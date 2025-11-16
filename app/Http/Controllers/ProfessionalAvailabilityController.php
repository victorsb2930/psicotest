<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\ProfessionalAvailability;
use App\Models\ProfessionalAvailabilityException;
use Carbon\Carbon;

class ProfessionalAvailabilityController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $weekly = ProfessionalAvailability::where('user_id',$user->id)->orderBy('day_of_week')->orderBy('start_time')->get();
        $exceptions = ProfessionalAvailabilityException::where('user_id',$user->id)->orderBy('date','desc')->limit(30)->get();
        return view('professional.availability', compact('weekly','exceptions'));
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $data = $request->validate([
            'day_of_week' => ['required','integer','between:0,6'],
            'start_time' => ['required','date_format:H:i'],
            'end_time' => ['required','date_format:H:i','after:start_time'],
        ]);
        // Simple overlap guard (same day, overlapping range)
        $conflict = ProfessionalAvailability::where('user_id',$user->id)
            ->where('day_of_week',$data['day_of_week'])
            ->where(function($q) use ($data){
                $q->where('start_time','<',$data['end_time'])
                  ->where('end_time','>',$data['start_time']);
            })->exists();
        if ($conflict) {
            return response()->json(['ok'=>false,'message'=>'Rango solapa con otro existente'],422);
        }
        $row = ProfessionalAvailability::create([
            'user_id'=>$user->id,
            'day_of_week'=>$data['day_of_week'],
            'start_time'=>$data['start_time'] . ':00',
            'end_time'=>$data['end_time'] . ':00',
            'active'=>true,
        ]);
        return response()->json(['ok'=>true,'slot'=>$row]);
    }

    public function destroy(Request $request, ProfessionalAvailability $availability)
    {
        $user = Auth::user();
        if ($availability->user_id !== $user->id) return response()->json(['ok'=>false],403);
        try { $availability->delete(); } catch(\Throwable $e) {}
        return response()->json(['ok'=>true]);
    }

    public function storeException(Request $request)
    {
        $user = Auth::user();
        $data = $request->validate([
            'date' => ['required','date'],
            'status' => ['required','in:available,blocked'],
            'start_time' => ['nullable','date_format:H:i'],
            'end_time' => ['nullable','date_format:H:i','after:start_time'],
            'reason' => ['nullable','string','max:255'],
        ]);
        if (($data['start_time'] && !$data['end_time']) || (!$data['start_time'] && $data['end_time'])) {
            return response()->json(['ok'=>false,'message'=>'Debe indicar ambos tiempos o ninguno'],422);
        }
        $row = ProfessionalAvailabilityException::create([
            'user_id'=>$user->id,
            'date'=>Carbon::parse($data['date'])->toDateString(),
            'start_time'=>$data['start_time']? $data['start_time'].':00': null,
            'end_time'=>$data['end_time']? $data['end_time'].':00': null,
            'status'=>$data['status'],
            'reason'=>$data['reason'] ?? null,
        ]);
        return response()->json(['ok'=>true,'exception'=>$row]);
    }

    public function destroyException(Request $request, ProfessionalAvailabilityException $exception)
    {
        $user = Auth::user();
        if ($exception->user_id !== $user->id) return response()->json(['ok'=>false],403);
        try { $exception->delete(); } catch(\Throwable $e) {}
        return response()->json(['ok'=>true]);
    }
}
