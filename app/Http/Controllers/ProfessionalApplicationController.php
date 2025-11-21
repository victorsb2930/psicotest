<?php

namespace App\Http\Controllers;

use App\Models\ProfessionalApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;

class ProfessionalApplicationController extends Controller
{
	/* Verifica acceso flexible: permite 'adminarea' o cualquiera de los permisos dados. */
	protected function ensureAccess(Request $request, array $abilities = []): void
	{
		$user = $request->user();
		if (!$user || !method_exists($user, 'can')) {
			abort(403, 'Acceso no autorizado');
		}
		// Umbrella adminarea
		try { if ($user->can('adminarea')) return; } catch (\Throwable $_) {}
		// Cualquiera de los permisos específicos
		foreach ($abilities as $ab) {
			try { if ($ab && $user->can($ab)) return; } catch (\Throwable $_) {}
		}
		abort(403, 'Acceso no autorizado');
	}

	public function index(Request $request)
	{
		$this->ensureAccess($request, ['professional_applications']);
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
		$this->ensureAccess($request, ['professional_applications']);
		// Require all existing documents to have been viewed before approving
		$requiredDocs = collect(['titulo','cedula','cv','exequatur'])->filter(fn($d)=>!empty($application->{$d.'_path'}))->values();
		$unviewed = $requiredDocs->filter(fn($d)=>empty($application->{$d.'_viewed_at'}))->values();
		if ($unviewed->count() > 0) {
			return back()->with('error', 'Debes revisar todos los documentos antes de aprobar. Faltan: '.implode(', ', $unviewed->all()));
		}
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
		$this->ensureAccess($request, ['professional_applications']);
		$requiredDocs = collect(['titulo','cedula','cv','exequatur'])->filter(fn($d)=>!empty($application->{$d.'_path'}))->values();
		$unviewed = $requiredDocs->filter(fn($d)=>empty($application->{$d.'_viewed_at'}))->values();
		if ($unviewed->count() > 0) {
			return back()->with('error', 'Debes revisar todos los documentos antes de rechazar. Faltan: '.implode(', ', $unviewed->all()));
		}
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
		$this->ensureAccess($request, ['professional_applications']);
		if (!in_array($field, ['titulo', 'cedula', 'cv', 'exequatur'], true)) {
			abort(404);
		}
		$path = match($field) {
			'titulo' => $application->titulo_path,
			'cedula' => $application->cedula_path,
			'cv' => $application->cv_path,
			'exequatur' => $application->exequatur_path,
			default => null,
		};
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

		// Mark document as viewed (best-effort; ignore failures)
		$col = $field.'_viewed_at';
		try {
			if (\Illuminate\Support\Facades\Schema::hasColumn('professional_applications', $col)) {
				$application->{$col} = now();
				$application->save();
			}
		} catch (\Throwable $_) { }

		return Response::stream(function () use ($stream) {
			fpassthru($stream);
		}, 200, [
			'Content-Type' => $mime,
			'Content-Disposition' => 'inline; filename="' . $filename . '"',
		]);
	}

	public function markDocViewed(Request $request, ProfessionalApplication $application)
	{
		$this->ensureAccess($request, ['professional_applications']);
		$field = (string)$request->input('field');
		if (!in_array($field, ['titulo','cedula','cv','exequatur'], true)) {
			return response()->json(['ok'=>false,'message'=>'invalid_field'], 422);
		}
		$pathAttr = $field.'_path';
		$col = $field.'_viewed_at';
		if (empty($application->{$pathAttr})) {
			return response()->json(['ok'=>false,'message'=>'missing_document'], 404);
		}
		try {
			if (\Illuminate\Support\Facades\Schema::hasColumn('professional_applications', $col)) {
				if (empty($application->{$col})) { $application->{$col} = now(); $application->save(); }
				return response()->json(['ok'=>true,'viewed_at'=>$application->{$col}->toDateTimeString()]);
			}
		} catch (\Throwable $e) {
			return response()->json(['ok'=>false,'message'=>'persist_error'], 500);
		}
		return response()->json(['ok'=>false,'message'=>'column_missing'], 500);
	}
}