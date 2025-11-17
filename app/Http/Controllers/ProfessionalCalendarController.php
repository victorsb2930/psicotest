<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class ProfessionalCalendarController extends Controller
{
	public function index()
	{
		return view('professional.calendar');
	}

	public function events(Request $request)
	{
		$user = Auth::user();
		$debug = $request->boolean('debug');
		$startStr = $request->query('start');
		$endStr = $request->query('end');
		$builder = Appointment::query()
			->where('professional_id', $user->id)
			->whereNull('deleted_at');
		if ($startStr) { try { $builder->where('start', '>=', Carbon::parse($startStr)); } catch (\Throwable $_) {} }
		if ($endStr) { try { $builder->where(function($q) use ($endStr){ $q->whereNull('end')->orWhere('end', '<=', Carbon::parse($endStr)); }); } catch (\Throwable $_) {} }
		$sql = null; $bindings = [];
		if ($debug) { try { $sql = $builder->toSql(); $bindings = $builder->getBindings(); } catch (\Throwable $_) { $sql = null; } }
		$raw = $builder->orderBy('start','asc')->get();
		$events = $raw->map(function($a){
			return [
				'id' => $a->id,
				'title' => $a->title ?: ($a->patient?->name ?? 'Cita'),
				'start' => $a->start?->setTimezone('UTC')->toIso8601String(),
				'end' => $a->end?->setTimezone('UTC')->toIso8601String(),
				'allDay' => (bool) $a->all_day,
				'status' => $a->status,
				'notes' => $a->notes ?? null,
				'rejection_reason' => $a->rejection_reason ?? null,
				'patient_name' => $a->patient?->name,
			];
		});
		if ($debug) {
			$roleNames = []; try { $roleNames = method_exists($user,'roles') ? $user->roles->pluck('name')->values()->all() : []; } catch (\Throwable $_) {}
			return response()->json([
				'debug' => true,
				'auth_user_id' => $user->id,
				'auth_user_roles' => $roleNames,
				'requested_start' => $startStr,
				'requested_end' => $endStr,
				'sql' => $sql,
				'bindings' => $bindings,
				'count' => $events->count(),
				'event_ids' => $events->pluck('id')->values(),
				'events' => $events,
			]);
		}
		return response()->json($events);
	}

	public function store(Request $request)
	{
		$user = Auth::user();
		$data = $request->validate([
			'patient_id' => ['required','integer', Rule::exists('users','id')],
			'title' => ['nullable','string','max:255'],
			'start' => ['required','date'],
			'end' => ['nullable','date','after:start'],
			'all_day' => ['sometimes','boolean'],
			'notes' => ['nullable','string'],
		]);
		$start = Carbon::parse($data['start'])->setTimezone('UTC');
		$end = isset($data['end']) ? Carbon::parse($data['end'])->setTimezone('UTC') : null;
		$now = Carbon::now()->setTimezone('UTC');
		if ($start->lt($now)) {
			return response()->json([
				'error' => 'validation',
				'field' => 'start',
				'message' => 'La fecha/hora de inicio indicada es anterior al momento actual.'
			], 422);
		}
		if ($end && !$end->gt($start)) {
			return response()->json([
				'error' => 'validation',
				'field' => 'end',
				'message' => 'La fecha/hora de fin debe ser posterior a la de inicio.'
			], 422);
		}
		try {
			$svc = app(\App\Services\AvailabilityService::class);
			$effectiveEnd = $end ?? $start->copy()->addMinutes(30);
			[$ok,$reason] = $svc->isSlotAvailable($user->id, $start, $effectiveEnd);
			if (!$ok) {
				return response()->json([
					'error' => 'availability',
					'field' => 'start',
					'message' => $reason ?? 'Horario no disponible'
				], 422);
			}
		} catch (\Throwable $ex) { \Log::error('Availability check failed (professional store)', ['err'=>$ex->getMessage()]); }
		$conflicts = Appointment::where('professional_id', $user->id)
			->whereNull('deleted_at')
			->where(function($q) use ($start, $end) {
				$q->where('start', '<=', $end ?? $start)
				->where(function($q2) use ($start) { $q2->whereNull('end')->orWhere('end', '>=', $start); });
			})->get();
		if ($conflicts->isNotEmpty()) {
			return response()->json([
				'error' => 'conflict',
				'field' => 'start',
				'message' => 'La nueva cita solapa con citas existentes.',
				'conflicts' => $conflicts->map(function($c){
					return [
						'id' => $c->id,
						'start' => $c->start?->setTimezone('UTC')->toIso8601String(),
						'end' => $c->end?->setTimezone('UTC')->toIso8601String(),
						'title' => $c->title,
					];
				})->values(),
			], 422);
		}
		$appoint = Appointment::create([
			'professional_id' => $user->id,
			'patient_id' => $data['patient_id'],
			'title' => $data['title'] ?? null,
			'start' => $start->toDateTimeString(),
			'end' => $end?->toDateTimeString() ?? null,
			'all_day' => $data['all_day'] ?? false,
			'notes' => $data['notes'] ?? null,
			'status' => 'pending',
		]);
		try { $appoint->patient->notify(new \App\Notifications\AppointmentCreated($appoint)); } catch (\Throwable $ex) {}
		return response()->json(['ok'=>true,'appointment'=>$appoint]);
	}

	public function searchPatients(Request $request)
	{
		$q = trim((string) $request->get('q',''));
		$roleId = 3; // 'user' role id
		$users = User::query()
			->join('model_has_roles', function($j) use ($roleId){
				$j->on('model_has_roles.model_id','=','users.id')
				  ->where('model_has_roles.role_id', $roleId)
				  ->where('model_has_roles.model_type', User::class);
			})
			->where(function($w) use ($q){
				$w->where('users.name','like', "%{$q}%")
				  ->orWhere('users.email','like', "%{$q}%");
			})
			->select('users.id','users.name','users.email')
			->orderBy('users.name')
			->limit(20)
			->get();
		\Log::info('searchPatients query', ['term' => $q, 'count' => $users->count()]);
		return response()->json($users);
	}
}