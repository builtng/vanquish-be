<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MenuPrivilege extends Model
{
    protected $fillable = [
        'menu_id',
        'roles',
    ];

    protected $casts = [
        'roles' => 'array',
    ];
}
