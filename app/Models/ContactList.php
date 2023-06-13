<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\UuidTrait;

class ContactList extends Model
{
    use HasFactory, softDeletes,UuidTrait;

    protected $table = 'lists';

    protected $fillable = [
        'id',
        'company_id',
        'type',
        'sub_type',
        'main_type',
        'name',
        'size',
        'created_by',
        'updated_by'
    ];

    //protected $appends = ['assign_by','listType'];
    protected $appends = ['listType'];
    public static function boot()
    {
        parent::boot();

        // create a event to happen on creating
        static::creating(function ($record) {
            $record->created_by = auth()->user()->id ?? null;
        });

        // create a event to happen on updating
        static::updating(function ($record) {
            $record->updated_by = auth()->user()->id ?? null;
        });
    }

    public function users(){
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    /*Please change here Contact table instaed user */
    public function listContact(){
        //return $this->belongsToMany(Contact::class,'list_contacts','list_id','contact_id')->with('type');
        return $this->belongsToMany(CompanyContact::class, 'list_contacts', 'list_id', 'contact_id')->with('type');
    }
    /* Set assing by name */
    // public function getAssignByAttribute(){
    //     if($this->created_by!=auth()->user()->id){
    //         return $this->users->first_name.' ' .$this->users->last_name;
    //     }
    // }
    /*Type Accessor*/

    // public function getTypeAttribute($value){
    //     switch($value){
    //         case 'L':
    //             return __('Lead');
    //         case 'CL':
    //             return __('Custom List');
    //         case 'C':
    //             return __('Client');
    //         case 'LC':
    //             return __('Lost Contacts');
    //         default:
    //             return $value;
    //     }
    // }

    /*SubType Accessor */
    // public function getSubTypeAttribute($value)
    // {
    //     switch ($value) {
    //         case 'C':
    //             return __('Company');
    //         case 'P':
    //             return __('People');
    //         default:
    //             return $value;
    //     }
    // }
    public function assignedList(){
        return $this->hasMany(AssignedList::class,'list_id','id');
    }
    /* Get list type full name */
    public function getlistTypeAttribute(){
        $value = $this->type;
        switch($value){
            case 'P':
                return __('Prospect');
            case 'CL':
                return __('Custom List');
            case 'C':
                return __('Client');
            case 'LC':
                return __('Lost Contacts');
            default:
                return $value;
        }
    }
}
