<?php

use Illuminate\Support\Facades\Route;
use Workbench\App\Http\Controllers\ProfileController;

Route::get('/', [ProfileController::class, 'create'])->name('workbench.profiles.create');
Route::get('/edit', [ProfileController::class, 'edit'])->name('workbench.profiles.edit');
Route::post('/profiles', [ProfileController::class, 'store'])
    ->middleware('precognitive')
    ->name('workbench.profiles.store');
Route::patch('/profiles/{profile}', [ProfileController::class, 'update'])
    ->middleware('precognitive')
    ->name('workbench.profiles.update');
Route::get('/options/skills', [ProfileController::class, 'skills'])->name('workbench.skills.index');
