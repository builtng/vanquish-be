<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FolderShare extends Model
{
    protected $fillable = [
        'folder_id',
        'target_type',
        'target_id'
    ];

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }
}
