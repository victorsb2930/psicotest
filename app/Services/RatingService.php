<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentRating;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class RatingService
{
    public function canRate(Appointment $appointment, User $patient): bool
    {
        if ($appointment->status !== 'completed') return false;
        if ($appointment->patient_id !== $patient->id) return false;
        $windowDays = (int) config('appointments.rating_window_days');
        $end = $appointment->end ?? now();
        if (now()->diffInDays($end) > $windowDays) return false;
        $exists = AppointmentRating::where('appointment_id',$appointment->id)->exists();
        return !$exists;
    }

    public function create(Appointment $appointment, User $patient, int $score, ?string $comment): AppointmentRating
    {
        if ($score < 1 || $score > 5) throw new \InvalidArgumentException('Invalid rating');
        if (!$this->canRate($appointment, $patient)) throw new \RuntimeException('Cannot rate appointment');
        $comment = $comment ? trim(mb_substr(strip_tags($comment),0,1000)) : null;
        return DB::transaction(function() use ($appointment,$patient,$score,$comment){
            $rating = AppointmentRating::create([
                'appointment_id' => $appointment->id,
                'professional_id' => $appointment->professional_id,
                'patient_id' => $patient->id,
                'rating' => $score,
                'comment' => $comment,
                'is_public' => true,
            ]);
            $this->recomputeStats($appointment->professional_id);
            return $rating;
        });
    }

    public function update(AppointmentRating $rating, int $score, ?string $comment): AppointmentRating
    {
        $editHours = (int) config('appointments.rating_edit_hours');
        if (now()->diffInHours($rating->created_at) > $editHours) throw new \RuntimeException('Edit window expired');
        if ($score < 1 || $score > 5) throw new \InvalidArgumentException('Invalid rating');
        $comment = $comment ? trim(mb_substr(strip_tags($comment),0,1000)) : null;
        $rating->rating = $score;
        $rating->comment = $comment;
        $rating->edited_at = now();
        $rating->save();
        $this->recomputeStats($rating->professional_id);
        return $rating;
    }

    public function recomputeStats(int $professionalId): void
    {
        $rows = AppointmentRating::where('professional_id',$professionalId)->where('is_public',true)->get(['rating']);
        $count = $rows->count();
        $avg = $count ? round($rows->avg('rating'),2) : 0.0;
        $breakdown = [1=>0,2=>0,3=>0,4=>0,5=>0];
        foreach ($rows as $r) { $breakdown[$r->rating] = ($breakdown[$r->rating] ?? 0) + 1; }
        $u = User::find($professionalId);
        if ($u) {
            $u->ratings_count = $count;
            $u->ratings_avg = $avg;
            $u->ratings_breakdown = json_encode($breakdown);
            $u->saveQuietly();
        }
    }
}
