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
			$photos = $user->photos()->orderBy('id', 'desc')->get()->map(function ($p) {
				// helper to sanitize strings for JSON encoding
				$safe = function ($v) {
					if (is_null($v)) {
						return null;
					} $s = (string) $v;
					$r = @iconv('UTF-8', 'UTF-8//IGNORE', $s);

					return $r === false ? '' : $r;
				};
				// ensure foto is a string before base64 encoding
				$dataUrl = null;
				try {
					$raw = $p->foto ?? null;
					if ($raw !== null) {
						// If DB driver returns a resource (stream), read it
						if (is_resource($raw)) {
							// rewind if possible then read
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
							$dataUrl = 'data:image/jpeg;base64,'.base64_encode($bytes);
						}
					}
				} catch (\Throwable$e) {
					$dataUrl = null;
				}

				return [
					'id' => $p->id,
					'caption' => $safe($p->caption),
					'is_profile' => (bool) $p->is_profile,
					'created_at' => optional($p->created_at)->toDateTimeString(),
					'data_url' => $dataUrl,
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

		// Use Intervention Image to re-encode uploaded file to JPEG (normalize bytes)
		$encoded = null;
		try {
			$img = Image::make($contents)->encode('jpg', 85);
			$encoded = (string) $img;
		} catch (\Throwable$e) {
			logger()->warning('Intervention failed to re-encode image, trying GD fallback', ['err' => $e->getMessage(), 'user_id' => $user->id ?? null]);
			// GD fallback
			try {
				$im = @imagecreatefromstring($contents);
				if ($im !== false) {
					ob_start();
					imagejpeg($im, null, 85);
					$jpeg = ob_get_clean();
					imagedestroy($im);
					if ($jpeg !== false && $jpeg !== null) {
						$encoded = $jpeg;
					}
				}
			} catch (\Throwable$_) {
				$encoded = null;
			}
		}

		if ($encoded === null) {
			logger()->warning('Falling back to original uploaded bytes for user photo (no re-encode available)', ['user_id' => $user->id ?? null]);
			$encoded = $contents;
		}

		// Some drivers (Postgres) require sending binary data as a LOB parameter.
		// Use a direct PDO prepared statement with PDO::PARAM_LOB for pgsql, otherwise use Eloquent.
		$up = null;
		try {
			$driver = DB::getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME);
		} catch (\Throwable$_) {
			$driver = null;
		}

		if ($driver === 'pgsql') {
			try {
				$pdo = DB::getPdo();
				$sql = 'INSERT INTO "user_photos" ("user_id","foto","caption","is_profile","created_at","updated_at") VALUES (:user_id, :foto, :caption, :is_profile, now(), now()) RETURNING id';
				$stmt = $pdo->prepare($sql);
				$stmt->bindValue(':user_id', $user->id, PDO::PARAM_INT);
				// bindValue with PARAM_LOB so pgsql receives a bytea parameter correctly
				$stmt->bindValue(':foto', $encoded, PDO::PARAM_LOB);
				$stmt->bindValue(':caption', $request->input('caption'), PDO::PARAM_STR);
				$stmt->bindValue(':is_profile', false, PDO::PARAM_BOOL);
				$stmt->execute();
				$newId = $stmt->fetchColumn();
				$up = $newId ? UserPhoto::find($newId) : null;
			} catch (\Throwable$e) {
				logger()->error('UserPhotoController@store DB LOB insert failed', ['err' => $e->getMessage(), 'user_id' => $user->id ?? null]);
				// fallback to Eloquent create if PDO route fails
				$up = UserPhoto::create([
					'user_id' => $user->id,
					'foto' => $encoded,
					'caption' => $request->input('caption'),
					'is_profile' => false,
				]);
			}
		} else {
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
						$dataUrl = 'data:image/jpeg;base64,'.base64_encode($bytes);
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

		// no filesystem path update; photo is in user_photos.foto
		return response()->json(['ok' => true]);
	}

	public function destroy(Request $request, UserPhoto $photo)
	{
		$user = auth()->user();
		if ($photo->user_id !== $user->id) {
			return response()->json(['ok' => false, 'message' => 'Foto no válida'], 403);
		}
		// photo binary stored in DB; simply delete record
		$photo->delete();

		return response()->json(['ok' => true]);
	}
}
