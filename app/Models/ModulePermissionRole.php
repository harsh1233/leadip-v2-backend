<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\UuidTrait;

class ModulePermissionRole extends Model
{
    use HasFactory,UuidTrait;

    protected $table =   'module_permission_role';
    protected $fillable =['id','module_code','permission_code','role_id','has_access'];

    public function getHasAccessAttribute($value)
    {
        return $value == 1? true:false;
    }
}
