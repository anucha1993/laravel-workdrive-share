<?php

use App\Http\Controllers\PublicShareController;
use App\Http\Controllers\ShareGroupController as C;
use App\Http\Controllers\ZohoAuthController;
use App\Http\Middleware\BreakOutOfIframe;
use Illuminate\Support\Facades\Route;

// Login ด้วย Zoho — ลิงก์ที่เอาไปแปะใน Zoho ได้: https://www.workdrive.trackingmouthefirst.com/auth/zoho
Route::middleware(['web', BreakOutOfIframe::class])->group(function () {
    Route::get('/', fn () => redirect()->route('workdrive.index'));
    Route::get('/login', fn () => redirect()->route('auth.zoho'))->name('login'); // ให้ middleware auth เด้งมาที่ Zoho
    Route::get('/auth/zoho', [ZohoAuthController::class, 'redirect'])->name('auth.zoho');
    Route::get('/auth/zoho/callback', [ZohoAuthController::class, 'callback'])->name('auth.zoho.callback');
    Route::post('/logout', [ZohoAuthController::class, 'logout'])->name('logout');
});

// ลิงก์หลักสำหรับลูกค้า (หน้าสารบัญ ไม่ต้อง Login)
Route::middleware(['web', 'throttle:60,1'])->get('/s/{token}', [PublicShareController::class, 'show'])->name('public.share');

Route::middleware(['web', BreakOutOfIframe::class, 'auth'])->prefix('workdrive')->name('workdrive.')->group(function () {
    Route::get('/', [C::class, 'index'])->name('index');
    Route::post('/', [C::class, 'store'])->name('store');
    Route::get('/template', [C::class, 'template'])->name('template');
    Route::post('/companies/refresh', [C::class, 'refreshCompanies'])->name('companies.refresh');
    Route::get('/{group}', [C::class, 'show'])->name('show');
    Route::delete('/{group}', [C::class, 'destroy'])->name('destroy');
    Route::get('/{group}/history', [C::class, 'history'])->name('history');
    Route::post('/{group}/import', [C::class, 'import'])->name('import');
    Route::post('/{group}/picks', [C::class, 'addPick'])->name('picks.add');
    Route::delete('/{group}/picks/{pick}', [C::class, 'removePick'])->name('picks.remove');
    Route::post('/{group}/recipients', [C::class, 'addRecipients'])->name('recipients.add');
    Route::delete('/{group}/recipients/{recipient}', [C::class, 'removeRecipient'])->name('recipients.remove');
    Route::post('/{group}/apply', [C::class, 'apply'])->name('apply');
    Route::post('/{group}/summary', [C::class, 'resendSummary'])->name('summary');
    Route::post('/{group}/public-link', [C::class, 'regeneratePublicLink'])->name('public-link');
});
