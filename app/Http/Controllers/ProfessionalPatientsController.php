<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ProfessionalPatientsController extends Controller
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
            ->select('appointments.patient_id')
            ->selectRaw('MAX(patients.name) as patient_name')
            ->selectRaw('MAX(patients.lastname) as patient_lastname')
            ->selectRaw('MAX(patients.email) as patient_email')
            ->selectRaw('MAX(patients.phone) as patient_phone')
            ->selectRaw('MAX(patients.deleted_at) as patient_deleted_at')
            ->selectRaw('COUNT(*) as total_appointments')
            ->selectRaw("SUM(CASE WHEN appointments.status = 'completed' THEN 1 ELSE 0 END) as completed_appointments")
            ->selectRaw("SUM(CASE WHEN appointments.status IN ('accepted','pending','in_progress') THEN 1 ELSE 0 END) as active_appointments")
            ->selectRaw("SUM(CASE WHEN appointments.start >= ? THEN 1 ELSE 0 END) as upcoming_appointments", [$now])
            ->selectRaw('MAX(appointments.start) as last_appointment_at')
            ->selectRaw('MIN(appointments.start) as first_appointment_at')
            ->selectRaw("MAX(CASE WHEN appointments.start >= ? THEN appointments.start ELSE NULL END) as next_appointment_at", [$now])
            ->where('appointments.professional_id', $user->id)
            ->whereNotNull('appointments.patient_id')
            ->whereNull('appointments.deleted_at')
            ->leftJoin('users as patients', 'patients.id', '=', 'appointments.patient_id')
            ->groupBy('appointments.patient_id');

        if ($search !== '') {
            $query->where(function ($w) use ($search) {
                $like = '%' . $search . '%';
                $w->where('patients.name', 'like', $like)
                    ->orWhere('patients.lastname', 'like', $like)
                    ->orWhereRaw("CONCAT_WS(' ', patients.name, patients.lastname) LIKE ?", [$like])
                    ->orWhere('patients.email', 'like', $like)
                    ->orWhere('patients.phone', 'like', $like);
            });
        }

        $sortOptions = [
            'recent' => ['last_appointment_at', 'desc'],
            'name' => ['patient_name', 'asc'],
            'sessions' => ['total_appointments', 'desc'],
            'upcoming' => ['next_appointment_at', 'desc'],
        ];
        [$sortCol, $sortDir] = $sortOptions[$sort] ?? $sortOptions['recent'];
        $query->orderBy($sortCol, $sortDir);

        $patients = $query->paginate($perPage)->appends($request->query());
        $patients->getCollection()->transform(function ($row) {
            foreach (['last_appointment_at', 'first_appointment_at', 'next_appointment_at'] as $col) {
                $row->{$col} = $row->{$col} ? Carbon::parse($row->{$col}) : null;
            }
            return $row;
        });

        return view('professional.patients', [
            'patients' => $patients,
            'filters' => [
                'q' => $search,
                'sort' => $sort,
                'per_page' => $perPage,
            ],
        ]);
    }
}
