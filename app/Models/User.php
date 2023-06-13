<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, UuidTrait;

    protected $table = 'users';

    protected $appends = ['fullname'];


    /**
     * fillable
     *
     * @var array
     */
    protected $fillable = [
        'id',
        'company_id',
        'role_id',
        'first_name',
        'last_name',
        'email',
        'password',
        'social_id',
        'social_type',
        'is_email_verified',
        'verification_token',
        'verification_token_expiry',
        'profile_picture',
        'position',
        'onboarding_status',
        'is_onboarded',
        'sync_with_gmail',
        'sync_with_outlook',
        'sync_with_linkedin',
        'is_active',
        'is_first_time_login',
        'created_by',
        'updated_by',
        'google_sync_count',
        'linkdin_sync_count',
        'outlook_sync_count',
        'prospect_google_sync_count',
        'client_google_sync_count',
        'prospect_linkdin_sync_count',
        'client_linkdin_sync_count',
        'prospect_outlook_sync_count',
        'client_outlook_sync_count',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at'     => 'datetime',
        'is_active'             => 'boolean',
        'is_first_time_login'   => 'boolean',
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
     * UserLocation
     *
     * @return void
     */
    public function userLocations()
    {
        return $this->hasMany(UserLocation::class);
    }

    /**
     * Company relation
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Company Detail
     */
    public function companyDetail()
    {
        return $this->hasOne(Company::class, 'id', 'company_id');
    }

    /**
     * User Detail
     */
    public function userDetail()
    {
        return $this->hasOne(UserDetail::class);
    }

    /**
     * UserLanguage
     *
     * @return void
     */
    public function userLanguages()
    {
        return $this->hasMany(UserLanguage::class);
    }

     /**
     * User Certification
     *
     * @return void
     */
    public function userCertifications()
    {
        return $this->hasMany(UserCertification::class);
    }

    /**
     * User Role
     *
     * @return void
     */
    public function role()
    {
        return $this->hasOne(Role::class, 'id', 'role_id');
    }

    /**
     * User Location
     *
     * @return void
     */
    public function location()
    {
        return $this->hasOne(UserLocation::class);
    }

    /**
     * create user Full name
     *
     * @return string
     */
    public function getFullNameAttribute() // notice that the attribute name is in CamelCase.
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    /*Get Compny contacts */
    public function companyContacts(){
        return $this->hasMany(CompanyContact::class, 'created_by', 'id');
    }

}
