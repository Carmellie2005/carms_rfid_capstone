<?php

use App\Http\Controllers\Api\RfidScanController;
use App\Http\Controllers\Api\RfidHeartbeatController;
use App\Http\Controllers\Api\RfidEnrollmentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::match(['get', 'post'], '/rfid-scan', RfidScanController::class)->name('api.rfid-scan');
Route::match(['get', 'post'], '/rfid-enrollment', RfidEnrollmentController::class)->name('api.rfid-enrollment');
Route::match(['get', 'post'], '/rfid-heartbeat', RfidHeartbeatController::class)->name('api.rfid-heartbeat');
