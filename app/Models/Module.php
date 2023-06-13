<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\UuidTrait;

class Module extends Model
{
    use HasFactory,UuidTrait;

    protected $table ='modules';


    protected $fillable = [
        'id',
        'name',
        'code'
    ];
    public function permissions(){

        if(auth()->user()){
            $permissions =  $this->hasMany(ModulePermissionRole::class, 'module_code', 'code')->select('module_code','permission_code','has_access','role_id')->where('role_id',auth()->user()->role_id);
        }
        else{
            $permissions =  $this->hasMany(ModulePermissionRole::class, 'module_code', 'code')->select('module_code','permission_code','has_access','role_id');
        }

        return $permissions;
    }
}
