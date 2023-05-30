<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyOffice extends Model
{
    use HasFactory, UuidTrait, SoftDeletes;

    /**
     * fillable
     *
     * @var array
     */
    protected $fillable = [
        'id',
        'company_id',
        'type',
        'address',
        'country_code',
        'city_id',
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

    /* relations */

    public function city_details()
    {
        return $this->belongsTo(City::class, 'city_id')->select('id', 'name');
    }

    public function country_details()
    {
        return $this->belongsTo(Country::class, 'country_code', 'code')->select('code', 'name');
    }
}
