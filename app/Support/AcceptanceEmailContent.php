<?php

namespace App\Support;

/**
 * Wording for the acceptance emails.
 *
 * - Both versions carry an "Accept" button ({{acceptance_link}}) that is
 *   valid for 72 hours ({{accept_by}}).
 * - Qualified counsellors are not on a placement, so their version never
 *   says "placement".
 */
class AcceptanceEmailContent
{
    public static function acceptBlock(string $buttonText = 'Accept &amp; Confirm Induction Attendance'): string
    {
        return '<div style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 12px; padding: 20px 24px; margin: 24px 0; text-align: center;">
    <p style="margin: 0 0 6px 0; font-size: 15px; font-weight: bold; color: #065f46;">Please confirm within 72 hours</p>
    <p style="margin: 0 0 16px 0; font-size: 13px; color: #047857;">Click the button below to accept and confirm you will attend the induction. This link expires on <strong>{{accept_by}}</strong>.</p>
    <a href="{{acceptance_link}}" target="_blank" style="display:inline-block;padding:12px 28px;background:#6f1d56;color:white;text-decoration:none;border-radius:8px;font-weight:bold;font-size:14px;">' . $buttonText . '</a>
</div>';
    }

    /** Adds the accept button to a trainee acceptance body that does not have one yet. */
    public static function withAcceptBlock(string $body): string
    {
        if (str_contains($body, '{{acceptance_link}}')) {
            return $body;
        }

        $marker = '<h2 style="color: #6f1d56; font-size: 17px; border-bottom: 2px solid #f0e6ed; padding-bottom: 8px; margin: 24px 0 16px 0;">Immediate Paperwork Requirements</h2>';
        if (str_contains($body, $marker)) {
            return str_replace($marker, self::acceptBlock() . "\n" . $marker, $body);
        }

        $pos = stripos($body, 'Immediate Paperwork');
        if ($pos !== false) {
            $tagStart = strrpos(substr($body, 0, $pos), '<');
            if ($tagStart !== false) {
                return substr($body, 0, $tagStart) . self::acceptBlock() . "\n" . substr($body, $tagStart);
            }
        }

        return $body . "\n" . self::acceptBlock();
    }

    public static function qcAcceptanceSubject(): string
    {
        return 'Welcome to Vanquish Therapies - Offer Acceptance';
    }

    public static function qcAcceptanceBody(): string
    {
        return '<h1 style="margin: 0 0 20px 0; color: #1e293b; font-size: 24px; font-weight: 700; line-height: 1.3;">Offer Acceptance</h1>
<p style="font-size: 16px; margin: 0 0 16px 0; color: #333333;">Dear {{first_name}},</p>
<p style="margin: 0 0 20px 0; color: #4b5563; font-size: 15px; line-height: 1.6;">Congratulations on your successful interview! We are delighted to welcome you to the team of qualified counsellors at <strong>Vanquish Therapies</strong>.</p>
<div style="background: #f8fafc; border-radius: 12px; padding: 24px; margin: 24px 0; border: 1px solid #e2e8f0;">
    <h2 style="color: #6f1d56; margin: 0 0 16px 0; font-size: 18px;">Mandatory Induction Details</h2>
    <p style="margin: 0 0 8px 0; font-size: 14px; color: #64748b;">Please attend our mandatory induction:</p>
    <p style="margin: 8px 0 0; font-size: 15px; font-weight: bold; color: #333333;"><strong>Date of Induction:</strong> {{induction_date}}</p>
    <p style="margin: 6px 0 0; font-size: 14px; color: #333333;"><strong>Platform:</strong> <a href="{{induction_zoom_link}}" style="color:#6f1d56;text-decoration:underline;">Zoom Link</a></p>
</div>
' . self::acceptBlock('Accept &amp; Confirm Induction Attendance') . '
<p style="font-size: 14px; color: #475569; margin: 16px 0;">We will be in touch shortly with your onboarding paperwork and access to the Practitioner Portal.</p>
<p style="font-size: 15px; font-weight: bold; color: #6f1d56; margin-top: 24px;">Warm congratulations once again, and welcome to the team!<br><br><span style="font-weight: normal; color: #777777; font-size: 13px;">The Compliance Team<br>Vanquish Therapies</span></p>';
    }

