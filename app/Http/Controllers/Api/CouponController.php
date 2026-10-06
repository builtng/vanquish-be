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
        if ($request->has('code')) {
            $request->merge(['code' => strtoupper(trim((string)$request->code))]);
        }

        $validated = $request->validate([
            'code' => 'required|string|unique:coupons,code|max:255',
            'type' => 'required|in:fixed,percent',
            'value' => 'required|numeric|min:0',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after:starts_at',
            'usage_limit' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
        ]);

        $validated['code'] = strtoupper(trim($validated['code']));

        $coupon = \App\Models\Coupon::create($validated);

        return response()->json($coupon, 201);
    }

    /**
     * Update the specified coupon.
     */
    public function update(Request $request, $id)
    {
        $coupon = \App\Models\Coupon::findOrFail($id);

        if ($request->has('code')) {
            $request->merge(['code' => strtoupper(trim((string)$request->code))]);
        }

        $validated = $request->validate([
            'code' => 'required|string|max:255|unique:coupons,code,' . $id,
            'type' => 'required|in:fixed,percent',
            'value' => 'required|numeric|min:0',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after:starts_at',
            'usage_limit' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
        ]);

        $validated['code'] = strtoupper(trim($validated['code']));

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
     *
     * Prompt 9:
     * - Case-insensitive and trimmed ("FREE", "Free", " free " all work)
     * - Inactive or expired or invalid code shows:
     *   "This code is not valid. Please check it or continue to payment."
     */
    public function verify(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        $code = strtoupper(trim((string)$request->code));
        $coupon = \App\Models\Coupon::whereRaw('UPPER(TRIM(code)) = ?', [$code])->first();

        if (!$coupon) {
            return response()->json([
                'message' => 'This code is not valid. Please check it or continue to payment.'
            ], 404);
        }

        if (!$coupon->isValid()) {
            return response()->json([
                'message' => 'This code is not valid. Please check it or continue to payment.'
            ], 422);
        }

        return response()->json($coupon);
    }
}
