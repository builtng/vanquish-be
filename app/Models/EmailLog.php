<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailLog extends Model
{
    protected $fillable = [
        'client_id',
        'submission_id',
        'email',
        'template_name',
        'payload',
        'status',
        'resend_message_id',
        'error_message',
        'sent_at'
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'payload' => 'array',
    ];

    protected $appends = [
        'recipient',
        'template',
        'error_text'
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function getRecipientAttribute(): string
    {
        return (string) $this->email;
    }

    public function getTemplateAttribute(): string
    {
        return (string) $this->template_name;
    }

    public function getErrorTextAttribute(): ?string
    {
        return $this->error_message;
    }
}
