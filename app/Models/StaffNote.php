<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffNote extends Model
{
    protected $fillable = [
        'staff_id',
        'admin_id',
        'note',
        'is_read',
    ];

    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
