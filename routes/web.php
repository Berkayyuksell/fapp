<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\SettingsController;



Route::get('/', [InvoiceController::class, 'indexOutgoing'])->name('home');

Route::prefix('invoices')->name('invoices.')->group(function () {
 
    Route::get('/outgoing', [InvoiceController::class, 'indexOutgoing'])->name('outgoing');

    Route::get('/incoming', [InvoiceController::class, 'indexIncoming'])->name('incoming');

    Route::post('/sync', [InvoiceController::class, 'sync'])->name('sync');

    Route::get('/{id}', [InvoiceController::class, 'show'])->name('show')->where('id', '[0-9]+');
    
    Route::get('/html/{uuid}', [InvoiceController::class, 'showHtml'])->name('html');
});


Route::prefix('settings')->name('settings.')->group(function () {
    Route::get('/', [SettingsController::class, 'index'])->name('index');
    Route::post('/', [SettingsController::class, 'update'])->name('update');
});

