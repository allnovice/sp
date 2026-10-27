<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\PropertyColumnController;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

Route::get('/properties', [PropertyController::class, 'index'])
    ->name('properties.index');

Route::post('/property-columns', [PropertyColumnController::class, 'store'])
    ->name('property-columns.store');

Route::patch('/property-columns/{propertyColumn}', [PropertyColumnController::class, 'update'])
    ->name('property-columns.update');

Route::delete('/property-columns/{propertyColumn}', [PropertyColumnController::class, 'destroy'])
    ->name('property-columns.destroy');

Route::get('/properties/template', [PropertyController::class, 'template'])
    ->name('properties.template');

Route::post('/property-columns/reorder', [PropertyColumnController::class, 'reorder'])
    ->name('property-columns.reorder');

Route::post('/properties/import', [PropertyController::class, 'import'])
    ->name('properties.import');

Route::delete('/properties/{property}', [PropertyController::class, 'destroy'])
    ->name('properties.destroy');

Route::patch('/properties/{property}', [PropertyController::class, 'update'])
    ->name('properties.update');

require __DIR__.'/settings.php';
