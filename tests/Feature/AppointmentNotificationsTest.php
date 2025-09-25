<?php

use App\Models\User;
use App\Models\Appointment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use App\Notifications\AppointmentCreated;
use App\Notifications\AppointmentAccepted;
use function Pest\Laravel\actingAs;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('notifies patient when appointment created', function(){
    Notification::fake();

    $pro = User::factory()->create();
    $patient = User::factory()->create();

    $this->actingAs($pro)->withoutMiddleware();

    $start = Carbon::now()->addDay()->setHour(11)->setMinute(0)->toIso8601String();
    $end = Carbon::now()->addDay()->setHour(11)->setMinute(30)->toIso8601String();

    $resp = $this->postJson(route('professional.calendar.events.store'), [
        'patient_id' => $patient->id,
        'start' => $start,
        'end' => $end,
    ]);

    $resp->assertStatus(200);

    Notification::assertSentTo($patient, AppointmentCreated::class);
});

it('notifies professional when patient accepts', function(){
    Notification::fake();

    $pro = User::factory()->create();
    $patient = User::factory()->create();

    $appt = Appointment::create([
        'professional_id' => $pro->id,
        'patient_id' => $patient->id,
        'start' => Carbon::now()->addDay()->toDateTimeString(),
        'end' => Carbon::now()->addDay()->addMinutes(30)->toDateTimeString(),
        'status' => 'pending',
    ]);

    $this->actingAs($patient)->withoutMiddleware();

    // debug asserts to ensure test setup is correct
    $this->assertEquals($patient->id, auth()->id());
    $this->assertDatabaseHas('appointments', ['id' => $appt->id, 'patient_id' => $patient->id]);

    $resp = $this->postJson("/professional/calendar/events/{$appt->id}/accept");

    $resp->assertStatus(200);

    Notification::assertSentTo($pro, AppointmentAccepted::class);
});
