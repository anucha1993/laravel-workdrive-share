<?php

use App\Http\Controllers\ShareFolderController as C;
use App\Http\Controllers\ZohoAuthController;
use Illuminate\Support\Facades\Route;

// Login ด้วย Zoho — ลิงก์ที่เอาไปแปะใน Zoho ได้: https://โดเมนของคุณ/auth/zoho
Route::middleware('web')->group(function () {
    Route::get('/login', fn () => redirect()->route('auth.zoho'))->name('login'); // ให้ middleware auth เด้งมาที่ Zoho
    Route::get('/auth/zoho', [ZohoAuthController::class, 'redirect'])->name('auth.zoho');
    Route::get('/auth/zoho/callback', [ZohoAuthController::class, 'callback'])->name('auth.zoho.callback');
    Route::post('/logout', [ZohoAuthController::class, 'logout'])->name('logout');
});

// ใส่ใน routes/web.php:  require __DIR__.'/workdrive.php';
// แนะนำให้ครอบด้วย middleware 'auth' เพื่อให้เฉพาะพนักงานเข้าได้

Route::middleware(['web', 'auth'])->prefix('workdrive')->name('workdrive.')->group(function () {
    Route::get('/', [C::class, 'index'])->name('index');
    Route::post('/', [C::class, 'store'])->name('store');
    Route::get('/template', [C::class, 'template'])->name('template');
    Route::get('/{folder}', [C::class, 'show'])->name('show');
    Route::delete('/{folder}', [C::class, 'destroy'])->name('destroy');
    Route::post('/{folder}/import', [C::class, 'import'])->name('import');
    Route::post('/{folder}/picks', [C::class, 'addPick'])->name('picks.add');
    Route::delete('/{folder}/picks/{pick}', [C::class, 'removePick'])->name('picks.remove');
    Route::post('/{folder}/sync', [C::class, 'sync'])->name('sync');
    Route::post('/{folder}/share-email', [C::class, 'shareEmail'])->name('share.email');
    Route::post('/{folder}/link', [C::class, 'createLink'])->name('share.link');
});
