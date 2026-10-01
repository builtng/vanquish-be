<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Folder extends Model
{
    protected $fillable = [
        'name',
        'parent_id',
        'type',
        'created_by',
        'client_id',
        'tc_id',
        'category',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Folder::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Folder::class, 'parent_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(SharedDocument::class, 'folder_id');
    }

    public function shares(): HasMany
    {
        return $this->hasMany(FolderShare::class);
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
