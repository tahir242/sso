<?php

use App\Domain\Federation\ApiScopes;
use App\Http\Controllers\Api\AccessRequestsController;
use App\Http\Controllers\Api\ApplicationUsersController;
use App\Http\Controllers\Api\RolesController;
use App\Http\Middleware\AuthenticateApplicationClient;
use Illuminate\Support\Facades\Route;

/*
| Server-to-server API for registered applications (OAuth client credentials).
| Every endpoint is scoped to the calling application, needs a scope the
| administrator enabled for it, and is rate-limited per application.
| Documentation: /docs/api
*/

$scope = fn (string $scope) => [AuthenticateApplicationClient::class.':'.$scope, 'throttle:application-api'];

Route::prefix('v1')->group(function () use ($scope) {
    Route::middleware($scope(ApiScopes::USERS_READ))->group(function () {
        Route::get('/users', [ApplicationUsersController::class, 'index'])->name('api.users.index');
        Route::get('/users/{userId}', [ApplicationUsersController::class, 'show'])->whereNumber('userId')->name('api.users.show');
    });

    Route::get('/roles', [RolesController::class, 'index'])->middleware($scope(ApiScopes::ROLES_READ))->name('api.roles.index');

    Route::middleware($scope(ApiScopes::ACCESS_REQUESTS_READ))->group(function () {
        Route::get('/access-requests', [AccessRequestsController::class, 'index'])->name('api.access-requests.index');
        Route::get('/access-requests/{id}', [AccessRequestsController::class, 'show'])->name('api.access-requests.show');
    });

    Route::post('/access-requests', [AccessRequestsController::class, 'store'])
        ->middleware($scope(ApiScopes::ACCESS_REQUESTS_WRITE))->name('api.access-requests.store');
});
