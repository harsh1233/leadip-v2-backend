<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserDetail extends Model
{
    use HasFactory, UuidTrait, SoftDeletes;

    /**
     * fillable
     *
     * @var array
     */
    protected $fillable = [
        'id',
        'user_id',
        'point_of_contact',
        'description',
        'phone_number',
        'whatsapp_number',
        'profile_completed_percentage',
        'linkedin_profile',
        'facebook_profile',
        'other_profile',
        'extra_channels',
        'expertises',
        'interests',
        'created_by',
        'updated_by',
        'quality_rating',
        'company_size',
        'revenue',
        'positions',
        'use_of_contact_data',
    ];

    public $appends = ['extra_channels_array', 'company_size_array'];

    /**
     * casts
     *
     * @var array
     */
    protected $casts = [
        'expertises'     => 'array',
        'interests'      => 'array',
    ];

    /**
     * getExtraChannelsAttribute
     *
     * @param  mixed $value
     * @return void
     */
    // public function getExtraChannelsAttribute($value)
    // {
    //     return json_decode($value);
    // }

    public function preferenceIndustries()
    {
        return $this->hasMany(PreferenceIndustry::class, 'user_detail_id');
    }

    public function preferenceCountries()
    {
        return $this->hasMany(PreferenceCountry::class, 'user_detail_id');
    }

    public function preferenceAgents()
    {
        return $this->hasMany(PreferenceAgent::class, 'user_detail_id');
    }

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

    public function getExtraChannelsArrayAttribute()
    {
        $extra_channels = [];
        if ($this->extra_channels) {
            if (@unserialize($this->extra_channels)) {
                $extra_channels =  unserialize($this->extra_channels);
            } else {
                $extra_channels[] = $this->extra_channels;
            }
        }
        return $extra_channels;
    }

    public function getCompanySizeArrayAttribute()
    {
        $company_size = [];
        if ($this->company_size) {
            if (@unserialize($this->company_size)) {
                $company_size =  unserialize($this->company_size);
            } else {
                $company_size[] = $this->company_size;
            }
        }
        return $company_size;
    }
}