    public static function qcOnboardingSubject(): string
    {
        return 'Action Required: Your Qualified Counsellor Agreement';
    }

    /**
     * Qualified counsellors sign an agreement (sent via JotForm) but do not
     * complete the personal therapy form or the trainee 4-way agreement
     * (VQT, 22 Nov). Admins can edit this in Settings > Email Management,
     * e.g. to add the JotForm link.
     */
    public static function qcOnboardingBody(): string
    {
        return '<div style="font-family: Arial, sans-serif; max-width: 640px; margin: 0 auto; color: #333;">
  <div style="background: linear-gradient(135deg, #6f1d56 0%, #9b2c7e 100%); padding: 40px 32px; border-radius: 12px 12px 0 0; text-align: center;">
    <h1 style="color: white; margin: 0; font-size: 26px;">Action Required</h1>
    <p style="color: rgba(255,255,255,0.8); margin: 8px 0 0; font-size: 15px;">Complete Your Onboarding — Vanquish Therapies</p>
  </div>
  <div style="background: #ffffff; padding: 32px; border: 1px solid #e2e8f0; border-top: none;">
    <p style="font-size: 16px; margin-top: 0;">Dear <strong>{{first_name}}</strong>,</p>
    <p>To finalise your onboarding, please sign your <strong>Qualified Counsellor Agreement</strong> before your induction date.</p>
    <div style="padding: 16px; background: #fffbeb; border: 1px solid #fef3c7; border-radius: 8px; margin: 20px 0;">
      <p style="margin: 0; font-size: 14px;"><strong>Qualified Counsellor Agreement</strong></p>
      <p style="margin: 4px 0 0; font-size: 13px; color: #92400e;">We will send you the agreement to sign online. Please complete it as soon as you receive it.</p>
    </div>
    <p style="font-size: 14px; color: #475569;">If you have any questions, just reply to this email.</p>
    <p style="font-size: 15px; font-weight: bold; color: #6f1d56; margin-top: 32px;">Best regards,<br><span style="font-weight: normal; color: #777; font-size: 13px;">Compliance &amp; Clinical Lead</span></p>
  </div>
</div>';
    }

    /** Removes "placement" wording from text written for trainees. */
    public static function qcWording(string $text): string
    {
        return str_ireplace(
            ['onto our placement programme', 'placement programme', 'Placement Acceptance', 'Placement Rejection', 'finalise your placement', 'finalize your placement', 'your placement', 'Trainee Placement', 'placement'],
            ['to our team of qualified counsellors', 'team of qualified counsellors', 'Offer Acceptance', 'Application Outcome', 'finalise your onboarding', 'finalize your onboarding', 'your onboarding', 'Qualified Counsellor', 'role'],
            $text
        );
    }

    /**
     * Apply to a defaults array that already holds the qc_* copies of the
     * trainee templates.
     */
    public static function applyQcDefaults(array $defaults): array
    {
        $acceptPlaceholders = ['first_name', 'induction_date', 'induction_zoom_link', 'acceptance_link', 'accept_by'];

        if (isset($defaults['trainee_placement_acceptance'])) {
            $defaults['trainee_placement_acceptance']['body'] = self::withAcceptBlock($defaults['trainee_placement_acceptance']['body']);
            $defaults['trainee_placement_acceptance']['placeholders'] = array_values(array_unique(array_merge(
                $defaults['trainee_placement_acceptance']['placeholders'] ?? [],
                ['acceptance_link', 'accept_by']
            )));
        }

        $defaults['qc_placement_acceptance'] = [
            'subject' => self::qcAcceptanceSubject(),
            'body' => self::qcAcceptanceBody(),
            'placeholders' => $acceptPlaceholders,
        ];

        // Own wording: no personal therapy form, no trainee 4-way agreement.
        $defaults['qc_onboarding_paperwork'] = [
            'subject' => self::qcOnboardingSubject(),
            'body' => self::qcOnboardingBody(),
            'placeholders' => ['first_name'],
        ];

        foreach (['qc_placement_rejection'] as $type) {
            if (isset($defaults[$type])) {
                $defaults[$type]['subject'] = self::qcWording($defaults[$type]['subject']);
                $defaults[$type]['body'] = self::qcWording($defaults[$type]['body']);
            }
        }

        return $defaults;
    }
}
