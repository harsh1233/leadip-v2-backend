<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\UuidTrait;

class ApolloHistory extends Model
{
    use UuidTrait;
    protected $connection = 'apollo_mysql';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'apollo_histories';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['people_id','first_name','last_name','headline','linkedin_url','email','title','email_status','photo_url','twitter_url','github_url','facebook_url','extrapolated_email_confidence','state','city','country','seniority','intent_strength','show_intent','revealed_for_current_team','personal_emails','departments','employment_history','organization','phone_numbers','subdepartments','functions'];

    /**
     * Get organization details.
     *
     * @return \Illuminate\Database\Eloquent\Casts\Attribute
     */
    protected function getOrganizationAttribute($value)
    {
        return json_decode($value);
    }

    /**
     * Get personal_emails details.
     *
     * @return \Illuminate\Database\Eloquent\Casts\Attribute
     */
    protected function getPersonalEmailsAttribute($value)
    {
        return json_decode($value);
    }

    /**
     * Get departments details.
     *
     * @return \Illuminate\Database\Eloquent\Casts\Attribute
     */
    protected function getDepartmentsAttribute($value)
    {
        return json_decode($value);
    }

    /**
     * Get employment_history details.
     *
     * @return \Illuminate\Database\Eloquent\Casts\Attribute
     */
    protected function getEmploymentHistoryAttribute($value)
    {
        return json_decode($value);
    }

    /**
     * Get phone_numbers details.
     *
     * @return \Illuminate\Database\Eloquent\Casts\Attribute
     */
    protected function getPhoneNumbersAttribute($value)
    {
        return json_decode($value);
    }

    /**
     * Get subdepartments details.
     *
     * @return \Illuminate\Database\Eloquent\Casts\Attribute
     */
    protected function getSubdepartmentsAttribute($value)
    {
        return json_decode($value);
    }

    /**
     * Get functions details.
     *
     * @return \Illuminate\Database\Eloquent\Casts\Attribute
     */
    protected function getFunctionsAttribute($value)
    {
        return json_decode($value);
    }
}
