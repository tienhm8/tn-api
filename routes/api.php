<?php

use App\Http\Controllers\Api\V1\AppointmentController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\ServiceController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (prefix: /api/v1)
|--------------------------------------------------------------------------
*/

Route::get('/ping', fn () => response()->json([
    'message' => 'pong',
    'service' => 'tn-api',
]));

Route::prefix('auth')->group(function (): void {
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth.jwt')->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

Route::middleware('auth.jwt')->group(function (): void {
    // Danh mục dịch vụ
    Route::get('services', [ServiceController::class, 'index']);

    // Danh sách user theo role (admin — để gán lại khách)
    Route::get('users', [UserController::class, 'index'])->middleware('role:admin');

    // Khách hàng
    Route::get('customers', [CustomerController::class, 'index']);
    Route::post('customers', [CustomerController::class, 'store'])->middleware('permission:customers.create');
    Route::post('customers/import', [CustomerController::class, 'import'])->middleware('permission:customers.import');
    Route::get('customers/import/template', [CustomerController::class, 'template'])->middleware('permission:customers.import');
    Route::get('customers/{customer}', [CustomerController::class, 'show']);
    Route::put('customers/{customer}', [CustomerController::class, 'update']);
    Route::delete('customers/{customer}', [CustomerController::class, 'destroy']);
    Route::post('customers/{customer}/reassign', [CustomerController::class, 'reassign'])->middleware('permission:customers.reassign');
    Route::post('customers/{customer}/status', [CustomerController::class, 'changeStatus']);
    Route::post('customers/{customer}/notes', [CustomerController::class, 'addNote']);

    // Lịch chăm sóc / gọi lại
    Route::get('appointments', [AppointmentController::class, 'index']);
    Route::post('appointments', [AppointmentController::class, 'store'])->middleware('permission:appointments.manage');
    Route::put('appointments/{appointment}', [AppointmentController::class, 'update'])->middleware('permission:appointments.manage');
    Route::post('appointments/{appointment}/complete', [AppointmentController::class, 'complete'])->middleware('permission:appointments.manage');
    Route::post('appointments/{appointment}/cancel', [AppointmentController::class, 'cancel'])->middleware('permission:appointments.manage');
});
