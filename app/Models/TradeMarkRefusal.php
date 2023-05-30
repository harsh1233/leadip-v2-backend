<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\UuidTrait;

class TradeMarkRefusal extends Model
{
    use UuidTrait;
    protected $connection = 'wipo_mysql';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'trademark_refusals';

    /**
     * fillable
     *
     * @var array
     */
    protected $fillable = [
        'trademark_id',
        'refused_country_id',
        'gazette_number',
        'notification_date',
        'refusal_type',
        'is_refusal_update',
        'created_by',
        'updated_by',
        'deleted_at',
    ];

    public function trademark()
    {
        return $this->hasOne(TradeMark::class, 'trademark_id', 'id');
    }

    public function country()
    {
        return $this->hasOne(WipoCountry::class, 'id', 'refused_country_id')->select('id', 'name', 'code', 'flag');
    }
}
