<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MatchingAlgorithmSetting;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MatchingAlgorithmSettingController extends Controller
{
    /**
     * Get the current matching algorithm weights.
     */
    public function index()
    {
        return response()->json($this->getSettings());
    }

    /**
     * Update the matching algorithm weights (admin only).
     * The six weights must sum to exactly 100.
     */
    public function update(Request $request)
    {
        $data = $request->validate([
            'availability_weight'       => 'required|numeric|min:0|max:100',
            'modality_weight'           => 'required|numeric|min:0|max:100',
            'gender_weight'             => 'required|numeric|min:0|max:100',
            'ethnicity_weight'          => 'required|numeric|min:0|max:100',
            'sexual_orientation_weight' => 'required|numeric|min:0|max:100',
            'age_weight'                => 'required|numeric|min:0|max:100',
        ]);

        $sum = round(array_sum($data), 2);
        if ($sum !== 100.0) {
            throw ValidationException::withMessages([
                'weights' => ["The six weights must add up to 100% (currently {$sum}%)."],
            ]);
        }

        $settings = $this->getSettings();
        $settings->fill($data);
        $settings->save();

        return response()->json([
            'message'  => 'Matching algorithm settings updated',
            'settings' => $settings,
        ]);
    }

    private function getSettings(): MatchingAlgorithmSetting
    {
        $settings = MatchingAlgorithmSetting::first();

        if (! $settings) {
            $settings = MatchingAlgorithmSetting::create([
                'availability_weight'       => 40,
                'modality_weight'           => 20,
                'gender_weight'             => 20,
                'ethnicity_weight'          => 10,
                'sexual_orientation_weight' => 5,
                'age_weight'                => 5,
            ]);
        }

        return $settings;
    }
}
