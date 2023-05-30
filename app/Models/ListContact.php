<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ListContact extends Model
{
    use HasFactory;

    protected $table = 'list_contacts';

    protected $fillable =['list_id','contact_id','type'];

    public $timestamps = false;
}
