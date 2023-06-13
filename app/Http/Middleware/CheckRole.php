<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Role;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next, $roles)
    {
        $roles = explode("|", $roles);
        $status = false;
        foreach ($roles as $currentrole) {
            $role = Role::where('id', auth()->user()->role_id)->first();
            if ($role->name == $currentrole) {
                $status = true;
                break;
            }
        }
        if (!$status && ('App\\Http\\Controllers\\API\\TeamController@addMember' == $request->route()->getActionName())) {
            return error(__("You don't have permission to add the member"), [], 'forbidden');
        }
        if (!$status && ('App\\Http\\Controllers\\API\\TeamController@updateMember' == $request->route()->getActionName())) {
            return error(__("You don't have permission to update this member"), [], 'forbidden');
        }
        if (!$status) {
            return error(__("Sorry, you don't have permission to access this page"), [], 'forbidden');
        }
        return $next($request);
    }
}
