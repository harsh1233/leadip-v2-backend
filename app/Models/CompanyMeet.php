<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyMeet extends Model
{
    use HasFactory,UuidTrait,SoftDeletes;
    
    /**
     * fillable
     *
     * @var array
     */
    protected $fillable = [
        'id',
        'company_id',
        'link',
        'date',
        'name',
        'country_code',
        'image',
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

    public function country_details()
    {
        return $this->belongsTo(Country::class, 'country_code', 'code')->select('code', 'name');
    }
}
