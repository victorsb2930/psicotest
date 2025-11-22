<?php

namespace App\Policies;

use App\Models\User;
use App\Models\AppointmentReschedule;
use Illuminate\Auth\Access\HandlesAuthorization;

class AppointmentReschedulePolicy
{
    use HandlesAuthorization;

    protected function isParticipant(User $user, AppointmentReschedule $reschedule): bool
    {
        $appt = $reschedule->appointment;
        return $user->id === $appt->professional_id || $user->id === $appt->patient_id;
    }

    public function accept(User $user, AppointmentReschedule $reschedule): bool
    {
        if(!$this->isParticipant($user, $reschedule)) return false;
        return $reschedule->status === 'pending';
    }

    public function reject(User $user, AppointmentReschedule $reschedule): bool
    {
        if(!$this->isParticipant($user, $reschedule)) return false;
        return $reschedule->status === 'pending';
    }
}
