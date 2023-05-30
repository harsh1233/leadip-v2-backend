<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\UuidTrait;

class Folder extends Model
{
    use HasFactory, SoftDeletes, UuidTrait;
    protected $fillable = [
        'name',
        'description',
        'contact_id',
        'folder_type_id',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at'
    ];

    public static function boot()
    {
        parent::boot();

        // create a event to happen on creating
        static::creating(function ($record) {
            $record->created_by = auth()->user()->id;
        });

        // create a event to happen on updating
        static::updating(function ($record) {
            $record->updated_by = auth()->user()->id ?? null;
        });
    }
    /* Get detail of user */
    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    /*Get compnay contact detail */
    public function companyContact()
    {
        return $this->belongsTo(CompanyContact::class, 'contact_id');
    }
    /*Get compnay contact detail */
    public function folderType()
    {
        return $this->belongsTo(FolderType::class, 'folder_type_id');
    }
}
