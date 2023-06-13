<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssignedContact extends Model
{
    use HasFactory, UuidTrait, SoftDeletes;

    /**
     * fillable
     *
     * @var array
     */

    protected $table = 'assigned_contacts';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'contact_id',
        'assigned_to_id',
        'assigned_by_id',
        'created_at',
        'updated_at',
        'created_by',
        'updated_by',
        'deleted_at',
    ];

    public static function boot()
    {
        parent::boot();

        // create a event to happen on creating
        static::creating(function ($record) {
            $record->created_by = auth()->user()->id ?? null;
            $record->created_at = date("Y-m-d H:i:s");
        });

        // create a event to happen on updating
        static::updating(function ($record) {
            $record->updated_by = auth()->user()->id ?? null;
            $record->updated_at = date("Y-m-d H:i:s");
        });
    }

    public function contact_details()
    {
        return $this->belongsTo(CompanyContact::class, 'contact_id');
    }
    public function assigned_to_details()
    {
        return $this->belongsTo(User::class, 'assigned_to_id')->select('id', 'first_name', 'last_name', 'profile_picture');
    }
    public function assigned_by_details()
    {
        return $this->belongsTo(User::class, 'assigned_by_id')->select('id', 'first_name', 'last_name');
    }
}
