<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileDetailsController extends Controller
{
    public function updateNames(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->unauthorizedResponse();
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'lastname' => ['required', 'string', 'max:255'],
        ], [
            'name.required' => 'Ingresa tus nombres.',
            'lastname.required' => 'Ingresa tus apellidos.',
        ]);

        $user->name = trim($data['name']);
        $user->lastname = trim($data['lastname']);
        $user->save();

        return $this->successResponse([
            'name' => $user->name,
            'lastname' => $user->lastname,
            'full_name' => trim(trim((string) $user->name . ' ' . (string) $user->lastname)),
        ], 'Nombres actualizados correctamente.');
    }

    public function updateGender(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->unauthorizedResponse();
        }

        $allowed = ['masculino', 'femenino', 'no binario', 'otro', 'prefiero no decir'];
        $data = $request->validate([
            'gender' => ['required', 'string', 'max:40', Rule::in($allowed)],
        ], [
            'gender.required' => 'Selecciona un género.',
            'gender.in' => 'Selecciona un género válido.',
        ]);

        $value = strtolower($data['gender']);
        $user->gender = $value;
        $user->save();

        return $this->successResponse([
            'gender' => $user->gender,
            'gender_label' => $this->formatGenderLabel($user->gender),
        ], 'Género actualizado.');
    }

    public function updateBirthdate(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->unauthorizedResponse();
        }

        $data = $request->validate([
            'birthdate' => ['required', 'date', 'after:1900-01-01', 'before:today'],
        ], [
            'birthdate.required' => 'La fecha de nacimiento es obligatoria.',
            'birthdate.after' => 'La fecha debe ser posterior al 1 de enero de 1900.',
            'birthdate.before' => 'La fecha debe ser anterior a hoy.',
        ]);

        $user->birthdate = $data['birthdate'];
        $user->save();
        $carbon = Carbon::parse($user->birthdate);

        return $this->successResponse([
            'birthdate' => $carbon->toDateString(),
            'age' => $carbon->age,
        ], 'Fecha de nacimiento actualizada.');
    }

    public function updateLocation(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->unauthorizedResponse();
        }

        $data = $request->validate([
            'location' => ['required', 'string', 'min:3', 'max:255'],
        ], [
            'location.required' => 'La ubicación es obligatoria.',
        ]);

        $user->location = trim($data['location']);
        $user->save();

        return $this->successResponse([
            'location' => $user->location,
        ], 'Ubicación actualizada.');
    }

    public function updateSpeciality(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->unauthorizedResponse();
        }

        if (!$this->userIsProfessional($user)) {
            return response()->json(['ok' => false, 'message' => 'Solo los profesionales pueden editar la especialidad.'], 403);
        }

        $data = $request->validate([
            'speciality' => ['required', 'string', 'min:3', 'max:255'],
        ], [
            'speciality.required' => 'La especialidad es obligatoria.',
        ]);

        $user->speciality = trim($data['speciality']);
        $user->save();

        return $this->successResponse([
            'speciality' => $user->speciality,
        ], 'Especialidad actualizada.');
    }

    protected function successResponse(array $fields, string $message): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'fields' => $fields,
            'message' => $message,
        ]);
    }

    protected function unauthorizedResponse(): JsonResponse
    {
        return response()->json(['ok' => false, 'message' => 'No autenticado.'], 401);
    }

    protected function formatGenderLabel(?string $value): string
    {
        if (!$value) {
            return 'No especificado';
        }

        $map = [
            'masculino' => 'Masculino',
            'femenino' => 'Femenino',
            'no binario' => 'No binario',
            'otro' => 'Otro',
            'prefiero no decir' => 'Prefiero no decir',
        ];

        $key = strtolower($value);
        return $map[$key] ?? ucfirst($value);
    }

    protected function userIsProfessional($user): bool
    {
        if (!$user) {
            return false;
        }

        try {
            return method_exists($user, 'hasRole') ? (bool) $user->hasRole('professional') : false;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
