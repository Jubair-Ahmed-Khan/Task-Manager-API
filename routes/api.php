<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\EmployeeController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {

    Route::middleware('role:Employee')->group(function () {
        Route::patch('/tasks/{task}/status', [TaskController::class, 'updateStatus']);

    });

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::apiResource('tasks', TaskController::class);
    Route::patch('tasks/{task}/status', [TaskController::class, 'updateStatus']);
    Route::get( '/users/employees', [ UserController::class, 'employees', ] );
    Route::get('/employees', [ EmployeeController::class, 'index' ])->middleware('role:Admin');
    Route::get('/employees/performance', [EmployeeController::class, 'performance'])->middleware('role:Admin');
});