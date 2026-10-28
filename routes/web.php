<?php

use App\Http\Controllers\ChatController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::post('/chat', [ChatController::class, 'store'])->middleware('throttle:30,1')->name('chat.store');
Route::get('/chat/{publicId}', [ChatController::class, 'show'])->name('chat.show');
