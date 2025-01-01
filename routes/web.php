<?php

use App\Models\Memorizer;
use App\Models\Student;
use Illuminate\Support\Facades\Route;
use App\Models\ReminderLog;

Route::view('/', 'welcome');

// Route::redirect('/quran-program', '/quran-program')->name('quran-program');
// Route::redirect('/association', '/association')->name('association');


Route::get('/whatsapp/{number}/{message}/{student_id}', function ($number, $message, $student_id) {
    $message = urlencode($message);
    $student = Student::with('progresses')->find($student_id);
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

Route::get('/send-reminder/{number}/{message}/{memorizer_id}', function ($number, $message, $memorizer_id) {
    $message = urldecode($message);
    $memorizer = Memorizer::with('reminderLogs', 'guardian')->find($memorizer_id);
    // Create reminder log
    $memorizer->reminderLogs()->create([
        'type' => 'payment',
        'phone_number' => $number,
        'message' => '',
        'is_parent' => !$memorizer->phone && $memorizer->guardian?->phone,
    ]);

    $message = urlencode($message);
    return view('redirects.whatsapp', ['number' => $number, 'message' => $message]);
})->name('memorizer-whatsapp');
