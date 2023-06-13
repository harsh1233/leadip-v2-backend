<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\UuidTrait;

class Note extends Model
{
    use HasFactory, SoftDeletes,UuidTrait;

    protected $fillable =[
        'id',
        'sub_type',
        'contact_id',
        'company_id',
        'subject',
        'note',
        'note_type_id',
        'note_content',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at'
    ];

    protected $appends =['timeAgo'];

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

    /*Get created user detail */
    public function users(){
        return $this->belongsTo(User::class, 'created_by', 'id');
    }
    /* Get New Time */
    public function gettimeAgoAttribute(){
        return $this->updated_at->diffForHumans();
    }
    /*Get Note types */
    public function noteType(){
        return $this->hasOne(NoteType::class, 'id', 'note_type_id');
    }

}
