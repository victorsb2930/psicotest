<?php

use App\Models\User;
use App\Models\Appointment;
use Illuminate\Support\Carbon;
use function Pest\Laravel\actingAs;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('rejects creation when start is in the past', function(){
    $pro = User::factory()->create();
    $this->actingAs($pro)->withoutMiddleware();

    $patient = User::factory()->create();

    $start = Carbon::now()->subMinute()->toIso8601String();
    $resp = $this->postJson(route('professional.calendar.events.store'), [
        'patient_id' => $patient->id,
        'start' => $start,
    ]);

    $resp->assertStatus(422)->assertJsonFragment(['field' => 'start']);
});

it('rejects when end is not after start', function(){
    $pro = User::factory()->create();
    $this->actingAs($pro)->withoutMiddleware();

    $patient = User::factory()->create();

    $start = Carbon::now()->addHour();
    $end = (clone $start);

    $resp = $this->postJson(route('professional.calendar.events.store'), [
        'patient_id' => $patient->id,
        'start' => $start->toIso8601String(),
        'end' => $end->toIso8601String(),
    ]);

    $resp->assertStatus(422)->assertJsonValidationErrors('end');
});

it('detects conflicts including end == start as conflict', function(){
    $pro = User::factory()->create();
    $this->actingAs($pro)->withoutMiddleware();

    $patient = User::factory()->create();

    // existing appointment 10:00 - 10:30
    $existingStart = Carbon::now()->addDay()->setHour(10)->setMinute(0)->setSecond(0);
    $existingEnd = (clone $existingStart)->addMinutes(30);
    Appointment::create([
        'professional_id' => $pro->id,
        'patient_id' => $patient->id,
        'start' => $existingStart->toDateTimeString(),
        'end' => $existingEnd->toDateTimeString(),
        'status' => 'pending',
    ]);

    // new appointment that starts exactly at existing end -> should be considered conflict per new rule
    $newStart = (clone $existingEnd);
    $newEnd = (clone $newStart)->addMinutes(30);

    $resp = $this->postJson(route('professional.calendar.events.store'), [
        'patient_id' => $patient->id,
        'start' => $newStart->toIso8601String(),
        'end' => $newEnd->toIso8601String(),
    ]);

    $resp->assertStatus(422)->assertJsonFragment(['error' => 'conflict']);
});

it('creates appointment when inputs valid and no conflict', function(){
    $pro = User::factory()->create();
    $this->actingAs($pro)->withoutMiddleware();

    $patient = User::factory()->create();

    $start = Carbon::now()->addDay()->setHour(14)->setMinute(0);
    $end = (clone $start)->addMinutes(30);

    $resp = $this->postJson(route('professional.calendar.events.store'), [
        'patient_id' => $patient->id,
        'start' => $start->toIso8601String(),
        'end' => $end->toIso8601String(),
    ]);

    $resp->assertStatus(200)->assertJsonFragment(['ok' => true]);
    $this->assertDatabaseHas('appointments', ['professional_id' => $pro->id, 'patient_id' => $patient->id]);
});
