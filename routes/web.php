<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');


// Route::redirect('/quran-program', '/quran-program')->name('quran-program');
// Route::redirect('/association', '/association')->name('association');

use App\Livewire\MemorizerQrCode;
use Illuminate\Http\Request;

Route::get('/memorizer-qr-code', MemorizerQrCode::class)->name('memorizer.qr-code');


