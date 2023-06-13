<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\UuidTrait;

class TradeMarkRepresentative extends Model
{
    use UuidTrait;
    protected $connection = 'wipo_mysql';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'trademark_representatives';

    /**
     * fillable
     *
     * @var array
     */
    protected $fillable = [
        'trademark_id',
        'country_id',
        'representative_id',
        'name',
        'address',
        'created_by',
        'updated_by',
        'deleted_at',
    ];

    /**
     * Trademark Details
     *
     * @return void
     */
    public function trademark()
    {
        return $this->hasOne(TradeMark::class, 'trademark_id', 'id');
    }

    /**
     * Trademark representative country Details
     *
     * @return void
     */
    public function country()
    {
        return $this->hasOne(WipoCountry::class, 'id', 'country_id')->select('id', 'name', 'code', 'flag');
    }
}
