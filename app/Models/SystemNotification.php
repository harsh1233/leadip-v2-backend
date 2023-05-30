<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\SoftDeletes;

class SystemNotification extends Model
{
    use HasFactory, UuidTrait, SoftDeletes;

    protected $appends =['timeAgo','iconUrl'];
    protected $fillable =
    [
        'id',
        'type',
        'title',
        'read_at',
        'sender_id',
        'receiver_id',
        'icon'
    ];

    /*Get time ago attribute */
    
    public function gettimeAgoAttribute(){
        return $this->created_at->diffForHumans();
    }
    /*Get full icon url */
    public function getIconUrlAttribute(){
        return 'https://leadip-v2-s3-test.s3.ap-south-1.amazonaws.com/notification/'.$this->icon;
    }
}
