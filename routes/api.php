<?php

use App\Http\Controllers\Api\ApplicantAuthController;
use App\Http\Controllers\Api\ApplicantController;
use App\Http\Controllers\Api\ApplicantDashboardController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admission Application API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // ── Public routes ────────────────────────────────────────────────────

    // Admin auth
    Route::post('auth/login',  [AuthController::class, 'login'])->name('auth.login');

    // Applicant self-service: submit application
    Route::post('applicants', [ApplicantController::class, 'store'])->name('applicants.store');

    // Applicant self-service: status lookup (no token required)
    Route::get('applicants/lookup', [ApplicantController::class, 'lookup'])->name('applicants.lookup');

    // Applicant auth: login
    Route::post('applicant/auth/login', [ApplicantAuthController::class, 'login'])->name('applicant.auth.login');

    // ── Applicant protected routes ────────────────────────────────────────
    Route::middleware('auth:sanctum')->prefix('applicant')->group(function () {
        Route::post('auth/logout',  [ApplicantAuthController::class, 'logout'])->name('applicant.auth.logout');
        Route::get('auth/me',       [ApplicantAuthController::class, 'me'])->name('applicant.auth.me');
        Route::get('dashboard',     [ApplicantDashboardController::class, 'application'])->name('applicant.dashboard');
        Route::get('status',        [ApplicantDashboardController::class, 'status'])->name('applicant.status');
        Route::get('print',         [ApplicantDashboardController::class, 'print'])->name('applicant.print');
        Route::post('passport',     [ApplicantDashboardController::class, 'uploadPassport'])->name('applicant.passport.upload');
    });

    // ── Admin protected routes (require Sanctum token) ───────────────────
    Route::middleware('auth:sanctum')->group(function () {

        // Admin auth
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('auth/me',     [AuthController::class, 'me'])->name('auth.me');

        // Dashboard
        Route::get('dashboard/stats', [DashboardController::class, 'stats'])->name('dashboard.stats');

        // Applicants (admin-only: list, view, update, delete, print)
        Route::get('applicants',              [ApplicantController::class, 'index'])->name('applicants.index');
        Route::get('applicants/{id}',         [ApplicantController::class, 'show'])->name('applicants.show');
        Route::put('applicants/{id}',         [ApplicantController::class, 'update'])->name('applicants.update');
        Route::patch('applicants/{id}',       [ApplicantController::class, 'update']);
        Route::delete('applicants/{id}',      [ApplicantController::class, 'destroy'])->name('applicants.destroy');
        Route::get('applicants/{id}/print',   [ApplicantController::class, 'print'])->name('applicants.print');
    });
});
