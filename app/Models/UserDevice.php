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
        $platform = null;
        if (str_contains($s, 'windows')) $platform = 'Windows';
        elseif (str_contains($s, 'macintosh') || str_contains($s, 'mac os x')) $platform = 'Mac';
        elseif (str_contains($s, 'android')) $platform = 'Android';
        elseif (str_contains($s, 'iphone') || str_contains($s, 'ipad')) $platform = 'iPhone/iPad';
        elseif (str_contains($s, 'linux')) $platform = 'Linux';
        elseif (str_contains($s, 'cros')) $platform = 'Chromebook';

        $browser = null;
        if (str_contains($s, 'edg/') || str_contains($s, 'edge')) $browser = 'Edge';
        elseif (str_contains($s, 'opr/') || str_contains($s, 'opera')) $browser = 'Opera';
        elseif (str_contains($s, 'chrome') && !str_contains($s, 'chromium') && !str_contains($s, 'edg')) $browser = 'Chrome';
        elseif (str_contains($s, 'crios')) $browser = 'Chrome (iOS)';
        elseif (str_contains($s, 'firefox')) $browser = 'Firefox';
        elseif (str_contains($s, 'safari') && !str_contains($s, 'chrome') && !str_contains($s, 'crios')) $browser = 'Safari';

        if ($browser && $platform) return $browser . ' en ' . $platform;
        if ($browser) return $browser;
        if ($platform) return $platform;
        return null;
    }
}
