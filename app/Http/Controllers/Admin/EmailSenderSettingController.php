<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailSenderSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class EmailSenderSettingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $settings = EmailSenderSetting::orderBy('category')->get();
        return response()->json($settings);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'category' => 'required|string|unique:email_sender_settings,category',
            'from_email' => 'required|email',
            'from_name' => 'nullable|string',
        ]);

        $setting = EmailSenderSetting::create($validated);

        Cache::forget('email_sender_' . $setting->category);

        return response()->json([
            'message' => 'Email sender setting created successfully',
            'setting' => $setting
        ], 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $setting = EmailSenderSetting::findOrFail($id);

        $validated = $request->validate([
            'category' => 'required|string|unique:email_sender_settings,category,' . $setting->id,
            'from_email' => 'required|email',
            'from_name' => 'nullable|string',
        ]);

        // Clear old category cache if category changed
        if ($setting->category !== $validated['category']) {
            Cache::forget('email_sender_' . $setting->category);
        }

        $setting->update($validated);

        // Clear new category cache
        Cache::forget('email_sender_' . $setting->category);

        return response()->json([
            'message' => 'Email sender setting updated successfully',
            'setting' => $setting
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $setting = EmailSenderSetting::findOrFail($id);

        Cache::forget('email_sender_' . $setting->category);

        $setting->delete();

        return response()->json([
            'message' => 'Email sender setting deleted successfully'
        ]);
    }
}
