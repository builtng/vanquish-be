<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SharedDocument extends Model
{
    protected $fillable = [
        'folder_id',
        'client_id',
        'tc_id',
        'category',
        'name',
        'description',
        'file_path',
        'file_type',
        'file_size',
        'uploaded_by',
        'is_active',
        'is_pinned'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_pinned' => 'boolean',
    ];

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function tc(): BelongsTo
    {
        return $this->belongsTo(TrainingCounsellor::class, 'tc_id');
    }
}
