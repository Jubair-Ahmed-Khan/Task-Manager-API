<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\TaskCommentController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\TaskActivityController;
use App\Http\Controllers\TaskCategoryController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\TaskAttachmentController;


Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {

    // Route::middleware('role:Employee')->group(function () {
    //     Route::patch('/tasks/{task}/status', [TaskController::class, 'updateStatus']);

    // });

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::apiResource('tasks', TaskController::class);
    Route::patch('tasks/{task}/status', [TaskController::class, 'updateStatus']);
    Route::get('/tasks/{task}/comments', [TaskCommentController::class, 'index']);
    Route::post('/tasks/{task}/comments', [TaskCommentController::class, 'store']);
    Route::delete('/comments/{comment}', [TaskCommentController::class, 'destroy']);
    Route::get( '/users/employees', [ UserController::class, 'employees']);
    Route::get('/employees', [ EmployeeController::class, 'index' ])->middleware('role:Admin');
    Route::get('/employees/performance', [EmployeeController::class, 'performance'])->middleware('role:Admin');

    Route::get('/tasks/{task}/attachments', [TaskAttachmentController::class, 'index']);
    Route::post('/tasks/{task}/attachments', [TaskAttachmentController::class, 'store']);
    Route::get('/task-attachments/{attachment}/download', [TaskAttachmentController::class, 'download']);
    Route::delete('/task-attachments/{attachment}', [TaskAttachmentController::class, 'destroy']);
    Route::get('/tasks/{task}/activities', [TaskActivityController::class, 'index']);

    Route::get('/task-categories', [TaskCategoryController::class, 'index']);
    Route::middleware('role:Admin')->group(function () {
        Route::post('/task-categories', [TaskCategoryController::class, 'store']);
        Route::get('/task-categories/{taskCategory}', [TaskCategoryController::class, 'show']);
        Route::put('/task-categories/{taskCategory}', [TaskCategoryController::class, 'update']);
        Route::delete('/task-categories/{taskCategory}', [TaskCategoryController::class, 'destroy']);
    });
});