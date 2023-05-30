<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PreferenceCountry extends Model
{
    use HasFactory;

    protected $fillable = [
        'country_id',
        'user_detail_id'
    ];

    protected $table = 'user_preference_country';

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id', 'code')->select('code', 'name');
    }

    public function userDetail()
    {
        return $this->belongsTo(UserDetail::class);
    }

    public $timestamps = false;
}
