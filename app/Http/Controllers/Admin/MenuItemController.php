<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Services\MenuService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class MenuItemController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAdmin($request);
        $q = MenuItem::query();
        $sections = MenuItem::query()->select('section')->distinct()->orderBy('section')->pluck('section');
        $search = trim((string)$request->input('q',''));
        $section = trim((string)$request->input('section',''));
        if ($search !== '') {
            $q->where(function($w) use ($search){
                $w->where('label','like',"%{$search}%")
                  ->orWhere('route_name','like',"%{$search}%")
                  ->orWhere('url','like',"%{$search}%");
            });
        }
        if ($section !== '') { $q->where('section', $section); }
        $items = $q->orderBy('section')->orderBy('sort_order')->paginate(20)->withQueryString();
        return view('admin.menuitems.index', compact('items','search','section','sections'));
    }

    public function create(Request $request)
    {
        $this->authorizeAdmin($request);
        $roles = Role::orderBy('name')->get(['id','name']);
        $sections = MenuItem::query()->select('section')->distinct()->orderBy('section')->pluck('section');
        $perms = Permission::orderBy('name')->get(['name']);
        $item = new MenuItem(['enabled' => true, 'sort_order' => 0, 'section' => '']);
        return view('admin.menuitems.create', compact('item','roles','perms','sections'));
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin($request);
        $data = $this->validateData($request);
        DB::transaction(function() use ($data, $request){
            $item = MenuItem::create($data);
            $roleIds = collect($request->input('role_ids', []))->map(fn($i)=>(int)$i)->filter()->values()->all();
            if (!empty($roleIds)) { $item->roles()->sync($roleIds); }
        });
        MenuService::bump();
        return redirect()->route('admin.menuitems.index')->with('success','Elemento creado');
    }

    public function edit(Request $request, MenuItem $menuItem)
    {
        $this->authorizeAdmin($request);
        $roles = Role::orderBy('name')->get(['id','name']);
        $sections = MenuItem::query()->select('section')->distinct()->orderBy('section')->pluck('section');
        $perms = Permission::orderBy('name')->get(['name']);
        $item = $menuItem;
        $selectedRoles = $item->roles()->pluck('id')->map(fn($i)=>(int)$i)->toArray();
        return view('admin.menuitems.edit', compact('item','roles','perms','sections','selectedRoles'));
    }

    public function update(Request $request, MenuItem $menuItem)
    {
        $this->authorizeAdmin($request);
        $data = $this->validateData($request);
        DB::transaction(function() use ($menuItem, $data, $request){
            $menuItem->update($data);
            $roleIds = collect($request->input('role_ids', []))->map(fn($i)=>(int)$i)->filter()->values()->all();
            $menuItem->roles()->sync($roleIds);
        });
        MenuService::bump();
        return redirect()->route('admin.menuitems.index')->with('success','Elemento actualizado');
    }

    public function destroy(Request $request, MenuItem $menuItem)
    {
        $this->authorizeAdmin($request);
        try { $menuItem->delete(); } catch (\Throwable $__) {}
        MenuService::bump();
        return redirect()->route('admin.menuitems.index')->with('success','Elemento eliminado');
    }

    public function toggle(Request $request, MenuItem $menuItem)
    {
        $this->authorizeAdmin($request);
        $menuItem->enabled = !$menuItem->enabled;
        try { $menuItem->save(); } catch (\Throwable $__) {}
        MenuService::bump();
        return redirect()->back();
    }

    private function authorizeAdmin(Request $request): void
    {
        $user = $request->user();
        if (!$user || !$user->can('adminarea')) { abort(403); }
    }

    private function validateData(Request $request): array
    {
        $data = $request->validate([
            'label' => ['required','string','max:100'],
            'route_name' => ['nullable','string','max:150'],
            'url' => ['nullable','string','max:300'],
            'icon_class' => ['nullable','string','max:150'],
            'section' => ['required','string','max:50'],
            'sort_order' => ['nullable','integer','min:0','max:100000'],
            'enabled' => ['sometimes','boolean'],
            'permission' => ['nullable','string','max:150','exists:permissions,name'],
        ]);
        $data['enabled'] = (bool) ($request->input('enabled', false));
        return $data;
    }
}
