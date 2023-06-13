<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\UuidTrait;

class TradeMark extends Model
{
    use UuidTrait;
    protected $connection = 'wipo_mysql';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'trademarks';

    /**
     * Get Nice Class.
     *
     * @return \Illuminate\Database\Eloquent\Casts\Attribute
     */
    protected function getNiceClassAttribute($value)
    {
        return json_decode($value);
    }

    /**
     * Trademark Image Details
     *
     * @return void
     */
    public function image()
    {
        return $this->hasOne(TradeMarkImage::class, 'trademark_id')->select('id', 'trademark_id', 'image_id', 'image_type', 'image_url', 'text', 'image_class');
    }

    /**
     * Trademark holder Details
     *
     * @return void
     */
    public function holder()
    {
        return $this->hasOne(TradeMarkHolder::class, 'trademark_id')->select('id', 'trademark_id', 'country_id', 'holder_id',  'name', 'address');
    }

    /**
     * Trademark representative Details
     *
     * @return void
     */
    public function representative()
    {
        return $this->hasOne(TradeMarkRepresentative::class, 'trademark_id')->select('id', 'trademark_id', 'country_id', 'representative_id', 'name', 'address');
    }

    /**
     * Trademark refusal Details
     *
     * @return void
     */
    public function refusal()
    {
        return $this->hasOne(TradeMarkRefusal::class, 'trademark_id')->select('id', 'trademark_id', 'refused_country_id', 'gazette_number', 'notification_date', 'refusal_type', 'is_refusal_update');
    }

    /**
     * Trademark country Details
     *
     * @return void
     */
    public function designation_country()
    {
        return $this->hasOne(WipoCountry::class, 'id', 'designation_country_id')->select('id', 'name', 'code', 'flag');
    }
}
