<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

// Public shell — the player logs in client-side against the Sanctum API
// (see routes/api.php), not this route's own session, so no `auth`
// middleware belongs here.
Route::view('play', 'play')->name('play');

require __DIR__.'/settings.php';
