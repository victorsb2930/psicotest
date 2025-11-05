<?php

namespace App\Http\Controllers;

use App\Models\ProfessionalApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;

class ProfessionalApplicationController extends Controller
{
	public function index(Request $request)
	{
		if (!\Illuminate\Support\Facades\Schema::hasTable('professional_applications')) {
			$apps = collect([]);
			$status = $request->get('status');
			$q = trim((string) $request->get('q', ''));
			return view('admin.professional_applications', compact('apps', 'status', 'q'));
		}
		$status = $request->get('status');
		$q = trim((string) $request->get('q', ''));
		$apps = ProfessionalApplication::with(['user', 'reviewer'])
			->when($status && in_array($status, ['pending', 'approved', 'rejected'], true), fn ($qq) => $qq->where('status', $status))
			->when($q !== '', function ($qq) use ($q) {
				$qq->whereHas('user', function ($u) use ($q) {
					$u->where('name', 'like', "%$q%")
						->orWhere('email', 'like', "%$q%");
				});
			})
			->orderBy('status')
			->orderByDesc('id')
			->paginate(20)
			->withQueryString();

		return view('admin.professional_applications', compact('apps', 'status', 'q'));
	}

	public function approve(Request $request, ProfessionalApplication $application)
	{
		if ($application->status !== 'pending') {
			return back()->with('info', 'Esta solicitud ya fue revisada (estado: '.$application->status.').');
		}
		DB::transaction(function () use ($application) {
			$application->status = 'approved';
			$application->reviewed_by = auth()->id();
			$application->reviewed_at = now();
			$application->save();
			$proRole = \Spatie\Permission\Models\Role::where('name', 'professional')->first();
			if ($proRole && $application->user) {
				$u = $application->user;
				$u->syncRoles([$proRole->name]);
				$u->is_active = true;
				$u->save();
			}
		});
		try {
			if ($application->user && $application->user->email) {
				\Illuminate\Support\Facades\Mail::to($application->user->email)
					->send(new \App\Mail\ProfessionalApplicationApproved($application));
			}
		} catch (\Throwable $e) {
			\Log::warning('Mail send failed (approved): '.$e->getMessage());
		}

		return back()->with('success', 'Solicitud aprobada.');
	}

	public function reject(Request $request, ProfessionalApplication $application)
	{
		if ($application->status !== 'pending') {
			return back()->with('info', 'Esta solicitud ya fue revisada (estado: '.$application->status.').');
		}
		$notes = (string) $request->input('notes');
		$application->status = 'rejected';
		$application->notes = $notes;
		$application->reviewed_by = auth()->id();
		$application->reviewed_at = now();
		$application->save();
		try {
			if ($application->user && $application->user->email) {
				\Illuminate\Support\Facades\Mail::to($application->user->email)
					->send(new \App\Mail\ProfessionalApplicationRejected($application));
			}
		} catch (\Throwable $e) {
			\Log::warning('Mail send failed (rejected): '.$e->getMessage());
		}

		return back()->with('success', 'Solicitud rechazada.');
	}

	public function file(Request $request, ProfessionalApplication $application, string $field)
	{
		if (!in_array($field, ['titulo', 'cedula'], true)) {
			abort(404);
		}
		$path = $field === 'titulo' ? $application->titulo_path : $application->cedula_path;
		if (!$path) {
			abort(404);
		}

		// Backward-compatibility: files may have been stored on the 'local' disk originally.
		$disk = null;
		if (Storage::disk('public')->exists($path)) {
			$disk = Storage::disk('public');
		} elseif (Storage::disk('local')->exists($path)) {
			$disk = Storage::disk('local');
		} else {
			abort(404);
		}

		$stream = $disk->readStream($path);
		$mime = $disk->mimeType($path) ?: 'application/octet-stream';
		$filename = basename($path);

		return Response::stream(function () use ($stream) {
			fpassthru($stream);
		}, 200, [
			'Content-Type' => $mime,
			'Content-Disposition' => 'inline; filename="' . $filename . '"',
		]);
	}
}