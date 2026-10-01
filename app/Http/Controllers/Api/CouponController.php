<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    /**
     * Display a listing of coupons.
     */
    public function index()
    {
        $coupons = \App\Models\Coupon::orderBy('id', 'desc')->get();
        return response()->json($coupons);
    }

    /**
     * Store a newly created coupon.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:coupons,code|max:255',
            'type' => 'required|in:fixed,percent',
            'value' => 'required|numeric|min:0',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after:starts_at',
            'usage_limit' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
        ]);

        $validated['code'] = strtoupper(trim($validated['code'])); // Force uppercase and trim

        $coupon = \App\Models\Coupon::create($validated);

        return response()->json($coupon, 201);
    }

    /**
     * Update the specified coupon.
     */
    public function update(Request $request, $id)
    {
        $coupon = \App\Models\Coupon::findOrFail($id);

        $validated = $request->validate([
            'code' => 'required|string|max:255|unique:coupons,code,' . $id,
            'type' => 'required|in:fixed,percent',
            'value' => 'required|numeric|min:0',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after:starts_at',
            'usage_limit' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
        ]);

        $validated['code'] = strtoupper(trim($validated['code'])); // Force uppercase and trim

        $coupon->update($validated);

        return response()->json($coupon);
    }

    /**
     * Remove the specified coupon.
     */
    public function destroy($id)
    {
        $coupon = \App\Models\Coupon::findOrFail($id);
        $coupon->delete();

        return response()->json(['message' => 'Coupon deleted successfully']);
    }

    /**
     * Verify a coupon code (Public).
     */
    public function verify(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        $code = strtoupper(trim($request->code));
        $coupon = \App\Models\Coupon::whereRaw('UPPER(code) = ?', [$code])->first();

        if (!$coupon) {
            return response()->json(['message' => 'Invalid discount code'], 404);
        }

        if (!$coupon->isValid()) {
            return response()->json(['message' => 'This discount code is expired or inactive'], 400);
        }

        return response()->json($coupon);
    }
}
