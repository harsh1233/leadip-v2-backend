<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
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
        'name',
        'headline',
        'description',
        'email',
        'phone',
        'website',
        'profile_picture',
        'linkedin_profile',
        'facebook_profile',
        'other_profile',
        'extra_channels',
        'services',
        'expertises',
        'regions',
        'languages',
        'created_by',
        'updated_by',
    ];
         
    public $appends = ['extra_channels_array'];

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
     * casts
     *
     * @var array
     */
    protected $casts = [
        'languages'      => 'array',
        'services'       => 'array',
        'expertises'     => 'array',
        'regions'        => 'array',
    ];
    
    /**
     * getextra_channelsAttribute
     *
     * @param  mixed $value
     * @return void
     */
    // public function getextra_channelsAttribute($value)
    // {
    //     return json_decode($value);
    // }

    public function getExtraChannelsArrayAttribute()
    {
        $extra_channels = [];
        if (unserialize($this->extra_channels)) {
            $extra_channels =  unserialize($this->extra_channels);
        }
        return $extra_channels;
    }

     /**
     * user
     *
     * @return void
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }

    /**
     * User Company
     *
     * @return void
     */
    public function offices()
    {
        return $this->hasMany(CompanyOffice::class);
    }
    /**
     * User Company
     *
     * @return void
     */
    public function certifications()
    {
        return $this->hasMany(CompanyCertification::class);
    }
     /**
     * User Company
     *
     * @return void
     */
    public function meets()
    {
        return $this->hasMany(CompanyMeet::class);
    }
    
}
