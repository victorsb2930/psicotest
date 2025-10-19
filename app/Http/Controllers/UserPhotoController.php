<?php

namespace App\Http\Controllers;

use App\Models\UserPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Intervention\Image\ImageManagerStatic as Image;
use PDO;

class UserPhotoController extends Controller
{
	public function index(Request $request)
	{
		$user = auth()->user();
		try {
			$photos = $user->photos()->orderBy('id', 'desc')->get()->map(function ($p) use ($user) {
				// ...existing sanitizers / dataUrl logic above ...
				$publicUrl = null;
				$secureUrl = null;
				try {
					if (!empty($p->path) && \Illuminate\Support\Facades\Storage::disk('public')->exists($p->path)) {
						try {
							// intentamos obtener URL pública via Storage::url
							$publicUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($p->path);
						} catch (\Throwable $e) {
							// fallback simple a /storage/...
							$publicUrl = url('/storage/' . ltrim($p->path, '/'));
							\Illuminate\Support\Facades\Log::warning('UserPhotoController: Storage::url failed, using fallback', ['path' => $p->path, 'err' => $e->getMessage()]);
						}
						// secure url only if route exists
						try {
							if (\Illuminate\Support\Facades\Route::has('secure.storage')) {
								$enc = rtrim(strtr(base64_encode($p->path), '+/', '-_'), '=');
								$secureUrl = route('secure.storage', ['encoded' => $enc]);
							}
						} catch (\Throwable $e) {
							\Illuminate\Support\Facades\Log::warning('UserPhotoController: building secure.url failed', ['path' => $p->path, 'err' => $e->getMessage()]);
							$secureUrl = null;
						}
					}
				} catch (\Throwable $e) {
					\Illuminate\Support\Facades\Log::error('UserPhotoController: error checking public path', ['path' => $p->path ?? null, 'err' => $e->getMessage()]);
				}

				// return detallado para frontend
				return [
					'id' => $p->id,
					'owner_id' => $p->user_id,
					'path' => $p->path,
					'caption' => $p->caption,
					'is_profile' => (bool) $p->is_profile,
					'created_at' => optional($p->created_at)->toDateTimeString(),
					'data_url' => $dataUrl ?? null,
					'url' => $publicUrl,
					'secure_url' => $secureUrl,
				];
			});

			return response()->json(['ok' => true, 'photos' => $photos]);
		} catch (\Throwable $e) {
			// log and return a safe error response
			logger()->error('UserPhotoController@index serialization error', ['err' => $e->getMessage(), 'user_id' => $user->id ?? null]);

			return response()->json(['ok' => false, 'message' => 'Error serializing photos'], 500);
		}
	}

	public function store(Request $request)
	{
		$request->validate(['photo' => ['required', 'image', 'max:5120']]);
		$user = auth()->user();
		$file = $request->file('photo');
		$contents = file_get_contents($file->getRealPath());

		// Prefer storing file on disk under storage/app/user_photos preserving original format
		$path = null;
		try {
			// try to determine extension from uploaded file or mime
			$ext = null;
			try { $ext = strtolower(pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION)); } catch (\Throwable$_) { $ext = null; }
			if (!$ext) {
				try { $f = new \finfo(FILEINFO_MIME_TYPE); $m = $f->buffer($contents); } catch (\Throwable$_) { $m = null; }
				if (!empty($m)) {
					$map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
					$ext = $map[$m] ?? null;
				}
			}
			if (!$ext) $ext = 'bin';
			// unified public path: user_photos/{user_id}/profile/...
			$fname = 'user_photos/' . $user->id . '/profile/' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
			\Illuminate\Support\Facades\Storage::disk('public')->put($fname, $contents);
			$path = $fname;
		} catch (\Throwable $e) {
			logger()->warning('Failed to write user photo to disk, falling back to DB blob', ['err' => $e->getMessage(), 'user_id' => $user->id ?? null]);
			$path = null;
		}

		if ($path) {
			$up = UserPhoto::create([
				'user_id' => $user->id,
				'path' => $path,
				'caption' => $request->input('caption'),
				'is_profile' => false,
			]);
		} else {
			// fallback to storing blob in foto column for backward compatibility
			$up = UserPhoto::create([
				'user_id' => $user->id,
				'foto' => $encoded,
				'caption' => $request->input('caption'),
				'is_profile' => false,
			]);
		}

		try {
			$safe = function ($v) {
				if (is_null($v)) {
					return null;
				} $s = (string) $v;
				$r = @iconv('UTF-8', 'UTF-8//IGNORE', $s);

				return $r === false ? '' : $r;
			};
			$dataUrl = null;
			if (isset($up->foto) && $up->foto !== null) {
				try {
					$raw = $up->foto;
					if (is_resource($raw)) {
						try {
							if (ftell($raw) !== false) {
								rewind($raw);
							}
						} catch (\Throwable$_) {
						}
						$bytes = @stream_get_contents($raw);
					} else {
						$bytes = $raw;
					}
					if ($bytes !== null && $bytes !== false && $bytes !== '') {
								$mime = null;
								try { $f = new \finfo(FILEINFO_MIME_TYPE); $mime = $f->buffer($bytes); } catch (\Throwable$_) { $mime = null; }
								if (!$mime) { $info = @getimagesizefromstring($bytes); if ($info && !empty($info['mime'])) $mime = $info['mime']; }
								if (!$mime) $mime = 'application/octet-stream';
								$dataUrl = 'data:'.$mime.';base64,'.base64_encode($bytes);
					}
				} catch (\Throwable$_) {
					$dataUrl = null;
				}
			}
			$photo = [
				'id' => $up->id,
				'caption' => $safe($up->caption),
				'is_profile' => (bool) $up->is_profile,
				'created_at' => optional($up->created_at)->toDateTimeString(),
				'data_url' => $dataUrl,
			];

			return response()->json(['ok' => true, 'photo' => $photo]);
		} catch (\Throwable $e) {
			logger()->error('UserPhotoController@store serialization error', ['err' => $e->getMessage(), 'user_id' => $user->id ?? null]);

			return response()->json(['ok' => false, 'message' => 'Error encoding photo response'], 500);
		}
	}

	public function setProfile(Request $request, UserPhoto $photo)
	{
		$user = auth()->user();
		if ($photo->user_id !== $user->id) {
			return response()->json(['ok' => false, 'message' => 'Foto no válida'], 403);
		}
		// clear previous
		UserPhoto::where('user_id', $user->id)->update(['is_profile' => false]);
		$photo->is_profile = true;
		$photo->save();

		// no further updates required; path is already stored if applicable
		return response()->json(['ok' => true]);
	}

	public function destroy(Request $request, UserPhoto $photo)
	{
		$user = auth()->user();
		if ($photo->user_id !== $user->id) {
			return response()->json(['ok' => false, 'message' => 'Foto no válida'], 403);
		}
		// If the photo had a filesystem path, remove the file
		try {
			if (!empty($photo->path) && \Illuminate\Support\Facades\Storage::disk('public')->exists($photo->path)) {
				\Illuminate\Support\Facades\Storage::disk('public')->delete($photo->path);
			}
		} catch (\Throwable $_) {}
		$photo->delete();

		return response()->json(['ok' => true]);
	}
}
