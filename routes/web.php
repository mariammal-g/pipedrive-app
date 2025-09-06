<?php

use App\Http\Controllers\PipedriveController;
use App\Http\Middleware\VerifyPipedrivePanelJwt;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/pipedrive/connect', [PipedriveController::class, 'connect']);
Route::get('/pipedrive/callback', [PipedriveController::class, 'handleCallback']);
Route::get('/pipedrive/success', [PipedriveController::class, 'success']);
Route::get('/pipedrive/entry', [PipedriveController::class, 'entry'])
    ->middleware('pipedrive.panel');
Route::get('/pipedrive/show', [PipedriveController::class, 'show']);
