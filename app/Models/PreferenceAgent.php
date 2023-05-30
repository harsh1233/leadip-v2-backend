<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PreferenceAgent extends Model
{
    use HasFactory;

    protected $fillable = [
        'agent_id',
        'user_detail_id'
    ];

    protected $table = 'user_preference_agent';


    public function userDetail()
    {
        return $this->belongsTo(UserDetail::class);
    }

    public $timestamps = false;
}
