<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

// Both panels share the web guard; /app/login is the only sign-in page, so guests hitting /admin land there.
Route::get('/login', fn () => redirect()->route('filament.app.auth.login'))->name('login');
