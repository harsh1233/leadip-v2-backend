<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PasswordReset extends Model
{
    use HasFactory;

    const UPDATED_AT = null;
    
    /**
     * fillable
     *
     * @var array
     */
    protected $fillable = [
        'email',
        'token',
        'created_at',
    ];

}
