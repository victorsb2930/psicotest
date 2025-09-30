<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserDevice extends Model
{
	use HasFactory;

	protected $table = 'user_devices';

	protected $fillable = ['user_id','token_hash','name','ip_address','user_agent','last_seen_at','revoked_at'];

	protected $dates = ['last_seen_at','revoked_at','created_at','updated_at'];

	public function user()
	{
		return $this->belongsTo(User::class);
	}

	/**
	 * Accessor: display_name - returns stored name or a friendly name derived from user agent
	 */
	public function getDisplayNameAttribute()
	{
		if (!empty($this->name)) return $this->name;
		return self::friendlyNameFromUserAgent($this->user_agent) ?: 'Sin nombre';
	}

	/**
	 * Derive a short, human-friendly device name from a User-Agent string.
	 * This is intentionally conservative and localized (Spanish) for display purposes.
	 */
	public static function friendlyNameFromUserAgent(?string $ua): ?string
	{
		if (empty($ua) || !is_string($ua)) return null;
		$s = strtolower($ua);
		$platform = $browser = null;
		// control arrays for matching (so = sistema operativo, br = browser)
		$ctrlSoAndBr =[
			// operating systems
			['so' => 'windows', 'descr' => 'Windows'],
			['so' => 'macintosh', 'descr' => 'Mac', 'ctrlContains' => ['mac os x']],
			['so' => 'android', 'descr' => 'Android'],
			['so' => 'iphone', 'descr' => 'iPhone/iPad', 'ctrlContains' => ['ipad']],
			['so' => 'ipad', 'descr' => 'iPhone/iPad'],
			['so' => 'linux', 'descr' => 'Linux'],
			['so' => 'cros', 'descr' => 'Chromebook'],
			// browsers
			['br' => 'edg/', 'descr' => 'Edge', 'ctrlContains' => ['edge']],
			['br' => 'opr/', 'descr' => 'Opera', 'ctrlContains' => ['opera']],
			['br' => 'chrome', 'descr' => 'Chrome', '!str_contains' => ['chromium', 'edg']],
			['br' => 'crios', 'descr' => 'Chrome (iOS)'],
			['br' => 'firefox', 'descr' => 'Firefox'],
			['br' => 'safari', 'descr' => 'Safari', '!str_contains' => ['chrome']]
		];

		foreach ($ctrlSoAndBr as $ctrlSo) {
			if (isset($ctrlSo['so'])) {
				if (str_contains($s, strtolower($ctrlSo['so']))) {
					$match = true;
					if (isset($ctrlSo['ctrlContains'])) {
						foreach ($ctrlSo['ctrlContains'] as $cc) {
							if (!str_contains($s, strtolower($cc))) {
								$match = false;
								break;
							}
						}
					}
					if (isset($ctrlSo['!str_contains'])) {
						foreach ($ctrlSo['!str_contains'] as $nsc) {
							if (str_contains($s, strtolower($nsc))) {
								$match = false;
								break;
							}
						}
					}
					if ($match) {
						$platform = $ctrlSo['descr'];
						break;
					}
				}
			}
		}
		foreach ($ctrlSoAndBr as $ctrlBr) {
			if (isset($ctrlBr['br'])) {
				if (str_contains($s, strtolower($ctrlBr['br']))) {
					$match = true;
					if (isset($ctrlBr['ctrlContains'])) {
						foreach ($ctrlBr['ctrlContains'] as $cc) {
							if (!str_contains($s, strtolower($cc))) {
								$match = false;
								break;
							}
						}
					}
					if (isset($ctrlBr['!str_contains'])) {
						foreach ($ctrlBr['!str_contains'] as $nsc) {
							if (str_contains($s, strtolower($nsc))) {
								$match = false;
								break;
							}
						}
					}
					if ($match) {
						$browser = $ctrlBr['descr'];
						break;
					}
				}
			}
		}
		if ($platform && $browser) {
			return $platform . ' - ' . $browser;
		} elseif ($platform) {
			return $platform;
		} elseif ($browser) {
			return $browser;
		}
		return null;
	}
}
