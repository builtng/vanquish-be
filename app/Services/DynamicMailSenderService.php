<?php

namespace App\Services;

use App\Models\EmailSenderSetting;
use Illuminate\Support\Facades\Cache;

class DynamicMailSenderService
{
    /**
     * Resolve the from email address and name based on category
     *
     * @param string $category
     * @return array
     */
    public function resolve(string $category): array
    {
        $setting = Cache::remember('email_sender_' . $category, 3600, function () use ($category) {
            return EmailSenderSetting::where('category', $category)->first();
        });

        if ($setting && filter_var($setting->from_email, FILTER_VALIDATE_EMAIL)) {
            return [
                'email' => $setting->from_email,
                'name' => $setting->from_name ?: config('mail.from.name'),
            ];
        }

        return [
            'email' => config('mail.from.address'),
            'name' => config('mail.from.name'),
        ];
    }
}
