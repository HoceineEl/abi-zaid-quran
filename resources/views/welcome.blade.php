<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>جمعية إبن أبي زيد القيرواني</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>

    @laravelPWA
</head>
<body class="bg-gray-100 dark:bg-gray-900 text-center">
<div class="min-h-screen flex flex-col justify-between">
    <header class="bg-white dark:bg-gray-800 shadow py-6">
        <div class="container mx-auto flex items-center justify-center">
            <img src="{{ asset('logo.jpg') }}" alt="شعار الجمعية" class="h-16 sm:h-20 rounded-full">
        </div>
    </header>

    <main class="flex-grow container mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="text-center mb-8 sm:mb-16">
            <h1 class="text-3xl sm:text-4xl md:text-5xl font-bold text-gray-800 dark:text-white mb-4">
                مرحباً بكم في جمعية إبن أبي زيد القيرواني
            </h1>
            <p class="text-base sm:text-lg md:text-xl text-gray-600 dark:text-gray-300">
                اختر الخدمة التي تريد الوصول إليها
            </p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 sm:gap-8">
            <a href="{{ url('/association') }}" class="group relative block overflow-hidden rounded-xl shadow-lg transition-all duration-300 hover:shadow-2xl hover:-translate-y-1">
                <div class="absolute inset-0 bg-gradient-to-r from-blue-500 to-indigo-600 opacity-75 transition-opacity duration-300 group-hover:opacity-100"></div>
                <div class="relative p-6 sm:p-8 flex flex-col items-center">
                    <svg class="w-12 h-12 sm:w-16 sm:h-16 text-white mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                    </svg>
                    <h2 class="text-xl sm:text-2xl font-bold text-white mb-2">إدارة الجمعية</h2>
                    <p class="text-blue-100 text-center text-sm sm:text-base">الوصول إلى لوحة تحكم إدارة الجمعية</p>
                </div>
            </a>

            <a href="{{ url('/quran-program') }}" class="group relative block overflow-hidden rounded-xl shadow-lg transition-all duration-300 hover:shadow-2xl hover:-translate-y-1">
                <div class="absolute inset-0 bg-gradient-to-r from-green-500 to-teal-600 opacity-75 transition-opacity duration-300 group-hover:opacity-100"></div>
                <div class="relative p-6 sm:p-8 flex flex-col items-center">
                    <svg class="w-12 h-12 sm:w-16 sm:h-16 text-white mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                    </svg>
                    <h2 class="text-xl sm:text-2xl font-bold text-white mb-2">البرامج العلمية الرقمية</h2>
                    <p class="text-green-100 text-center text-sm sm:text-base">الوصول إلى منصة البرامج التعليمية</p>
                </div>
            </a>
        </div>
    </main>

    <footer class="bg-gray-200 dark:bg-gray-800 py-6 mt-12">
        <div class="container mx-auto text-center text-gray-600 dark:text-gray-400">
            <p>جميع الحقوق محفوظة © {{ date('Y') }} جمعية إبن أبي زيد القيرواني</p>
        </div>
    </footer>
</div>
</body>
</html>
