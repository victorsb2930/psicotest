<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Appointment;
use App\Models\AppointmentRating;
use Illuminate\Auth\Access\HandlesAuthorization;

class AppointmentPolicy
{
    use HandlesAuthorization;

    protected function isParticipant(User $user, Appointment $appointment): bool
    {
        return $user->id === $appointment->professional_id || $user->id === $appointment->patient_id;
    }

    public function view(User $user, Appointment $appointment): bool
    {
        return $this->isParticipant($user, $appointment);
    }

    public function startSession(User $user, Appointment $appointment): bool
    {
        if(!$this->isParticipant($user, $appointment)) return false;
        // Allow session start if appointment already accepted or in_progress.
        if (in_array($appointment->status, ['accepted','in_progress'], true)) return true;
        // Also allow early access for participants when appointment is still 'pending'
        // but within the early access window configured in appointments. This mirrors
        // AppointmentSessionService::isWithinEarlyAccessWindow so clients can call
        // the start endpoint shortly before the scheduled time.
        try {
            if ($appointment->status === 'pending' && $appointment->start) {
                $minutes = (int) config('appointments.early_access_minutes', 15);
                $now = \Illuminate\Support\Carbon::now();
                $windowStart = $appointment->start->copy()->subMinutes($minutes);
                $windowEnd = $appointment->end ?: $appointment->start->copy()->addMinutes(60);
                if ($now->between($windowStart, $windowEnd)) {
                    return true;
                }
            }
        } catch (\Throwable $_) {
            // On any error, fall through to deny
        }
        return false;
    }

    public function heartbeat(User $user, Appointment $appointment): bool
    {
        return $this->startSession($user, $appointment);
    }

    public function completeSession(User $user, Appointment $appointment): bool
    {
        if(!$this->isParticipant($user, $appointment)) return false;
        return in_array($appointment->status, ['in_progress','accepted'], true);
    }

    public function requestReschedule(User $user, Appointment $appointment): bool
    {
        if(!$this->isParticipant($user, $appointment)) return false;
        // Block if already pending reschedule or completed/cancelled
        if(in_array($appointment->status, ['reschedule_pending','completed','cancelled','canceled'], true)) return false;
        return true;
    }

    public function rate(User $user, Appointment $appointment): bool
    {
        // Only patient can rate and only after completion
        if($user->id !== $appointment->patient_id) return false;
        if($appointment->status !== 'completed') return false;
        // Respect rating window
        $days = (int) config('appointments.rating_window_days', 7);
        if($appointment->end && now()->greaterThan($appointment->end->copy()->addDays($days))) return false;
        // Prevent double rating (creation only)
        $existing = AppointmentRating::where('appointment_id',$appointment->id)->where('patient_id',$user->id)->exists();
        return !$existing;
    }

    public function updateRating(User $user, Appointment $appointment): bool
    {
        if($user->id !== $appointment->patient_id) return false;
        if($appointment->status !== 'completed') return false;
        $hours = (int) config('appointments.rating_edit_hours', 2);
        $rating = AppointmentRating::where('appointment_id',$appointment->id)->where('patient_id',$user->id)->first();
        if(!$rating) return false;
        if($rating->created_at && now()->greaterThan($rating->created_at->copy()->addHours($hours))) return false;
        return true;
    }
}
