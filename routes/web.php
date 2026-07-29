<?php

use App\Http\Controllers\Admin\ContentPostController as AdminContentPostController;
use App\Http\Controllers\Admin\CourtController as AdminCourtController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\OwnerApplicationController as AdminOwnerApplicationController;
use App\Http\Controllers\Admin\ReviewController as AdminReviewController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\CourtDirectoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Owner\BookingController as OwnerBookingController;
use App\Http\Controllers\Owner\CourtController as OwnerCourtController;
use App\Http\Controllers\Owner\DashboardController as OwnerDashboardController;
use App\Http\Controllers\Owner\ReportController as OwnerReportController;
use App\Http\Controllers\OwnerApplicationController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PlayerBookingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicContentController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\WaitlistController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/courts', [CourtDirectoryController::class, 'index'])->name('courts.index');
Route::get('/courts/{court}', [CourtDirectoryController::class, 'show'])->name('courts.show');
Route::get('/courts/{court}/availability', AvailabilityController::class)->name('courts.availability');
Route::get('/updates', [PublicContentController::class, 'index'])->name('content.index');
Route::get('/updates/{post:slug}', [PublicContentController::class, 'show'])->name('content.show');

Route::middleware(['auth', 'verified', 'active'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/bookings', [PlayerBookingController::class, 'index'])->name('bookings.index');
    Route::post('/courts/{court}/bookings', [PlayerBookingController::class, 'store'])->name('bookings.store');
    Route::get('/bookings/{booking}', [PlayerBookingController::class, 'show'])->name('bookings.show');
    Route::patch('/bookings/{booking}/cancel', [PlayerBookingController::class, 'cancel'])->name('bookings.cancel');
    Route::post('/bookings/{booking}/payments', [PaymentController::class, 'store'])->name('payments.store');
    Route::get('/payments/{payment}/proof', [PaymentController::class, 'download'])->name('payments.proof');
    Route::post('/bookings/{booking}/review', [ReviewController::class, 'store'])->name('reviews.store');
    Route::post('/courts/{court}/favorite', [FavoriteController::class, 'store'])->name('favorites.store');
    Route::delete('/courts/{court}/favorite', [FavoriteController::class, 'destroy'])->name('favorites.destroy');
    Route::post('/courts/{court}/waitlist', [WaitlistController::class, 'store'])->name('waitlist.store');
    Route::delete('/waitlist/{waitlist}', [WaitlistController::class, 'destroy'])->name('waitlist.destroy');
    Route::post('/owner-application', [OwnerApplicationController::class, 'store'])->name('owner-applications.store');
    Route::get('/notifications/{notification}', [NotificationController::class, 'read'])->name('notifications.read');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'verified', 'active', 'role:owner,admin'])
    ->prefix('owner')
    ->name('owner.')
    ->group(function () {
        Route::get('/', OwnerDashboardController::class)->name('dashboard');
        Route::get('/courts', [OwnerCourtController::class, 'index'])->name('courts.index');
        Route::get('/courts/create', [OwnerCourtController::class, 'create'])->name('courts.create');
        Route::post('/courts', [OwnerCourtController::class, 'store'])->name('courts.store');
        Route::get('/courts/{court}/edit', [OwnerCourtController::class, 'edit'])->name('courts.edit');
        Route::put('/courts/{court}', [OwnerCourtController::class, 'update'])->name('courts.update');
        Route::get('/courts/{court}/manage', [OwnerCourtController::class, 'manage'])->name('courts.manage');
        Route::patch('/courts/{court}/archive', [OwnerCourtController::class, 'archive'])->name('courts.archive');
        Route::patch('/courts/{court}/submit', [OwnerCourtController::class, 'submitForVerification'])->name('courts.submit');

        Route::post('/courts/{court}/photos', [OwnerCourtController::class, 'storePhoto'])->name('courts.photos.store');
        Route::delete('/courts/{court}/photos/{photo}', [OwnerCourtController::class, 'destroyPhoto'])->name('courts.photos.destroy');
        Route::post('/courts/{court}/units', [OwnerCourtController::class, 'storeUnit'])->name('courts.units.store');
        Route::delete('/courts/{court}/units/{unit}', [OwnerCourtController::class, 'destroyUnit'])->name('courts.units.destroy');
        Route::put('/courts/{court}/hours', [OwnerCourtController::class, 'updateHours'])->name('courts.hours.update');
        Route::post('/courts/{court}/schedules', [OwnerCourtController::class, 'storeSchedule'])->name('courts.schedules.store');
        Route::delete('/courts/{court}/schedules/{schedule}', [OwnerCourtController::class, 'destroySchedule'])->name('courts.schedules.destroy');
        Route::post('/courts/{court}/blackouts', [OwnerCourtController::class, 'storeBlackout'])->name('courts.blackouts.store');
        Route::delete('/courts/{court}/blackouts/{blackout}', [OwnerCourtController::class, 'destroyBlackout'])->name('courts.blackouts.destroy');
        Route::post('/courts/{court}/payment-methods', [OwnerCourtController::class, 'storePaymentMethod'])->name('courts.payment-methods.store');
        Route::delete('/courts/{court}/payment-methods/{method}', [OwnerCourtController::class, 'destroyPaymentMethod'])->name('courts.payment-methods.destroy');
        Route::post('/courts/{court}/verifications', [OwnerCourtController::class, 'storeVerification'])->name('courts.verifications.store');

        Route::get('/bookings', [OwnerBookingController::class, 'index'])->name('bookings.index');
        Route::patch('/bookings/{booking}', [OwnerBookingController::class, 'update'])->name('bookings.update');
        Route::patch('/payments/{payment}/verify', [OwnerBookingController::class, 'verifyPayment'])->name('payments.verify');
        Route::patch('/payments/{payment}/reject', [OwnerBookingController::class, 'rejectPayment'])->name('payments.reject');
        Route::get('/reports', [OwnerReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/export', [OwnerReportController::class, 'export'])->name('reports.export');
    });

Route::middleware(['auth', 'verified', 'active', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', AdminDashboardController::class)->name('dashboard');
        Route::get('/courts', [AdminCourtController::class, 'index'])->name('courts.index');
        Route::patch('/courts/{court}/publish', [AdminCourtController::class, 'publish'])->name('courts.publish');
        Route::patch('/courts/{court}/archive', [AdminCourtController::class, 'archive'])->name('courts.archive');
        Route::patch('/courts/{court}/feature', [AdminCourtController::class, 'feature'])->name('courts.feature');
        Route::patch('/verifications/{verification}/accept', [AdminCourtController::class, 'acceptVerification'])->name('verifications.accept');
        Route::patch('/verifications/{verification}/reject', [AdminCourtController::class, 'rejectVerification'])->name('verifications.reject');
        Route::get('/verifications/{verification}/evidence', [AdminCourtController::class, 'downloadEvidence'])->name('verifications.evidence');

        Route::get('/owner-applications', [AdminOwnerApplicationController::class, 'index'])->name('owner-applications.index');
        Route::patch('/owner-applications/{ownerApplication}', [AdminOwnerApplicationController::class, 'update'])->name('owner-applications.update');
        Route::get('/owner-applications/{ownerApplication}/evidence', [AdminOwnerApplicationController::class, 'download'])->name('owner-applications.evidence');
        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::patch('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
        Route::get('/reviews', [AdminReviewController::class, 'index'])->name('reviews.index');
        Route::patch('/reviews/{review}', [AdminReviewController::class, 'update'])->name('reviews.update');
        Route::get('/content', [AdminContentPostController::class, 'index'])->name('content.index');
        Route::post('/content', [AdminContentPostController::class, 'store'])->name('content.store');
        Route::put('/content/{post}', [AdminContentPostController::class, 'update'])->name('content.update');
        Route::delete('/content/{post}', [AdminContentPostController::class, 'destroy'])->name('content.destroy');
    });

require __DIR__.'/auth.php';
