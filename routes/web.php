<?php

use App\Models\Memorizer;
use App\Models\Student;
use Illuminate\Support\Facades\Route;
use App\Models\ReminderLog;

Route::view('/', 'welcome');

// Route::redirect('/quran-program', '/quran-program')->name('quran-program');
// Route::redirect('/association', '/association')->name('association');

Route::middleware('auth')->group(function () {

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
        $memorizer = Memorizer::with('reminderLogs')->find($memorizer_id);
        // Create reminder log
        $memorizer->reminderLogs()->create([
            'type' => 'payment',
            'phone_number' => $number,
            'message' => '',
            'is_parent' => true,
        ]);

        $message = urlencode($message);
        return view('redirects.whatsapp', ['number' => $number, 'message' => $message]);
    })->name('memorizer-whatsapp');



    Route::get('/send-absence-whatsapp/{number}/{message}/{memorizer_id}', function ($number, $message, $memorizer_id) {
        $message = urldecode($message);
        $memorizer = Memorizer::find($memorizer_id);
        // Create reminder log
        $memorizer->reminderLogs()->create([
            'type' => 'absence',
            'phone_number' => $number,
            'message' => '',
            'is_parent' => true,
        ]);
        dd($memorizer);
        $message = urlencode($message);
        return view('redirects.whatsapp', ['number' => $number, 'message' => $message]);
    })->name('memorizer-absence-whatsapp');

    Route::get('/send-trouble-whatsapp/{number}/{message}/{memorizer_id}', function ($number, $message, $memorizer_id) {
        $message = urldecode($message);
        $memorizer = Memorizer::find($memorizer_id);
        // Create reminder log
        $memorizer->reminderLogs()->create([
            'type' => 'trouble',
            'phone_number' => $number,
            'message' => '',
            'is_parent' => true,
        ]);

        $message = urlencode($message);
        return view('redirects.whatsapp', ['number' => $number, 'message' => $message]);
    })->name('memorizer-trouble-whatsapp');
    Route::get('/send-no_memorization-whatsapp/{number}/{message}/{memorizer_id}', function ($number, $message, $memorizer_id) {
        $message = urldecode($message);
        $memorizer = Memorizer::find($memorizer_id);
        // Create reminder log
        $memorizer->reminderLogs()->create([
            'type' => 'no_memorization',
            'phone_number' => $number,
            'message' => '',
            'is_parent' => true,
        ]);

        $message = urlencode($message);
        return view('redirects.whatsapp', ['number' => $number, 'message' => $message]);
    })->name('memorizer-no_memorization-whatsapp');
});
