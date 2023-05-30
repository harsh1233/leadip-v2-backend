<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\UuidTrait;

class TradeMarkImage extends Model
{
    use UuidTrait;
    protected $connection = 'wipo_mysql';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'trademark_images';

    /**
     * fillable
     *
     * @var array
     */
    protected $fillable = [
        'trademark_id',
        'image_id',
        'image_type',
        'image_url',
        'text',
        'image_class',
        'created_by',
        'updated_by',
        'deleted_at',
    ];

    /**
     * Get Image Class.
     *
     * @return \Illuminate\Database\Eloquent\Casts\Attribute
     */
    protected function getImageClassAttribute($value)
    {
        return json_decode($value);
    }

    public function trademark()
    {
        return $this->hasOne(TradeMark::class, 'trademark_id', 'id');
    }
}
