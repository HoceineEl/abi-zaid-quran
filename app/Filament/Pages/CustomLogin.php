<?php

namespace App\Filament\Pages;

use Filament\Pages\Auth\Login;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Filament\Facades\Filament;

class CustomLogin extends Login
{
    public function getHeading(): string | Htmlable
    {
        $panel = Filament::getCurrentPanel();

        $teacherLoginLink = '';
        if ($panel->getId() === 'association') {
            $teacherLoginLink = '<a href="/teacher" class="flex items-center gap-2 text-lg text-info-600 hover:bg-info-50 hover:text-info-700 dark:text-info-500 dark:hover:bg-info-950 dark:hover:text-info-400 px-4 py-2 rounded-lg transition-colors duration-200">' .
                '<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>' .
                'تسجيل دخول المعلمين' .
                '</a>';
        }

        return new HtmlString(
            '<div class="flex flex-col items-center gap-2 ">' .
                '<h2 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white flex items-center gap-3">' .
                '<svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-primary-600 dark:text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                </svg>' .
                __('filament-panels::pages/auth/login.heading') .
                '</h2>' .
                '<div class="flex flex-col gap-3 w-full">' .
                '<a href="/" class="flex items-center gap-2 text-lg text-primary-600 hover:bg-primary-50 hover:text-primary-700 dark:text-primary-500 dark:hover:bg-primary-950 dark:hover:text-primary-400 px-4 py-2 rounded-lg transition-colors duration-200">' .
                '<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>' .
                'الرجوع إلى الصفحة الرئيسية' .
                '</a>' .
                $teacherLoginLink .
                '</div>'
        );
    }
}
