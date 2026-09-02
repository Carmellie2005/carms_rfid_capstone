<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\System\AuditLogController;
use App\Http\Controllers\System\CheckpointController;
use App\Http\Controllers\System\DashboardController;
use App\Http\Controllers\System\GuardController;
use App\Http\Controllers\System\GuardPatrolController;
use App\Http\Controllers\System\IncidentReportController;
use App\Http\Controllers\System\NotificationController;
use App\Http\Controllers\System\NotificationReadController;
use App\Http\Controllers\System\PatrolLogController;
use App\Http\Controllers\System\ReaderStatusController;
use App\Http\Controllers\System\ReportController;
use App\Http\Controllers\System\ScanIssueController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    if (! auth()->check()) {
        return view('welcome');
    }

    return auth()->user()->role === 'guard'
        ? redirect()->route('patrol.scan')
        : redirect()->route('dashboard');
});

Route::middleware('auth')->group(function () {
    Route::get('/patrol/scan', [GuardPatrolController::class, 'create'])->name('patrol.scan');
    Route::get('/patrol/pending-scan', [GuardPatrolController::class, 'pendingScan'])->name('patrol.pending-scan');
    Route::post('/patrol/verify-face', [GuardPatrolController::class, 'verifyFace'])->name('patrol.verify-face');
    Route::post('/patrol/scan', [GuardPatrolController::class, 'store'])->name('patrol.store');
    Route::get('/patrol-logs/pdf', [PatrolLogController::class, 'downloadPdf'])->name('patrol-logs.pdf');
    Route::get('/patrol-logs', [PatrolLogController::class, 'index'])->name('patrol-logs.index');
    Route::get('/incidents/{incidentReport}/pdf', [IncidentReportController::class, 'downloadPdf'])->name('incidents.pdf');
    Route::get('/notifications', NotificationController::class)->name('notifications.index');
    Route::post('/notifications/read', [NotificationReadController::class, 'store'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationReadController::class, 'storeAll'])->name('notifications.read-all');

    Route::middleware('supervisor')->group(function () {
        Route::get('/dashboard', DashboardController::class)->middleware('verified')->name('dashboard');
        Route::get('/guards/{guard}/records', [GuardController::class, 'records'])->name('guards.records');
        Route::resource('guards', GuardController::class)->except(['show']);
        Route::resource('checkpoints', CheckpointController::class)->except(['show']);
        Route::get('/incidents', [IncidentReportController::class, 'index'])->name('incidents.index');
        Route::patch('/incidents/{incidentReport}', [IncidentReportController::class, 'update'])->name('incidents.update');
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reader-status', [ReaderStatusController::class, 'index'])->name('readers.index');
        Route::get('/scan-issues', [ScanIssueController::class, 'index'])->name('scan-issues.index');
        Route::get('/audit-trail/pdf', [AuditLogController::class, 'downloadPdf'])->name('audit-logs.pdf');
        Route::get('/audit-trail', [AuditLogController::class, 'index'])->name('audit-logs.index');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
