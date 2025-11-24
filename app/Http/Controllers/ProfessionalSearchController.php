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
		$speciality = $request->query('speciality');
		$apptType = $request->query('type');

		$query = User::query();
		// filter by role professional if Spatie roles are present
		try { $query->role('professional'); } catch (\Throwable $e) { /* ignore if role package not available */ }

		if ($q) {
			$query->where(function($sub) use ($q) {
				$sub->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%");
			});
		}

		if ($speciality) {
			// if users table has speciality column
			$query->where('speciality', 'like', "%{$speciality}%");
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

			// Prefer data URL from accessor (handles blob or filesystem path)
			$photo = null;
			try {
				if (method_exists($u, 'getProfilePhotoDataUrlAttribute')) {
					$photo = $u->profile_photo_data_url ?? null;
				}
			} catch (\Throwable $_) { $photo = null; }

			try {
				if (!$photo) {
					// try explicit user->photo first (may already be a URL or a storage path)
					$photo = $u->photo ?? null;
				}
				if (!$photo && method_exists($u, 'photos')) {
					$pf = $u->photos()->where('is_profile', true)->first();
					if ($pf) $photo = $pf->path ?? ($pf->foto ?? null);
				}
			} catch (\Throwable $_) { /* ignore */ }

			// If we have a storage path (like user_photos/...), try to return a data URL
			if ($photo && is_string($photo)) {
				// if already a data URL or full URL, keep as-is
				if (str_starts_with($photo, 'data:') || preg_match('#^https?://#i', $photo)) {
					// nothing
				} else {
					try {
						$storage = \Illuminate\Support\Facades\Storage::disk('public');
						$candidates = [
							$photo,
							ltrim($photo, '/'),
							preg_replace('#^/storage/#', '', $photo),
						];
						$found = null;
						foreach ($candidates as $cand) {
							if ($cand && $storage->exists($cand)) { $found = $cand; break; }
						}
						if ($found) {
							$bytes = $storage->get($found);
							if ($bytes !== null && $bytes !== false && $bytes !== '') {
									$mime = null;
									try { $f = new \finfo(FILEINFO_MIME_TYPE); $mime = $f->buffer($bytes); } catch (\Throwable$_) { $mime = null; }
									if (!$mime) { $info = @getimagesizefromstring($bytes); if ($info && !empty($info['mime'])) $mime = $info['mime']; }
									if (!$mime) $mime = 'application/octet-stream';
									$photo = 'data:'.$mime.';base64,'.base64_encode($bytes);
								}
						} else {
							// as a fallback, convert to storage URL or /storage/ path
							try { $photo = \Illuminate\Support\Facades\Storage::url($photo); } catch (\Throwable$_) { if (!str_starts_with($photo, '/')) $photo = '/storage/' . ltrim($photo, '/'); }
						}
					} catch (\Throwable$_) {
						// keep original value
					}
				}
			}

			// compute appointment counts: total completed and completed in current month
			$completedTotal = 0;
			$completedThisMonth = 0;
			try {
				$completedTotal = \App\Models\Appointment::where('professional_id', $u->id)->where('status', 'completed')->count();
				$start = \Carbon\Carbon::now()->startOfMonth();
				$end = \Carbon\Carbon::now()->endOfMonth();
				$completedThisMonth = \App\Models\Appointment::where('professional_id', $u->id)->where('status', 'completed')->whereBetween('start', [$start, $end])->count();
			} catch (\Throwable $_) { /* ignore */ }

			return [
				'id' => $u->id,
				'name' => $u->name,
				'lastname' => $u->lastname,
				'email' => $u->email,
				'photo' => $photo,
				'speciality' => $u->speciality ?? null,
				'ratings_avg' => (float) ($u->ratings_avg ?? 0),
				'ratings_count' => (int) ($u->ratings_count ?? 0),
				'completed_count' => (int) $completedTotal,
				'completed_count_month' => (int) $completedThisMonth,
				'appointment_types' => $types,
				'location' => $u->location ?? null,
			];
		});

		return response()->json($result);
	}

	// Show a public-facing profile page for a professional
	public function show($id)
	{
		$u = User::find($id);
		if (!$u) abort(404);

		// derive avatar: prefer data URL accessor, otherwise build URL/data from stored path
		$avatar = null;
		try { $avatar = $u->profile_photo_data_url ?? null; } catch (\Throwable $_) { $avatar = null; }
		if (!$avatar) {
			try {
				$pf = $u->photos()->where('is_profile', true)->first();
				if ($pf) {
					if (!empty($pf->path) && \Illuminate\Support\Facades\Storage::disk('public')->exists($pf->path)) {
						$bytes = \Illuminate\Support\Facades\Storage::disk('public')->get($pf->path);
						if ($bytes) {
							$mime = null;
							try { $f = new \finfo(FILEINFO_MIME_TYPE); $mime = $f->buffer($bytes); } catch (\Throwable$_) { $mime = null; }
							if (!$mime) { $info = @getimagesizefromstring($bytes); if ($info && !empty($info['mime'])) $mime = $info['mime']; }
							if (!$mime) $mime = 'application/octet-stream';
							$avatar = 'data:'.$mime.';base64,'.base64_encode($bytes);
						}
					} elseif (!empty($pf->foto)) {
						$raw = $pf->foto; if (is_resource($raw)) { try{ if (ftell($raw)!==false) rewind($raw);}catch(\Throwable$_){} $bytes = @stream_get_contents($raw); } else { $bytes = $raw; }
						if ($bytes) {
							$mime = null;
							try { $f = new \finfo(FILEINFO_MIME_TYPE); $mime = $f->buffer($bytes); } catch (\Throwable$_) { $mime = null; }
							if (!$mime) { $info = @getimagesizefromstring($bytes); if ($info && !empty($info['mime'])) $mime = $info['mime']; }
							if (!$mime) $mime = 'application/octet-stream';
							$avatar = 'data:'.$mime.';base64,'.base64_encode($bytes);
						}
					}
				}
			} catch (\Throwable $_) { /* ignore */ }
		}

		return view('professionals.show', compact('u', 'avatar'));
	}

	// Public JSON list of latest public ratings (comments) for a professional
	public function publicRatings(Request $request, int $id)
	{
		$u = User::findOrFail($id);
		$limit = (int) min(50, max(1, (int)$request->query('limit', 10)));
		$ratings = \App\Models\AppointmentRating::query()
			->where('professional_id', $u->id)
			->where('is_public', true)
			->orderByDesc('created_at')
			->limit($limit)
			->get(['id','rating','comment','response_text','created_at']);
		$items = $ratings->map(function($r){
			return [
				'id' => $r->id,
				'score' => (int)$r->rating,
				'comment' => (string) ($r->comment ?? ''),
				'response' => (string) ($r->response_text ?? ''),
				'created_at' => optional($r->created_at)->toIso8601String(),
			];
		});
		return response()->json([
			'ok' => true,
			'professional_id' => $u->id,
			'ratings_avg' => (float) ($u->ratings_avg ?? 0),
			'ratings_count' => (int) ($u->ratings_count ?? 0),
			'items' => $items,
		]);
	}
}
