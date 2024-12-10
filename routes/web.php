<?php

use App\Models\Student;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

// Route::redirect('/quran-program', '/quran-program')->name('quran-program');
// Route::redirect('/association', '/association')->name('association');


Route::get('/whatsapp/{number}/{message}/{student_id}', function ($number, $message, $student_id) {
    $message = urlencode($message);
    $student = Student::find($student_id);
    if ($student->progresses->where('date', now()->format('Y-m-d'))->count() == 0) {
        $student->progresses()->create([
            'created_by' => auth()->id(),
            'date' => now()->format('Y-m-d'),
            'page_id' => null,
            'lines_from' => null,
            'lines_to' => null,
            'status' => null,
        ]);
    }
    return view('redirects.whatsapp', ['number' => $number, 'message' => $message]);
})->name('whatsapp');
