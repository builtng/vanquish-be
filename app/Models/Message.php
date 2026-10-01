<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = [
        'from_user_id',
        'to_tc_id',
        'to_user_id',
        'subject',
        'message',
        'type',
        'is_read',
        'read_at',
        'related_client_id',
        'related_consultation_id',
        'is_trashed',
        'trashed_at',
        'cc_users',
        'attachment_path',
        'attachment_name',
        'attachment_type',
        'reply_to_id',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'is_trashed' => 'boolean',
        'trashed_at' => 'datetime',
        'cc_users' => 'array',
    ];

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function toTrainingCounsellor(): BelongsTo
    {
        return $this->belongsTo(TrainingCounsellor::class, 'to_tc_id');
    }

    public function relatedClient(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'related_client_id');
    }

    public function relatedConsultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class, 'related_consultation_id');
    }

    public function parentMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'reply_to_id');
    }

    public function replies()
    {
        return $this->hasMany(Message::class, 'reply_to_id');
    }
}


