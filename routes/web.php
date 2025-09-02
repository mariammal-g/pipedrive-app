<?php

use App\Http\Controllers\PipedriveController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/pipedrive', [PipedriveController::class, 'show']);
Route::get('/oauth/callback', [PipedriveController::class, 'handleCallback']);
Route::get('/pipedrive/success', function () {
    return view('pipedrive-success');
});