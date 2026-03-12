<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| EcoDash — Web Routes
|--------------------------------------------------------------------------
*/

// Page de connexion
Route::get('/', function () {
    return file_get_contents(resource_path('views/dashboard/login.html'));
});

// Dashboard
Route::get('/dashboard', function () {
    return file_get_contents(resource_path('views/dashboard/dashboard.html'));
});

// Toutes les autres routes → login (sauf /api)
Route::get('/{any}', function () {
    return file_get_contents(resource_path('views/dashboard/login.html'));
})->where('any', '^(?!api).*');
