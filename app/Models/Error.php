<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\SoftDeletes;

class Error extends Model
{
    protected $connection = 'error_mysql';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'errors';


    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['user_id', 'code', 'file', 'line', 'message', 'trace'];
}
