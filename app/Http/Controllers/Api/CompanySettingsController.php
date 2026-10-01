<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CompanySettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CompanySettingsController extends Controller
{
    /**
     * Get all company settings as a key => value map.
     * Public: returns only non-sensitive settings.
     */
    public function index()
    {
        return response()->json(CompanySettingsService::getForPdf());
    }

    /**
     * Update one or more company settings (admin only).
     */
    public function update(Request $request)
    {
        $allowed = [
            'company_name', 'company_tagline', 'company_email',
            'company_phone', 'company_address', 'company_website',
            'consultation_zoom_link', 'consultation_meeting_id',
            'consultation_passcode', 'consultation_duration_minutes',
            'pdf_header_text', 'pdf_footer_text',
            'jotform_session_notes_url', 'jotform_psg_form_url',
            'jotform_agreement_url', 'jotform_intake_form_url',
            'jotform_trainee_application_url', 'jotform_therapy_form_url',
            'use_internal_session_notes', 'use_internal_psg_form',
            'use_internal_agreement_form', 'use_internal_intake_form',
            'use_internal_trainee_application',
        ];

        $data = $request->validate([
            'company_name'              => 'sometimes|string|max:200',
            'company_tagline'           => 'sometimes|string|max:300',
            'company_email'             => 'sometimes|email|max:200|nullable',
            'company_phone'             => 'sometimes|string|max:50|nullable',
            'company_address'           => 'sometimes|string|max:500|nullable',
            'company_website'           => 'sometimes|url|max:300|nullable',
            'consultation_zoom_link'    => 'sometimes|url|max:500|nullable',
            'consultation_meeting_id'   => 'sometimes|string|max:100|nullable',
            'consultation_passcode'     => 'sometimes|string|max:100|nullable',
            'consultation_duration_minutes' => 'sometimes|integer|min:1|max:600|nullable',
            'pdf_header_text'           => 'sometimes|string|max:300',
            'pdf_footer_text'           => 'sometimes|string|max:300',
            'jotform_session_notes_url' => 'sometimes|url|max:500|nullable',
            'jotform_psg_form_url'      => 'sometimes|url|max:500|nullable',
            'jotform_agreement_url'     => 'sometimes|url|max:500|nullable',
            'jotform_intake_form_url'    => 'sometimes|url|max:500|nullable',
            'jotform_trainee_application_url' => 'sometimes|url|max:500|nullable',
            'jotform_therapy_form_url'   => 'sometimes|url|max:500|nullable',
            'use_internal_session_notes' => 'sometimes|string|in:0,1',
            'use_internal_psg_form'      => 'sometimes|string|in:0,1',
            'use_internal_agreement_form' => 'sometimes|string|in:0,1',
            'use_internal_intake_form'   => 'sometimes|string|in:0,1',
            'use_internal_trainee_application' => 'sometimes|string|in:0,1',
        ]);

        foreach ($data as $key => $value) {
            DB::table('company_settings')
                ->updateOrInsert(
                    ['key' => $key],
                    ['value' => $value, 'updated_at' => now()]
                );
        }

        return response()->json(['message' => 'Settings updated', 'settings' => $this->getMap()]);
    }

    /**
     * Upload platform logo (admin only).
     * Accepts 'logo' (light) or 'logo_dark'.
     */
    public function uploadLogo(Request $request)
    {
        $request->validate([
            'logo'      => 'sometimes|image|mimes:png,jpg,jpeg,svg,webp|max:2048',
            'logo_dark' => 'sometimes|image|mimes:png,jpg,jpeg,svg,webp|max:2048',
        ]);

        $updated = [];

        if ($request->hasFile('logo')) {
            // Delete old logo if exists
            $old = DB::table('company_settings')->where('key', 'platform_logo_url')->value('value');
            if ($old) $this->deleteOldLogo($old);

            $path = $request->file('logo')->store('company', 'public');
            $url  = asset('storage/' . $path);

            DB::table('company_settings')->updateOrInsert(
                ['key' => 'platform_logo_url'],
                ['value' => $url, 'updated_at' => now()]
            );
            $updated['platform_logo_url'] = $url;
        }

        if ($request->hasFile('logo_dark')) {
            $old = DB::table('company_settings')->where('key', 'platform_logo_dark_url')->value('value');
            if ($old) $this->deleteOldLogo($old);

            $path = $request->file('logo_dark')->store('company', 'public');
            $url  = asset('storage/' . $path);

            DB::table('company_settings')->updateOrInsert(
                ['key' => 'platform_logo_dark_url'],
                ['value' => $url, 'updated_at' => now()]
            );
            $updated['platform_logo_dark_url'] = $url;
        }

        return response()->json(['message' => 'Logo uploaded', 'urls' => $updated]);
    }

    /**
     * Delete a logo (admin only).
     */
    public function deleteLogo(Request $request)
    {
        $request->validate(['type' => 'required|in:logo,logo_dark']);

        $key = $request->type === 'logo' ? 'platform_logo_url' : 'platform_logo_dark_url';
        $url = DB::table('company_settings')->where('key', $key)->value('value');

        if ($url) {
            $this->deleteOldLogo($url);
            DB::table('company_settings')
                ->where('key', $key)
                ->update(['value' => '', 'updated_at' => now()]);
        }

        return response()->json(['message' => 'Logo removed']);
    }

    /**
     * Get the status of the 4-Way Agreement document (admin only).
     */
    public function getFourWayAgreementStatus()
    {
        $filePath = storage_path('app/templates/4-way-agreement-trainee.docx');
        $exists = file_exists($filePath);
        $isPlaceholder = false;
        $size = null;
        $uploadedAt = null;

        if ($exists) {
            $content = file_get_contents($filePath);
            $isPlaceholder = str_contains($content, 'Placeholder for 4-way-agreement-trainee');
            $size = filesize($filePath);
            $uploadedAt = date('Y-m-d H:i:s', filemtime($filePath));
        }

        return response()->json([
            'exists'        => $exists,
            'is_placeholder'=> $isPlaceholder,
            'is_real'       => $exists && !$isPlaceholder,
            'size'          => $size,
            'uploaded_at'   => $uploadedAt,
            'filename'      => '4-way-agreement-trainee.docx',
        ]);
    }

    /**
     * Upload the 4-Way Agreement document (admin only).
     * Accepts .docx or .pdf and stores to storage/app/templates/
     */
    public function uploadFourWayAgreement(Request $request)
    {
        $request->validate([
            'document' => 'required|file|mimes:docx,pdf,doc|max:10240', // 10 MB max
        ]);

        $directory = storage_path('app/templates');
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $file = $request->file('document');
        $destPath = $directory . '/4-way-agreement-trainee.docx';

        // Move the uploaded file to the templates directory
        $file->move($directory, '4-way-agreement-trainee.docx');

        return response()->json([
            'message'     => '4-Way Agreement document uploaded successfully.',
            'filename'    => '4-way-agreement-trainee.docx',
            'size'        => file_exists($destPath) ? filesize($destPath) : null,
            'uploaded_at' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Delete the 4-Way Agreement document (admin only).
     * Removes the real file; the next email send will auto-create a placeholder.
     */
    public function deleteFourWayAgreement()
    {
        $filePath = storage_path('app/templates/4-way-agreement-trainee.docx');

        if (file_exists($filePath)) {
            unlink($filePath);
        }

        return response()->json(['message' => 'Document removed. A placeholder will be used until a new document is uploaded.']);
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function getMap(): array
    {
        return CompanySettingsService::getForPdf();
    }

    private function deleteOldLogo(string $url): void
    {
        try {
            // Convert URL back to storage path
            $storageBase = asset('storage/');
            if (str_starts_with($url, $storageBase)) {
                $relativePath = str_replace($storageBase, '', $url);
                Storage::disk('public')->delete($relativePath);
            }
        } catch (\Throwable) {
            // Silently ignore — old file may not exist
        }
    }
}
