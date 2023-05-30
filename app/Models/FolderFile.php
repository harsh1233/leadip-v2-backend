<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FolderFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'folder_id',
        'file_id'
    ];

    protected $table = 'folder_files';

    public $timestamps = false;
}
