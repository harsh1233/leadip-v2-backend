<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\UuidTrait;

class AssignedList extends Model
{
    use HasFactory, UuidTrait;

    protected $table = 'assigned_lists';

    protected $fillable = [
        'id',
        'assigned_to',
        'assigned_from',
        'list_id',
        'owned_by'
    ];


    public $timestamps = false;

    /*Get Assing to detail */
    public function assigned_to_details()
    {
        return $this->belongsTo(User::class, 'assigned_to')->select('id', 'first_name', 'last_name');
    }

    /*Get Assing by detail */
    public function assigned_by_details()
    {
        return $this->belongsTo(User::class, 'assigned_from')->select('id', 'first_name', 'last_name');
    }
}
