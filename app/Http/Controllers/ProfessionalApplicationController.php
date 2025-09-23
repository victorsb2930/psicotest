<?php

namespace App\Http\Controllers;

use App\Models\ProfessionalApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;

class ProfessionalApplicationController extends Controller
{
	public function demo(Request $request)
	{
		if (!\Illuminate\Support\Facades\Schema::hasTable('professional_applications')) {
			return back()->with('error', 'Falta la tabla professional_applications. Ejecuta migraciones.');
		}
		$email = (string) ($request->input('email') ?: 'pro_demo_'.time().'@local.test');
		$now = now();
		foreach (['users','roles'] as $t) {
			if (!\Illuminate\Support\Facades\Schema::hasTable($t)) {
				return back()->with('error', "Falta la tabla {$t}. Ejecuta migraciones.");
			}
		}
		// Roles base
		$rolePro = \Illuminate\Support\Facades\DB::table('roles')->where('name','professional')->first();
		if (!$rolePro) {
			\Illuminate\Support\Facades\DB::table('roles')->insert([
				'name' => 'professional',
				'guard_name' => 'web',
				'show_in_signup' => true,
				'requires_docs' => true,
				'created_at' => $now,
				'updated_at' => $now,
			]);
			$rolePro = \Illuminate\Support\Facades\DB::table('roles')->where('name','professional')->first();
		} else if (\Illuminate\Support\Facades\Schema::hasColumn('roles','requires_docs')) {
			\Illuminate\Support\Facades\DB::table('roles')->where('id',$rolePro->id)->update(['requires_docs' => true, 'updated_at' => $now]);
		}
		$roleUser = \Illuminate\Support\Facades\DB::table('roles')->where('name','user')->first();
		if (!$roleUser) {
			\Illuminate\Support\Facades\DB::table('roles')->insert([
				'name' => 'user',
				'guard_name' => 'web',
				'show_in_signup' => true,
				'requires_docs' => false,
				'created_at' => $now,
				'updated_at' => $now,
			]);
			$roleUser = \Illuminate\Support\Facades\DB::table('roles')->where('name','user')->first();
		}
		// Usuario
		$user = \Illuminate\Support\Facades\DB::table('users')->where(\Illuminate\Support\Facades\DB::raw('LOWER(email)'), strtolower($email))->first();
		if (!$user) {
			$uid = \Illuminate\Support\Facades\DB::table('users')->insertGetId([
				'name' => 'Pro Demo',
				'email' => $email,
				'password' => \Illuminate\Support\Facades\Hash::make('secret123'),
				'is_active' => false,
				'created_at' => $now,
				'updated_at' => $now,
			]);
			$user = \Illuminate\Support\Facades\DB::table('users')->where('id',$uid)->first();
		} else {
			\Illuminate\Support\Facades\DB::table('users')->where('id',$user->id)->update(['is_active' => false, 'updated_at' => $now]);
		}
		// Asignar rol base user
		if ($roleUser) {
			\Illuminate\Support\Facades\DB::table('model_has_roles')->updateOrInsert([
				'model_type' => \App\Models\User::class,
				'model_id' => $user->id,
				'role_id' => $roleUser->id,
			], []);
		}
		// Archivos
		$titulo = 'professional_docs/'.(string) \Illuminate\Support\Str::uuid().'.pdf';
		$cedula = 'professional_docs/'.(string) \Illuminate\Support\Str::uuid().'.pdf';
		\Illuminate\Support\Facades\Storage::disk('local')->put($titulo, '');
		\Illuminate\Support\Facades\Storage::disk('local')->put($cedula, '');
		// Crear solicitud
		$exists = \Illuminate\Support\Facades\DB::table('professional_applications')->where('user_id',$user->id)->where('status','pending')->exists();
		if (!$exists) {
			\Illuminate\Support\Facades\DB::table('professional_applications')->insert([
				'user_id' => $user->id,
				'titulo_path' => $titulo,
				'cedula_path' => $cedula,
				'status' => 'pending',
				'created_at' => $now,
				'updated_at' => $now,
			]);
		}
		$count = \Illuminate\Support\Facades\DB::table('professional_applications')->where('status','pending')->count();
		return back()->with('success', 'Solicitud demo creada. Pendientes: '.$count);
	}
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
		if (! in_array($field, ['titulo', 'cedula'], true)) {
			abort(404);
		}
		$path = $field === 'titulo' ? $application->titulo_path : $application->cedula_path;
		if (! $path || ! Storage::disk('local')->exists($path)) {
			abort(404);
		}
		$stream = Storage::disk('local')->readStream($path);
		$mime = Storage::disk('local')->mimeType($path) ?: 'application/octet-stream';

		return Response::stream(function () use ($stream) {
			fpassthru($stream);
		}, 200, ['Content-Type' => $mime]);
	}
}