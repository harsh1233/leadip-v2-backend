<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PreferenceIndustry extends Model
{
    use HasFactory;

    protected $fillable = [
        'industry_id',
        'user_detail_id'
    ];

    protected $table = 'user_preference_industry';

    public function industry()
    {
        return $this->belongsTo(Industry::class)->select('id', 'name');
    }

    public function userDetail()
    {
        return $this->belongsTo(UserDetail::class);
    }

    public $timestamps = false;
}
