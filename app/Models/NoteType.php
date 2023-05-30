<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\UuidTrait;

class NoteType extends Model
{
    use HasFactory, UuidTrait;

    protected $fillable =[
        
        'name',
        'icon_url',
        'company_id'
    ];

    protected $appends =['icon'];

    public function getIconAttribute(){
        return 'https://leadip-v2-s3-test.s3.ap-south-1.amazonaws.com/NoteTypes/'.$this->icon_url;
    }
}
