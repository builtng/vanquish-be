<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TcConductEvent extends Model
{
    protected $fillable = [
        'tc_id',
        'type',
        'notes',
        'logged_by_user_id',
        'email_sent',
    ];

    protected $casts = [
        'email_sent' => 'boolean',
    ];

    /**
     * The event types admins can log against a trainee counsellor, the email
     * template each maps to, and the copy shown in the admin UI.
     */
    const TYPES = [
        'late_to_session' => [
            'label' => 'Late to Session',
            'email_template' => 'tc_late_to_session',
            'description' => 'Sends an email notifying the trainee counsellor they were late to a session.',
        ],
        'missed_psg' => [
            'label' => 'Missed PSG',
            'email_template' => 'tc_missed_psg',
            'description' => 'Sends an email notifying the trainee counsellor they missed a Peer Support Group session.',
        ],
        'missed_session' => [
            'label' => 'Missed a Session',
            'email_template' => 'tc_missed_session',
            'description' => 'Sends an email notifying the trainee counsellor they missed a scheduled session.',
        ],
        'late_session_notes' => [
            'label' => 'Late Session Notes',
            'email_template' => 'tc_late_session_notes',
            'description' => 'Sends a reminder that session notes are pending completion.',
        ],
        'session_disruption' => [
            'label' => 'Disruption to Session (Internet/Device)',
            'email_template' => 'tc_session_disruption',
            'description' => 'Logs a technical disruption (internet/device) that affected a session.',
        ],
    ];

    public function trainingCounsellor(): BelongsTo
    {
        return $this->belongsTo(TrainingCounsellor::class, 'tc_id');
    }

    public function loggedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'logged_by_user_id');
    }
}
