<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyContact extends Model
{
    use HasFactory, UuidTrait, SoftDeletes;

    /**
     * fillable
     *
     * @var array
     */

    protected $table = 'contacts';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'company_id',
        'profile_picture',
        'type',
        'section',
        'sub_type',
        'company_name',
        'priority',
        'category',
        'role',
        'point_of_contact',
        'email',
        'phone_number',
        'client_since',
        'first_name',
        'last_name',
        'country_code',
        'city_id',
        'recently_contacted_by',
        'areas_of_expertise',
        'covered_regions',
        'ongoing_work',
        'potencial_for',
        'industry',
        'marketing',
        'slag',
        'is_import',
        'created_at',
        'updated_at',
        'created_by',
        'updated_by',
        'deleted_at',
        'social_type',
        'social_id',
        'is_enrich',
        'linkedin_url',
        'is_private',
        'is_lost'
    ];

    public $appends = ['recently_contacted_by_array', 'ongoing_work_array', 'potencial_for_array', 'industry_array', 'covered_regions_array', 'areas_of_expertise_array', 'marketing_array', 'full_name'];

    public static function boot()
    {
        parent::boot();

        // create a event to happen on creating
        static::creating(function ($record) {
            $record->created_by = auth()->user()->id ?? null;
            $record->created_at = date("Y-m-d H:i:s");
        });

        // create a event to happen on updating
        static::updating(function ($record) {
            $record->updated_by = auth()->user()->id ?? null;
            $record->updated_at = date("Y-m-d H:i:s");
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

    public function getRecentlyContactedByArrayAttribute()
    {
        $recently_contacted_by = [];
        if ($this->recently_contacted_by) {
            if (@unserialize($this->recently_contacted_by)) {
                $recently_contacted_by =  unserialize($this->recently_contacted_by);
            } else {
                $recently_contacted_by[] = $this->recently_contacted_by;
            }
        }
        return $recently_contacted_by;
    }

    public function getMarketingArrayAttribute()
    {
        $marketing = [];
        if ($this->marketing) {
            if (@unserialize($this->marketing)) {
                $marketing =  unserialize($this->marketing);
            } else {
                $marketing[] = $this->marketing;
            }
        }

        return $marketing;
    }

    public function getOngoingWorkArrayAttribute()
    {
        $ongoing_work = [];
        if ($this->ongoing_work) {
            if (@unserialize($this->ongoing_work)) {
                $ongoing_work =  unserialize($this->ongoing_work);
            } else {
                $ongoing_work[] = $this->ongoing_work;
            }
        }
        return $ongoing_work;
    }
    public function getPotencialForArrayAttribute()
    {
        $potencial_for = [];
        if ($this->potencial_for) {
            if (@unserialize($this->potencial_for)) {
                $potencial_for =  unserialize($this->potencial_for);
            } else {
                $potencial_for[] =  $this->potencial_for;
            }
        }

        return $potencial_for;
    }
    public function getIndustryArrayAttribute()
    {
        //dd(unserialize($this->industry));
        $industry = [];
        if ($this->industry) {
            if (@unserialize($this->industry)) {
                $industry =  unserialize($this->industry);
            } else {
                $industry[] =  $this->industry;
            }
        }
        //dd($industry);
        return $industry;
    }
    public function getCoveredRegionsArrayAttribute()
    {
        $covered_regions = [];
        if ($this->covered_regions) {
            if (@unserialize($this->covered_regions)) {
                $covered_regions =  unserialize($this->covered_regions);
            } else {
                $covered_regions[] = $this->covered_regions;
            }
        }

        $covered_regions_with_names = [];

        foreach ($covered_regions as $region) {
            $country = Country::where('code', $region)->first();
            if ($country) {
                $covered_regions_with_names[] = [
                    'code' => $region,
                    'name' => $country->name
                ];
            }
        }

        return $covered_regions_with_names;
    }
    public function getAreasOfExpertiseArrayAttribute()
    {
        $areas_of_expertise = [];
        if ($this->areas_of_expertise) {
            if (@unserialize($this->areas_of_expertise)) {
                $areas_of_expertise =  unserialize($this->areas_of_expertise);
            } else {
                $areas_of_expertise[] = $this->areas_of_expertise;
            }
        }
        return $areas_of_expertise;
    }
    /*Get User detail */
    public function users()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }
    /*Get contacts based on list */
    public function listContact()
    {
        return $this->belongsTo(ListContact::class, 'id', 'contact_id');
    }
    /*Get Compnay Detail */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
    /* Get full name of contacts */
    public function getFullNameAttribute() // notice that the attribute name is in CamelCase.
    {
        return $this->first_name . ' ' . $this->last_name;
    }
    /* Take only six records of assinged contact */
    public function assinged_contact()
    {
        return $this->hasMany(AssignedContact::class, 'contact_id', 'id')->with('assigned_to_details')->whereHas('assigned_to_details', function ($query) {
            $query->where('deleted_at', null);
        })->take(6)->orderBy('created_at', 'desc');
    }
    /* Get Company name when people create based on particular compnay domain */
    // public function peopleCompany(){
    //     return $this->belongsToMany(CompanyContact::class,'company_people', 'people_id','company_id')->select('id','company_name');
    // }
}
