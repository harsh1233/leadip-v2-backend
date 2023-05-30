<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyPeople extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'people_id'
    ];

    protected $table = 'company_people';

    public $timestamps = false;
}
