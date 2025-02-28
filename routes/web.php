<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;


Route::get('/', function () {
    return Inertia::render('home');
})->name('home');
Route::get('/about', function () {
    return Inertia::render('About');
})->name('About');

Route::get('/Ulogin', function () {
    return Inertia::render('login');
})->name('login');

Route::get('/dashboard', function () {
    return Inertia::render('dashboard');
})->name('dashboard');

Route::get('/Uregister', function () {
    return Inertia::render('register');
})->name('register');

Route::get('/Tran', function () {
    return Inertia::render('Transaction');
})->name('Tran');


require __DIR__.'/api.php';

