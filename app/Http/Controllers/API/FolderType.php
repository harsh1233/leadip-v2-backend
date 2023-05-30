<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\UuidTrait;

class FolderType extends Model
{
    use HasFactory, SoftDeletes, UuidTrait;
    protected $fillable = [
        'name',
        'created_at',
        'updated_at'
    ];
}
