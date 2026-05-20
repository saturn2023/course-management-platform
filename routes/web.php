<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
use App\Http\Controllers\XeroAuthController;

Route::get('/xero/connect', [XeroAuthController::class, 'connect'])
    ->name('xero.connect');

Route::get('/xero/callback', [XeroAuthController::class, 'callback'])
    ->name('xero.callback');