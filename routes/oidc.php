<?php

use App\Http\Controllers\Oidc\DiscoveryController;
use App\Http\Controllers\Oidc\EndSessionController;
use App\Http\Controllers\Oidc\JwksController;
use App\Http\Controllers\Oidc\UserInfoController;
use App\Http\Middleware\AuthorizeApplicationAccess;
use App\Http\Middleware\ThrottleFailedClientAuthentication;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Http\Controllers\AccessTokenController;
use Laravel\Passport\Http\Controllers\AuthorizationController;

/*
| OpenID Connect provider endpoints. Only the Passport endpoints we use are
| exposed (Passport's own route file is disabled): no consent screens, no
| client/token JSON APIs, no implicit or password grants.
*/

Route::get('/.well-known/openid-configuration', DiscoveryController::class)->name('oidc.discovery');
Route::get('/oauth/jwks', JwksController::class)->name('oidc.jwks');

Route::get('/oauth/authorize', [AuthorizationController::class, 'authorize'])
    ->middleware(['web', AuthorizeApplicationAccess::class])
    ->name('passport.authorizations.authorize');

Route::post('/oauth/token', [AccessTokenController::class, 'issueToken'])
    ->middleware(ThrottleFailedClientAuthentication::class)
    ->name('passport.token');

Route::match(['get', 'post'], '/oauth/userinfo', UserInfoController::class)
    ->middleware('auth:api')
    ->name('oidc.userinfo');

Route::middleware('web')->group(function () {
    Route::get('/oauth/logout', [EndSessionController::class, 'show'])->name('oidc.logout');
    Route::post('/oauth/logout', [EndSessionController::class, 'confirm'])->middleware('auth')->name('oidc.logout.confirm');
});
