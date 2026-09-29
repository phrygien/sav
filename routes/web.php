<?php

use Illuminate\Support\Facades\Route;

Route::livewire('/auth-login', 'pages::auth.login-api')->name('auth.login');
Route::middleware(['jwt-session-auth'])->group(function () {
    Route::view('/', 'dashboard')->name('home');
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::livewire('kanban', 'pages::kanban')->name('kanban');
    Route::livewire('/kanban/details/{ticket}', 'pages::kanban.details')->name('kanban.details');

    // Retour et retractation
    Route::livewire('/kanban-retour-retractation', 'pages::kanban-retour')->name('kanban.retour.retractation');
    Route::livewire('/kanban-changement-adresse', 'pages::kanban-changement-adresse')->name('kanban.changement.adresse');
    Route::livewire('/kanban-invertion-colis', 'pages::kanban-invertion-colis')->name('kanban.invertion.colis');
    Route::livewire('/tiket-redondant', 'pages::tiket-redondant')->name('tiket.redondant');
});

require __DIR__.'/settings.php';
