<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class ProfessionalSearchController extends Controller
{
    // Render the search page
    public function index()
    {
        return view('professionals.index');
    }

    // Return JSON list of professionals with optional filters
    public function search(Request $request)
    {
        $q = $request->query('q');
        $specialty = $request->query('specialty');
        $apptType = $request->query('type');

        $query = User::query();
        // filter by role professional if Spatie roles are present
        try { $query->role('professional'); } catch (\Throwable $e) { /* ignore if role package not available */ }

        if ($q) {
            $query->where(function($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%");
            });
        }

        if ($specialty) {
            // if users table has specialty column
            $query->where('specialty', 'like', "%{$specialty}%");
        }

        if ($apptType) {
            // appointment types could be stored as json or comma list in appointment_types column
            $query->where(function($sub) use ($apptType) {
                $sub->where('appointment_types', 'like', "%{$apptType}%")->orWhere('appointment_types', 'like', "%{$apptType}%");
            });
        }

        $users = $query->limit(50)->get();

        $result = $users->map(function($u){
            // ensure appointment_types is an array
            $types = null;
            try {
                if (is_array($u->appointment_types)) {
                    $types = $u->appointment_types;
                } elseif ($u->appointment_types) {
                    $types = json_decode($u->appointment_types, true);
                }
            } catch (\Throwable $e) {
                $types = null;
            }

            $photo = $u->photo ?? null;
            try {
                if (!$photo && method_exists($u, 'photos')) {
                    $pf = $u->photos()->where('is_profile', true)->first();
                    if ($pf) $photo = $pf->path;
                }
            } catch (\Throwable $_) { /* ignore */ }

            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'photo' => $photo,
                'specialty' => $u->specialty ?? null,
                'rating' => $u->rating ?? null,
                'appointment_types' => $types,
                'location' => $u->location ?? null,
            ];
        });

        return response()->json($result);
    }
}
