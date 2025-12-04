<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use Illuminate\Http\Request;

class ProfessionalAppointmentHistoryController extends Controller
{
    /**
     * Build the base query applying filters (shared between HTML + export).
     */
    protected function buildFilteredQuery(Request $request, $user)
    {
        $finalStates = ['completed','skipped','no_show','cancelled','canceled','rejected'];
        $allowedStatusFilter = array_merge(['accepted','pending','in_progress'], $finalStates);
        $status = strtolower(trim((string)$request->query('status','')));
        if ($status !== '' && !in_array($status, $allowedStatusFilter, true)) { $status = ''; }
        $patient = trim((string)$request->query('patient',''));
        $patientId = (int) $request->query('patient_id', 0);
        $from = $request->query('from');
        $to = $request->query('to');
        $sort = $request->query('sort','start_desc');

        $query = Appointment::with(['patient','rating','session'])
            ->where('professional_id', $user->id)
            ->where(function($q) use ($finalStates){
                $q->where('start','<', now())
                  ->orWhereIn('status', $finalStates);
            });
        if ($status !== '') { $query->where('status', $status); }
        if ($patient !== '') {
            $query->whereHas('patient', function($q) use ($patient){
                $q->where('name','like', "%{$patient}%")
                  ->orWhere('email','like', "%{$patient}%");
            });
        }
        if ($patientId > 0) {
            $query->where('patient_id', $patientId);
        }
        if ($from) { try { $query->where('start','>=', \Carbon\Carbon::parse($from)); } catch (\Throwable $_) {} }
        if ($to) { try { $query->where('start','<=', \Carbon\Carbon::parse($to)->endOfDay()); } catch (\Throwable $_) {} }
        $sortMap = [ 'start_desc' => ['start','desc'], 'start_asc' => ['start','asc'], 'status' => ['status','asc'] ];
        [$col,$dir] = $sortMap[$sort] ?? ['start','desc'];
        $query->orderBy($col,$dir);
        $filters = compact('status','patient','from','to','sort');
        $filters['patient_id'] = $patientId > 0 ? $patientId : null;
        return [$query, $allowedStatusFilter, $filters];
    }
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user) { abort(401); }

        [$query,$allowedStatusFilter,$filters] = $this->buildFilteredQuery($request, $user);
        $appointments = $query->paginate(50)->appends($request->query());
        return view('professional.appointments_history', [
            'appointments' => $appointments,
            'filters' => $filters,
            'allowedStatuses' => $allowedStatusFilter,
        ]);
    }

    public function export(Request $request)
    {
        $user = $request->user(); if (!$user) abort(401);
        [$query] = $this->buildFilteredQuery($request, $user);
        $format = strtolower(trim($request->query('format','csv')));
        if (!in_array($format, ['csv','xlsx'], true)) { $format = 'csv'; }
        // We will treat xlsx as CSV with a different extension unless a real Excel package is added.
        $filename = 'citas_'.$user->id.'_'.now()->format('Ymd_His').'.'.$format;
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];
        $callback = function() use ($query) {
            $out = fopen('php://output', 'w');
            // BOM for Excel compatibility with UTF-8
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, ['ID','FECHA_INICIO','FECHA_FIN','PACIENTE','EMAIL_PACIENTE','ESTADO','TITULO','NOTAS','CALIFICACION','RESPUESTA']);
            $query->chunk(500, function($chunk) use ($out){
                foreach ($chunk as $a) {
                    $rating = $a->rating?->score;
                    $response = $a->rating?->response_text;
                    fputcsv($out, [
                        $a->id,
                        optional($a->start)->format('Y-m-d H:i:s'),
                        optional($a->end)->format('Y-m-d H:i:s'),
                        $a->patient?->name,
                        $a->patient?->email,
                        $a->status,
                        $a->title,
                        $a->notes,
                        $rating,
                        $response,
                    ]);
                }
            });
            fclose($out);
        };
        return response()->stream($callback, 200, $headers);
    }
}
