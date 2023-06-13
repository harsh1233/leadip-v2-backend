<?php

namespace App\Http\Middleware;

use App\Models\CompanyContact;
use App\Models\ModulePermissionRole;
use Closure;
use Illuminate\Http\Request;
use App\Models\Module;
class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next,$module_code,$permission)
    {
        $modules = explode("|", $module_code);
        $query   = ModulePermissionRole::query();
        /* Check more than one module */
        if (count($modules) > 1) {
            $check = (clone $query)->whereIn('module_code', $modules)->where('permission_code', $permission)->where('role_id', auth()->user()->role_id)->where('has_access', 1)->first();
        } else {
            /*on single module */
            $check = (clone $query)->where('module_code', $module_code)->where('permission_code', $permission)->where('role_id', auth()->user()->role_id)->where('has_access', 1)->first();
        }
        if ($check) {
            return $next($request);
        }
        return error(__("Sorry, you don't have permission to " . $permission), [], 'forbidden');
    }
}
