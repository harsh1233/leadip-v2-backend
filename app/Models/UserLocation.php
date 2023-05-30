<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserLocation extends Model
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
        'country_code',
        'city_id',
        'address',
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

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_code', 'code');
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

        public function city_details()
    {
        return $this->belongsTo(City::class, 'city_id')->select('id', 'name');
    }

    public function country_details()
    {
        return $this->belongsTo(Country::class, 'country_code', 'code')->select('code', 'name');
    }

}
