<?php

namespace App\Services;

use App\Models\ProfessionalAvailability;
use App\Models\ProfessionalAvailabilityException;
use App\Models\Appointment;
use Carbon\Carbon;

class AvailabilityService
{
    /**
     * Determine if the professional is available for the entire [start,end) interval.
     * Returns [bool available, string|null reason]
     */
    public function isSlotAvailable(int $professionalId, Carbon $startUtc, Carbon $endUtc): array
    {
        if ($endUtc->lte($startUtc)) {
            return [false, 'Intervalo inválido'];
        }

        // Normalize to professional's local day-of-week using app timezone (or stored timezone if any record has it)
        // For now assume app timezone set in config('app.timezone')
        $appTz = config('app.timezone');
        $startLocal = $startUtc->copy()->setTimezone($appTz);
        $endLocal = $endUtc->copy()->setTimezone($appTz);
        if ($startLocal->isSameDay($endLocal) === false) {
            // Reject cross-midnight for simplicity
            return [false, 'La cita no puede cruzar medianoche'];
        }
        $dow = (int) $startLocal->dayOfWeek; // 0-6

        // Fetch weekly availability ranges for that day
        $weekly = ProfessionalAvailability::where('user_id', $professionalId)
            ->where('active', true)
            ->where('day_of_week', $dow)
            ->get();
        if ($weekly->isEmpty()) {
            return [false, 'Profesional sin disponibilidad para ese día'];
        }

        $startMinutes = $startLocal->hour * 60 + $startLocal->minute;
        $endMinutes = $endLocal->hour * 60 + $endLocal->minute;

        $covers = $weekly->first(function($slot) use ($startMinutes,$endMinutes) {
            $s = $this->timeToMinutes($slot->start_time);
            $e = $this->timeToMinutes($slot->end_time);
            return $startMinutes >= $s && $endMinutes <= $e; // fully contained
        });
        if (!$covers) {
            return [false, 'Fuera del horario disponible'];
        }

        // Exceptions on that date
        $date = $startLocal->toDateString();
        $exceptions = ProfessionalAvailabilityException::where('user_id',$professionalId)->whereDate('date',$date)->get();
        foreach ($exceptions as $ex) {
            if ($ex->status === 'blocked') {
                // If full-day or overlaps any portion
                if (!$ex->start_time && !$ex->end_time) {
                    return [false, 'Día bloqueado'];
                }
                $exStart = $ex->start_time ? $this->timeToMinutes($ex->start_time) : 0;
                $exEnd = $ex->end_time ? $this->timeToMinutes($ex->end_time) : 24*60;
                if ($startMinutes < $exEnd && $endMinutes > $exStart) {
                    return [false, 'Horario bloqueado'];
                }
            } elseif ($ex->status === 'available') {
                // Additional availability slot: override cover check if inside available slot
                $exStart = $ex->start_time ? $this->timeToMinutes($ex->start_time) : 0;
                $exEnd = $ex->end_time ? $this->timeToMinutes($ex->end_time) : 24*60;
                if ($startMinutes >= $exStart && $endMinutes <= $exEnd) {
                    $covers = true; // treat as covered
                }
            }
        }
        if (!$covers) {
            return [false, 'Fuera del horario disponible'];
        }

        // Overlap with existing appointments (same professional)
        $conflict = Appointment::where('professional_id', $professionalId)
            ->whereNull('deleted_at')
            ->where(function($q) use ($startUtc,$endUtc) {
                // Overlap condition: start < existing.end AND end > existing.start (treat null end as start-only slot)
                $q->where(function($q2) use ($startUtc,$endUtc) {
                    $q2->where('start','<',$endUtc->toDateTimeString())
                       ->where(function($q3) use ($startUtc) {
                           $q3->whereNotNull('end')->where('end','>', $startUtc->toDateTimeString())
                              ->orWhere(function($q4) use ($startUtc) { $q4->whereNull('end')->where('start','>=',$startUtc->toDateTimeString())->where('start','<',$startUtc->toDateTimeString()); });
                       });
                });
            })->exists();
        if ($conflict) {
            return [false, 'Solapa con otra cita'];
        }

        return [true, null];
    }

    protected function timeToMinutes(string $time): int
    {
        [$h,$m,$s] = array_pad(explode(':',$time),3,'0');
        return (int)$h * 60 + (int)$m; // ignore seconds
    }
}
