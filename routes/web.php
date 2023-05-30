<?php

use App\Http\Controllers\API\SocialAuthController;
use App\Http\Controllers\API\OutlookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/' , function(){
    return ok('Welcome to leadip-v2 Backend');
});
Route::group(['middleware' => ['web']], function () {
    Route::get('auth/{provider}', [SocialAuthController::class, 'redirectToGoogle']);
    Route::get('auth/{provider}/callback', [SocialAuthController::class, 'handleGoogleCallback']);

    // Sync Routes
    Route::get('auth/{provider}/sync-contact', [SocialAuthController::class, 'redirectToGoogle']);
    Route::get('outlook/sync-contact', [OutlookController::class, 'outlookSignin']);
    Route::get('outlook/callback', [OutlookController::class, 'outlookCallback']);
});
