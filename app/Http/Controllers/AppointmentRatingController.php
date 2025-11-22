<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Appointment;
use App\Models\AppointmentRating;
use App\Services\RatingService;
use App\Notifications\RatingSubmitted;

class AppointmentRatingController extends Controller
{
    public function store(Request $request, Appointment $appointment, RatingService $service)
    {
        $this->authorize('rate', $appointment);
        $user = $request->user();
        $data = $request->validate([
            'rating' => ['required','integer','min:1','max:5'],
            // Make comment required to align with new UX
            'comment' => ['required','string','max:1000'],
        ]);
        try {
            $rating = $service->create($appointment, $user, $data['rating'], $data['comment'] ?? null);
            try { $appointment->professional?->notify(new RatingSubmitted($rating)); } catch (\Throwable $e) {}
            return response()->json(['ok'=>true,'rating_id'=>$rating->id]);
        } catch (\Throwable $e) {
            return response()->json(['ok'=>false,'message'=>$e->getMessage()], 422);
        }
    }

    public function update(Request $request, Appointment $appointment, RatingService $service)
    {
        $this->authorize('updateRating', $appointment);
        $user = $request->user();
        $data = $request->validate([
            'rating' => ['required','integer','min:1','max:5'],
            'comment' => ['required','string','max:1000'],
        ]);
        $rating = AppointmentRating::where('appointment_id',$appointment->id)->where('patient_id',$user->id)->first();
        if (!$rating) return response()->json(['ok'=>false,'message'=>'Rating not found'],404);
        try {
            $rating = $service->update($rating, $data['rating'], $data['comment'] ?? null);
            return response()->json(['ok'=>true,'rating_id'=>$rating->id]);
        } catch (\Throwable $e) {
            return response()->json(['ok'=>false,'message'=>$e->getMessage()],422);
        }
    }

    public function summary(int $professionalId)
    {
        $pro = \App\Models\User::findOrFail($professionalId);
        return response()->json([
            'ok' => true,
            'professional_id' => $pro->id,
            'ratings_count' => (int) $pro->ratings_count,
            'ratings_avg' => (float) $pro->ratings_avg,
            'ratings_breakdown' => $this->decodeBreakdown($pro->ratings_breakdown),
        ]);
    }

    protected function decodeBreakdown($json): array
    {
        if (!$json) return [1=>0,2=>0,3=>0,4=>0,5=>0];
        $d = json_decode($json,true); if (!is_array($d)) return [1=>0,2=>0,3=>0,4=>0,5=>0];
        return $d + [1=>0,2=>0,3=>0,4=>0,5=>0];
    }

    public function professionalIndex(Request $request)
    {
        $pro = $request->user();
        // Guard: must be professional role or permission
        if (!$pro || (!$pro->hasRole('professional') && !$pro->can('professionalarea'))) {
            abort(403);
        }
        $ratingsQuery = AppointmentRating::query()->where('professional_id', $pro->id);
        // Optional filters
        if ($request->filled('min')) {
            $ratingsQuery->where('rating', '>=', (int)$request->input('min'));
        }
        if ($request->filled('has_comment')) {
            $val = filter_var($request->input('has_comment'), FILTER_VALIDATE_BOOLEAN);
            if ($val) { $ratingsQuery->whereNotNull('comment')->where('comment','!=',''); }
        }
        if ($request->filled('q')) {
            $q = trim($request->input('q')); if ($q !== '') { $ratingsQuery->where('comment','like','%'.$q.'%'); }
        }
        if ($request->filled('from')) {
            try { $from = \Carbon\Carbon::parse($request->input('from')); $ratingsQuery->where('created_at','>=',$from); } catch(\Throwable $e){}
        }
        if ($request->filled('to')) {
            try { $to = \Carbon\Carbon::parse($request->input('to')); $ratingsQuery->where('created_at','<=',$to); } catch(\Throwable $e){}
        }

        $ratings = $ratingsQuery->latest()->paginate(30)->withQueryString();
        // Aggregates
        $all = AppointmentRating::where('professional_id', $pro->id)->get(['rating']);
        $breakdown = [1=>0,2=>0,3=>0,4=>0,5=>0];
        foreach ($all as $r) { $breakdown[(int)$r->rating] = ($breakdown[(int)$r->rating] ?? 0) + 1; }
        $total = array_sum($breakdown);
        $avg = $total > 0 ? round(collect($all)->avg('rating'),2) : 0.0;
        $pctHigh = $total > 0 ? round((($breakdown[4]+$breakdown[5]) / $total) * 100,1) : 0.0;
        // Prepare dataset for simple monthly trend (last 6 months)
        // Portable monthly trend: use database-specific date formatting.
        $driver = \DB::getDriverName();
        if ($driver === 'pgsql') {
            $trend = AppointmentRating::selectRaw("to_char(created_at,'YYYY-MM') as ym, AVG(rating) as avg_rating, COUNT(*) as c")
                ->where('professional_id',$pro->id)
                ->groupBy('ym')
                ->orderBy('ym','desc')
                ->limit(6)
                ->get();
        } elseif ($driver === 'sqlite') {
            // strftime for sqlite
            $trend = AppointmentRating::selectRaw("strftime('%Y-%m', created_at) as ym, AVG(rating) as avg_rating, COUNT(*) as c")
                ->where('professional_id',$pro->id)
                ->groupBy('ym')
                ->orderBy('ym','desc')
                ->limit(6)
                ->get();
        } else { // mysql/maria & fallback
            $trend = AppointmentRating::selectRaw("DATE_FORMAT(created_at,'%Y-%m') as ym, AVG(rating) as avg_rating, COUNT(*) as c")
                ->where('professional_id',$pro->id)
                ->groupBy('ym')
                ->orderBy('ym','desc')
                ->limit(6)
                ->get();
        }

        return view('professional.ratings', [
            'ratings' => $ratings,
            'breakdown' => $breakdown,
            'total' => $total,
            'avg' => $avg,
            'pctHigh' => $pctHigh,
            'trend' => $trend,
        ]);
    }

    public function moderate(Request $request, AppointmentRating $rating)
    {
        $pro = $request->user();
        if (!$pro || $rating->professional_id !== $pro->id) abort(403);
        $data = $request->validate([
            'is_public' => ['sometimes','boolean'],
            'response_text' => ['sometimes','nullable','string','max:2000'],
        ]);
        if (array_key_exists('is_public',$data)) {
            $rating->is_public = (bool)$data['is_public'];
        }
        if (array_key_exists('response_text',$data)) {
            $rating->response_text = $data['response_text'] !== '' ? $data['response_text'] : null;
            $rating->responded_at = $rating->response_text ? now() : null;
        }
        try { $rating->save(); } catch (\Throwable $e) { return response()->json(['ok'=>false,'message'=>'save_error'],500); }
        return response()->json(['ok'=>true]);
    }
}
