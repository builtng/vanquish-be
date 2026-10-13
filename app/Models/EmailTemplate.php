<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    protected $fillable = [
        'type',
        'subject',
        'body',
        'placeholders',
        'version',
    ];

    protected $casts = [
        'placeholders' => 'array',
    ];

    protected $appends = ['name'];

    public function getNameAttribute(): string
    {
        $names = [
            'trainee_stage_three_invite' => 'Trainee: Interview Invitation',
            'qc_stage_three_invite' => 'Qualified Counsellor: Interview Invitation',
            'trainee_interview_confirmed' => 'Trainee: Interview Confirmed',
            'qc_interview_confirmed' => 'Qualified Counsellor: Interview Confirmed',
            'trainee_interview_reminder' => 'Trainee: Interview Reminder',
            'qc_interview_reminder' => 'Qualified Counsellor: Interview Reminder',
            'trainee_interview_not_attended' => 'Trainee: Interview Not Attended',
            'qc_interview_not_attended' => 'Qualified Counsellor: Interview Not Attended',
            'trainee_placement_acceptance' => 'Trainee: Placement Acceptance',
            'qc_placement_acceptance' => 'Qualified Counsellor: Placement Acceptance',
            'trainee_onboarding_paperwork' => 'Trainee: Onboarding Paperwork',
            'qc_onboarding_paperwork' => 'Qualified Counsellor: Onboarding Paperwork',
            'trainee_placement_rejection' => 'Trainee: Placement Rejection',
            'qc_placement_rejection' => 'Qualified Counsellor: Placement Rejection',
            'trainee_induction_completed' => 'Trainee: Induction Completed',
            'qc_induction_completed' => 'Qualified Counsellor: Induction Completed',
            'trainee_induction_not_attended' => 'Trainee: Induction Not Attended',
            'qc_induction_not_attended' => 'Qualified Counsellor: Induction Not Attended',
            'trainee_initial_invite' => 'Trainee: Initial Invitation',
            'trainee_application_received' => 'Trainee: Application Received',
            'trainee_stage_two_invite' => 'Trainee: Stage Two Invite',
            'trainee_video_interview_received' => 'Trainee: Video Interview Received',
            'trainee_portal_invite' => 'Trainee: Portal Invite',
            'qualified_counsellor_submission' => 'Qualified Counsellor: Application Received',
        ];

        if (isset($names[$this->type])) {
            return $names[$this->type];
        }

        if (str_starts_with($this->type, 'qc_')) {
            return 'Qualified Counsellor: ' . ucwords(str_replace('_', ' ', substr($this->type, 3)));
        }

        if (str_starts_with($this->type, 'trainee_')) {
            return 'Trainee: ' . ucwords(str_replace('_', ' ', substr($this->type, 8)));
        }

        return ucwords(str_replace('_', ' ', $this->type));
    }
}

