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

<body class="bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-900 dark:to-gray-800 min-h-screen">
    <div class="flex flex-col min-h-screen">
        <header class="py-6 bg-white/80 dark:bg-gray-800/80 backdrop-blur-sm fixed w-full z-50">
            <div class="container mx-auto">
                <div class="flex justify-center">
                    <img src="{{ asset('logo.jpg') }}" alt="شعار الجمعية"
                        class="h-16 rounded-full shadow-lg hover:scale-105 transition-transform duration-300">
                </div>
            </div>
        </header>

        <main class="flex-grow container mx-auto px-4 pt-32 pb-16">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <h1
                    class="text-4xl md:text-5xl font-bold bg-gradient-to-r from-blue-600 to-indigo-600 dark:from-blue-400 dark:to-indigo-400 bg-clip-text text-transparent pb-6">
                    مرحباً بكم في جمعية إبن أبي زيد القيرواني
                </h1>
                <p class="text-lg text-gray-600 dark:text-gray-300">
                    اختر الخدمة التي تريد الوصول إليها
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-6xl mx-auto">
                <a href="{{ url('/association') }}" class="group">
                    <div
                        class="bg-white dark:bg-gray-800 rounded-2xl p-8 shadow-lg hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-2">
                        <div
                            class="bg-gradient-to-br from-blue-500 to-indigo-600 text-white p-4 rounded-xl mb-6 inline-block">
                            <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4">
                                </path>
                            </svg>
                        </div>
                        <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-3">إدارة الجمعية</h2>
                        <p class="text-gray-600 dark:text-gray-300">الوصول إلى لوحة تحكم إدارة الجمعية</p>
                    </div>
                </a>

                <a href="{{ url('/quran-program') }}" class="group">
                    <div
                        class="bg-white dark:bg-gray-800 rounded-2xl p-8 shadow-lg hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-2">
                        <div
                            class="bg-gradient-to-br from-emerald-500 to-green-600 text-white p-4 rounded-xl mb-6 inline-block">
                            <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253">
                                </path>
                            </svg>
                        </div>
                        <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-3">البرامج العلمية الرقمية</h2>
                        <p class="text-gray-600 dark:text-gray-300">الوصول إلى منصة البرامج التعليمية</p>
                    </div>
                </a>

                <a href="{{ url('/teacher') }}" class="group">
                    <div
                        class="bg-white dark:bg-gray-800 rounded-2xl p-8 shadow-lg hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-2">
                        <div
                            class="bg-gradient-to-br from-purple-500 to-pink-600 text-white p-4 rounded-xl mb-6 inline-block">
                            <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z">
                                </path>
                            </svg>
                        </div>
                        <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-3">المعلمين</h2>
                        <p class="text-gray-600 dark:text-gray-300">الوصول إلى لوحة تحكم المعلمين</p>
                    </div>
                </a>
            </div>
        </main>

        <footer class="bg-white/80 dark:bg-gray-800/80 backdrop-blur-sm py-6">
            <div class="container mx-auto text-center">
                <p class="text-gray-600 dark:text-gray-400">
                    جميع الحقوق محفوظة © {{ date('Y') }} جمعية إبن أبي زيد القيرواني
                </p>
            </div>
        </footer>
    </div>
</body>

</html>
