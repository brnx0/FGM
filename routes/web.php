<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Provisória: a home real (com editais e eventos) chega na Task 5.
Route::get('/', fn () => Inertia::render('Home'))->name('home');
