<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class UserProfessionalsController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            abort(401);
        }

        $search = trim((string) $request->query('q', ''));
        $sort = $request->query('sort', 'recent');
        $perPage = (int) $request->query('per_page', 25);
        if ($perPage < 10) {
            $perPage = 10;
        } elseif ($perPage > 100) {
            $perPage = 100;
        }

        $now = now();

        $query = Appointment::query()
            ->select('appointments.professional_id')
            ->selectRaw('MAX(professionals.name) as professional_name')
            ->selectRaw('MAX(professionals.lastname) as professional_lastname')
            ->selectRaw('MAX(professionals.email) as professional_email')
            ->selectRaw('MAX(professionals.phone) as professional_phone')
            ->selectRaw('MAX(professionals.speciality) as professional_speciality')
            ->selectRaw('MAX(professionals.deleted_at) as professional_deleted_at')
            ->selectRaw('COUNT(*) as total_appointments')
            ->selectRaw("SUM(CASE WHEN appointments.status = 'completed' THEN 1 ELSE 0 END) as completed_appointments")
            ->selectRaw("SUM(CASE WHEN appointments.status IN ('accepted','pending','in_progress') THEN 1 ELSE 0 END) as active_appointments")
            ->selectRaw("SUM(CASE WHEN appointments.start >= ? THEN 1 ELSE 0 END) as upcoming_appointments", [$now])
            ->selectRaw('MAX(appointments.start) as last_appointment_at')
            ->selectRaw('MIN(appointments.start) as first_appointment_at')
            ->selectRaw("MAX(CASE WHEN appointments.start >= ? THEN appointments.start ELSE NULL END) as next_appointment_at", [$now])
            ->where('appointments.patient_id', $user->id)
            ->whereNotNull('appointments.professional_id')
            ->whereNull('appointments.deleted_at')
            ->leftJoin('users as professionals', 'professionals.id', '=', 'appointments.professional_id')
            ->groupBy('appointments.professional_id');

        if ($search !== '') {
            $query->where(function ($w) use ($search) {
                $like = '%' . $search . '%';
                $w->where('professionals.name', 'like', $like)
                    ->orWhere('professionals.lastname', 'like', $like)
                    ->orWhereRaw("CONCAT_WS(' ', professionals.name, professionals.lastname) LIKE ?", [$like])
                    ->orWhere('professionals.email', 'like', $like)
                    ->orWhere('professionals.speciality', 'like', $like)
                    ->orWhere('professionals.phone', 'like', $like);
            });
        }

        $sortOptions = [
            'recent' => ['last_appointment_at', 'desc'],
            'name' => ['professional_name', 'asc'],
            'sessions' => ['total_appointments', 'desc'],
            'upcoming' => ['next_appointment_at', 'desc'],
        ];
        [$sortCol, $sortDir] = $sortOptions[$sort] ?? $sortOptions['recent'];
        $query->orderBy($sortCol, $sortDir);

        $professionals = $query->paginate($perPage)->appends($request->query());
        $professionals->getCollection()->transform(function ($row) {
            foreach (['last_appointment_at', 'first_appointment_at', 'next_appointment_at'] as $col) {
                $row->{$col} = $row->{$col} ? Carbon::parse($row->{$col}) : null;
            }
            return $row;
        });

        return view('user.professionals', [
            'professionals' => $professionals,
            'filters' => [
                'q' => $search,
                'sort' => $sort,
                'per_page' => $perPage,
            ],
        ]);
    }
}
