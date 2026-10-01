<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailSenderSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'category',
        'from_email',
        'from_name',
    ];
}
