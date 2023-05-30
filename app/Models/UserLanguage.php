<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserLanguage extends Model
{
    use HasFactory,UuidTrait,SoftDeletes;
    
    /**
     * fillable
     *
     * @var array
     */
    protected $fillable = [
        'id',
        'user_id',
        'language_id',
        'proficiency_level',
        'created_by',
        'updated_by',
    ];

    /**
     * boot
     *
     * @return void
     */
    public static function boot()
    {
        parent::boot();

        // create a event to happen on creating
        static::creating(function ($record) {
            $record->created_by = auth()->user()->id ?? null;
        });

        // create a event to happen on updating
        static::updating(function ($record) {
            $record->updated_by = auth()->user()->id ?? null;
        });
    }
    
    /**
     * user
     *
     * @return void
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

     /**
     * get language
     *
     * @return void
     */
    public function language()
    {
        return $this->belongsTo(Language::class);
    }
    
}
