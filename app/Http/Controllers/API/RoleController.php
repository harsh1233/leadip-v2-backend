<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Role;

class RoleController extends Controller
{
    /**
     * get Roles api
     *
     * @param  mixed $request
     * @return void
     */
    public function list(Request $request)
    {
        // Get All roles name and ids.
        $roles = Role::where('name', '!=', config('constants.super_admin'))->get(['id','name']);
        return ok(__('Role list successfully!'), $roles);
    }
}
