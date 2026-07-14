<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::routes([
    'middleware' => [
        \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        'auth:sanctum',
    ],
]);

Broadcast::channel('store.{storeId}', function ($user, int $storeId) {
    return method_exists($user, 'canAccessStore') && $user->canAccessStore($storeId);
});

Broadcast::channel('company.{companyId}', function ($user, int $companyId) {
    return method_exists($user, 'canAccessCompany') && $user->canAccessCompany($companyId);
});

Broadcast::channel('admin.dashboard', function ($user) {
    return method_exists($user, 'isAdmin') && $user->isAdmin();
});
