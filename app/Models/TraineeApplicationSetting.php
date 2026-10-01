<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TraineeApplicationSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    protected $casts = [
        'value' => 'array',
    ];

    public static function getByKey($key, $default = null)
    {
        $setting = self::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    public static function setByKey($key, $value)
    {
        return self::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
