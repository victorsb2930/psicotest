<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Payment;

class ProfessionalPaymentsController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user || (!$user->hasRole('professional') && !$user->can('professionalarea'))) {
            abort(403);
        }

        $query = Payment::with(['user','subscription'])
            ->where('recipient_user_id', $user->id);

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }
        if ($request->filled('from')) {
            try { $from = \Carbon\Carbon::parse($request->query('from')); $query->where('created_at', '>=', $from); } catch (\Throwable $_) {}
        }
        if ($request->filled('to')) {
            try { $to = \Carbon\Carbon::parse($request->query('to'))->endOfDay(); $query->where('created_at', '<=', $to); } catch (\Throwable $_) {}
        }

        $payments = $query->orderBy('created_at','desc')->paginate(30)->withQueryString();

        return view('professional.payments_history', [
            'payments' => $payments,
            'filters' => [ 'status' => $request->query('status',''), 'from' => $request->query('from',''), 'to' => $request->query('to','') ],
        ]);
    }

    public function export(Request $request)
    {
        $user = $request->user();
        if (!$user || (!$user->hasRole('professional') && !$user->can('professionalarea'))) {
            abort(403);
        }
        $format = strtolower(trim($request->query('format','csv')));
        if (!in_array($format, ['csv','xlsx'], true)) $format = 'csv';

        $query = Payment::with(['user','subscription'])->where('recipient_user_id', $user->id);
        if ($request->filled('status')) $query->where('status', $request->query('status'));
        if ($request->filled('from')) { try { $from = \Carbon\Carbon::parse($request->query('from')); $query->where('created_at','>=',$from); } catch (\Throwable $_) {} }
        if ($request->filled('to')) { try { $to = \Carbon\Carbon::parse($request->query('to'))->endOfDay(); $query->where('created_at','<=',$to); } catch (\Throwable $_) {} }

        $filename = 'pagos_'.$user->id.'_'.now()->format('Ymd_His').'.'.$format;
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        $callback = function() use ($query) {
            $out = fopen('php://output','w');
            // BOM for Excel
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, ['ID','FECHA','MONTO','CURRENCY','STATUS','PAYER','PAYER_EMAIL','APPOINTMENT_ID','PROVIDER','REFERENCE']);
            $query->chunk(500, function($chunk) use ($out){
                foreach ($chunk as $p) {
                    fputcsv($out, [
                        $p->id,
                        optional($p->created_at)->format('Y-m-d H:i:s'),
                        number_format(($p->amount_cents / 100), 2, '.', ''),
                        $p->currency,
                        $p->status,
                        $p->user?->name,
                        $p->user?->email,
                        $p->meta['appointment_id'] ?? null,
                        $p->provider,
                        $p->provider_charge_id,
                    ]);
                }
            });
            fclose($out);
        };
        return response()->stream($callback, 200, $headers);
    }
}
