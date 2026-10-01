<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MatchingAlgorithmSetting extends Model
{
    protected $fillable = [
        'availability_weight',
        'modality_weight',
        'gender_weight',
        'ethnicity_weight',
        'sexual_orientation_weight',
        'age_weight',
    ];

    protected $casts = [
        'availability_weight'       => 'float',
        'modality_weight'           => 'float',
        'gender_weight'             => 'float',
        'ethnicity_weight'          => 'float',
        'sexual_orientation_weight' => 'float',
        'age_weight'                => 'float',
    ];
}
