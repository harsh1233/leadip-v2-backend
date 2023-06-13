<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\UuidTrait;

class Industry extends Model
{
    use HasFactory, UuidTrait;


    /**
     * fillable
     *
     * @var array
     */

    protected $table = 'industries';

    protected $primaryKey = 'id';

    protected $keyType    = 'string';

    public $incrementing  = false;

    public function preferenceIndustries()
    {
        return $this->hasMany(PreferenceIndustry::class, 'industry_id');
    }

    protected $fillable = [
        'id',
        'name',
        'created_at',
        'updated_at',
    ];
}
