<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MenuItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'label',
        'route_name',
        'url',
        'icon_class',
        'section',
        'sort_order',
        'enabled',
        'permission',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function roles()
    {
        return $this->belongsToMany(\Spatie\Permission\Models\Role::class, 'menu_item_role', 'menu_item_id', 'role_id');
    }

    public function resolvedUrl(): string
    {
        $route = trim((string)($this->route_name ?? ''));
        if ($route !== '') {
            try { if (\Route::has($route)) { return route($route); } } catch (\Throwable $__) {}
        }
        $url = trim((string)($this->url ?? ''));
        if ($url !== '') return $url;
        return '#';
    }
}
