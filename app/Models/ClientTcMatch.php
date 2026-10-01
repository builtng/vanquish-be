<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientTcMatch extends Model
{
    protected $fillable = [
        'client_id',
        'tc_id',
        'matched_by',
        'service_type',
        'match_score',
        'match_breakdown',
        'flags',
        'assignment_notes',
        'status',
        'assigned_date',
        'agreement_signed_date',
        'start_date',
        'end_date',
        'send_notification',
    ];

    protected $casts = [
        'assigned_date' => 'date',
        'agreement_signed_date' => 'date',
        'start_date' => 'date',
        'end_date' => 'date',
        'send_notification' => 'boolean',
        'match_breakdown' => 'array',
        'flags' => 'array',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function tc(): BelongsTo
    {
        return $this->belongsTo(TrainingCounsellor::class, 'tc_id');
    }

    public function matchedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'matched_by');
    }
}
