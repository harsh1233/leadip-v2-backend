<?php

namespace App\Http\Middleware;

use Closure;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class CheckAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $bearer = $request->bearerToken();
        if($bearer)
        {
            //$tokenId = explode('|', $bearer);
            $token = PersonalAccessToken::findToken($bearer);
            if($token)
            {
                $finduser = User::where('id', $token->tokenable_id)->first();
                if($finduser)
                {
                    if (!Auth::check())
                    {
                        Auth::login($finduser);
                    }

                    // Add user in request
                    // $request->merge(['user' => $finduser]);
                    // //add this
                    // $request->setUserResolver(function () use ($finduser) {
                    //     return $finduser;
                    // });
                    return $next($request);
                }
                else
                {
                    return error(__('You have been logged out due to change in password. Please login again with the new password.'), [], 'unauthenticated');
                }
            }
            else
            {
                return error(__('You have been logged out due to change in password. Please login again with the new password.'), [], 'unauthenticated');
            }
        }

        if (!$request->expectsJson()) {
            return error(__('Unauthenticated.'), [], 'unauthenticated');
        }
        return $next($request);
    }
}
