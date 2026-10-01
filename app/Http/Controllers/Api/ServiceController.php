<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ServiceSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ServiceController extends Controller
{
    public function checkCoachingCapacity()
    {
        try {
            $setting = ServiceSetting::where('service_name', 'Counselling & Coaching')->first();
            $companySettings = \Illuminate\Support\Facades\DB::table('company_settings')->pluck('value', 'key')->toArray();
            $useInternalIntake = ($companySettings['use_internal_intake_form'] ?? '0') === '1';

            $defaultMessage = "This service is at capacity at this time. If you would like to work with us, you can click here to proceed with our Partner service VQT COACHING & THERAPY";
            
            if ($useInternalIntake) {
                $defaultUrl = rtrim(config('app.frontend_url'), '/') . "/clform";
            } else {
                $defaultUrl = $companySettings['jotform_intake_form_url'] ?? "https://pci.jotform.com/form/243161740962456";
            }

            return response()->json([
                'capacity_full' => $setting ? (bool) $setting->capacity_full : false,
                'message' => $setting && $setting->capacity_message
                    ? $setting->capacity_message
                    : $defaultMessage,
                'alternative_url' => $setting && $setting->alternative_url
                    ? $setting->alternative_url
                    : $defaultUrl,
            ]);
        } catch (\Exception $e) {
            Log::error('Error checking Ish capacity: ' . $e->getMessage());
            return response()->json([
                'capacity_full' => false,
                'message' => "This service is at capacity at this time. If you would like to work with us, you can click here to proceed with our Partner service VQT COACHING & THERAPY",
                'alternative_url' => "https://pci.jotform.com/form/243161740962456",
            ], 200);
        }
    }

    /**
     * Update service capacity and prices (admin only)
     */
    public function updateCapacity(Request $request)
    {
        try {
            $validated = $request->validate([
                'service_name'      => 'required|string',
                'capacity_full'     => 'sometimes|boolean',
                'capacity_message'  => 'nullable|string',
                'alternative_url'   => 'nullable|string',
                'consultation_price' => 'nullable|numeric|min:0',
                'session_price'     => 'nullable|numeric|min:0',
                'block_price'       => 'nullable|numeric|min:0',
                'booking_enabled'   => 'sometimes|boolean',
            ]);

            $updateData = [];
            if (array_key_exists('capacity_full', $validated))
                $updateData['capacity_full']      = (bool) $validated['capacity_full'];
            if (array_key_exists('capacity_message', $validated))
                $updateData['capacity_message']   = $validated['capacity_message'];
            if (array_key_exists('alternative_url', $validated))
                $updateData['alternative_url']    = $validated['alternative_url'];
            if (array_key_exists('consultation_price', $validated))
                $updateData['consultation_price'] = $validated['consultation_price'] ?? 13.00;
            if (array_key_exists('session_price', $validated))
                $updateData['session_price']      = $validated['session_price'] ?? 0;
            if (array_key_exists('block_price', $validated))
                $updateData['block_price']        = $validated['block_price'] ?? 0;
            if (array_key_exists('booking_enabled', $validated))
                $updateData['booking_enabled']    = (bool) $validated['booking_enabled'];

            $setting = ServiceSetting::updateOrCreate(
                ['service_name' => $validated['service_name']],
                $updateData
            );

            return response()->json($setting);
        } catch (\Exception $e) {
            Log::error('Error updating service setting: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to update service setting',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Update service price specifically (admin only alternative)
     */
    public function updatePrice(Request $request)
    {
        try {
            $validated = $request->validate([
                'service_name' => 'required|string',
                'consultation_price' => 'nullable|numeric|min:0',
                'session_price' => 'nullable|numeric|min:0',
                'block_price' => 'nullable|numeric|min:0',
            ]);

            $setting = ServiceSetting::updateOrCreate(
                ['service_name' => $validated['service_name']],
                [
                    'consultation_price' => $validated['consultation_price'] ?? 13.00,
                    'session_price' => $validated['session_price'] ?? 0,
                    'block_price' => $validated['block_price'] ?? 0,
                ]
            );

            return response()->json([
                'message' => 'Price updated successfully',
                'service' => $setting
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating service price: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to update service price',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Check global maintenance mode status
     */
    public function checkMaintenance()
    {
        try {
            $setting = ServiceSetting::where('service_name', 'Maintenance')->first();

            return response()->json([
                'maintenance_mode' => $setting ? (bool) $setting->capacity_full : false,
                'message' => $setting && $setting->capacity_message
                    ? $setting->capacity_message
                    : "The system is currently undergoing maintenance. Registration and intake are temporarily disabled.",
            ]);
        } catch (\Exception $e) {
            Log::error('Error checking maintenance mode: ' . $e->getMessage());
            return response()->json([
                'maintenance_mode' => false,
                'message' => "The system is currently undergoing maintenance.",
            ], 200);
        }
    }

    /**
     * Update global maintenance mode (admin only)
     */
    public function updateMaintenance(Request $request)
    {
        try {
            $validated = $request->validate([
                'maintenance_mode' => 'required|boolean',
                'message'          => 'nullable|string',
            ]);

            $setting = ServiceSetting::updateOrCreate(
                ['service_name' => 'Maintenance'],
                [
                    'capacity_full'    => $validated['maintenance_mode'],
                    'capacity_message' => $validated['message'] ?? "The system is currently undergoing maintenance. Registration and intake are temporarily disabled.",
                ]
            );

            return response()->json([
                'maintenance_mode' => (bool) $setting->capacity_full,
                'message'          => $setting->capacity_message,
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating maintenance mode: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to update maintenance mode',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get all services (admin only)
     */
    public function getAllServices()
    {
        try {
            $services = ServiceSetting::where('service_name', '!=', 'Maintenance')
                ->orderBy('id', 'desc')->get();
            return response()->json($services);
        } catch (\Exception $e) {
            Log::error('Error getting all services: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to get services',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
