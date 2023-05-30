<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\UuidTrait;

class GlobalFile extends Model
{
    use HasFactory, SoftDeletes, UuidTrait;
    protected $fillable = [
        'uploaded_file',
        'company_id',
        'contact_id',
        'file_name',
        'message',
        'company_realted',
        'contact_related',
        'created_by',
        'updated_by',
        'file_name',
        'file_type',
        'created_at',
        'updated_at'
    ];

    public static function boot()
    {
        parent::boot();

        // create a event to happen on creating
        static::creating(function ($record) {
            $record->created_by = auth()->user()->id;
        });

        // create a event to happen on updating
        static::updating(function ($record) {
            $record->updated_by = auth()->user()->id ?? null;
        });
    }
    /* Get detail of user */
    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    /*Get compnay contact detail */
    public function companyContact()
    {
        return $this->belongsTo(CompanyContact::class, 'company_related');
    }
    /*Get company people contact detail */
    public function peopleContact()
    {
        return $this->belongsTo(CompanyContact::class, 'contact_related');
    }
    /*Get contact detail */
    public function contact()
    {
        return $this->belongsTo(CompanyContact::class, 'contact_id');
    }
}
