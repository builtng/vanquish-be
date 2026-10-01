<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MenuPrivilege;
use Illuminate\Http\Request;

class MenuPrivilegeController extends Controller
{
    /**
     * Get all menu privileges
     */
    public function index()
    {
        try {
            return response()->json(MenuPrivilege::all());
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('MenuPrivilege Index Error: ' . $e->getMessage());
            return response()->json(['message' => 'Internal server error'], 500);
        }
    }

    /**
     * Update menu privilege for a specific menu item
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'menu_id' => 'required|string|exists:menu_privileges,menu_id',
            'roles' => 'present|array',
            'roles.*' => 'in:admin,staff,counsellor,consultation_staff,compliance_officer',
        ]);

        $privilege = MenuPrivilege::where('menu_id', $validated['menu_id'])->first();
        $privilege->update(['roles' => $validated['roles']]);

        return response()->json($privilege);
    }

    /**
     * Get menu privileges for a specific role
     */
    public function getForRole(string $role)
    {
        if (!in_array($role, ['admin', 'super_admin', 'staff', 'counsellor', 'consultation_staff', 'compliance_officer'])) {
            return response()->json([]);
        }

        $checkRole = $role === 'super_admin' ? 'admin' : $role;
        $privileges = MenuPrivilege::all()->filter(function ($p) use ($checkRole) {
            return is_array($p->roles) && in_array($checkRole, $p->roles);
        })->pluck('menu_id')->values();

        return response()->json($privileges);
    }
}
