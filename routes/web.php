<?php

use Illuminate\Support\Facades\Route;

// Redirige les anciennes pages d'auth vers la page de connexion Cosmia
Route::redirect('/login', '/auth-login', 301);
Route::redirect('/register', '/auth-login', 301);
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

    // Manage Users
    Route::middleware('can:manage-access')->group(function () {
        Route::livewire('/users', 'pages::users.list')->name('users.list');
        Route::livewire('/users/create', 'pages::users.create')
            ->middleware('can:create-user')
            ->name('users.create');
        Route::livewire("/users/edit/{user}", 'pages::users.edit')->name('users.edit');
    });
});

require __DIR__.'/settings.php';
