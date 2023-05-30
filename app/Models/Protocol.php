<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Protocol extends Model
{
    use HasFactory, UuidTrait, SoftDeletes;

    /**
     * fillable
     *
     * @var array
     */

    protected $table = 'protocols';

    protected $primaryKey = 'id';

    protected $appends = ['timeAgo', 'iconUrl'];

    public $timestamps = false;

    protected $fillable = [
        'id',
        'contact_id',
        'assigned_to_id',
        'assigned_by_id',
        'category',
        'message',
        'icon',
        'read',
        'read_at',
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

    /*Get User detail */
    public function users()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    /*Get time ago attribute */

    public function gettimeAgoAttribute()
    {
        return Carbon::parse($this->created_at)->diffForHumans();
    }

    /*Get full icon url */
    public function getIconUrlAttribute()
    {
        return 'https://leadip-v2-s3-test.s3.ap-south-1.amazonaws.com/notification/' . $this->icon;
    }
}
