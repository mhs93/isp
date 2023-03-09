<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Dashboard\DashboardController;

// Route::get('/', function() {
//     return view('admin.dashboard');
// })

Route::get('/', [DashboardController::class, 'index'])->middleware('auth');
