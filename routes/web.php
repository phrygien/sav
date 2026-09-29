<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
Route::livewire('/auth-login', 'pages::auth.login-api')->name('auth.login');
Route::middleware(['auth'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::livewire('kanban', 'pages::kanban')->name('kanban');
    Route::livewire('/kanban/details/{ticket}', 'pages::kanban.details')->name('kanban.details');

    // Retour et retractation
    Route::livewire('/kanban-retour-retractation', 'pages::kanban-retour')->name('kanban.retour.retractation');
    Route::livewire('/kanban-retour-retractation', 'pages::kanban-retour')->name('kanban.retour.retractation');
    Route::livewire('/kanban-retour-retractation', 'pages::kanban-retour')->name('kanban.retour.retractation');
});

require __DIR__.'/settings.php';
