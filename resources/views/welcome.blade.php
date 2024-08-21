<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="rtl">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title> جمعية إبن أبي زيد القيرواني </title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,600&display=swap" rel="stylesheet" />

    <!-- Styles -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>

<body class="font-sans antialiased dark:bg-black dark:text-white/50">
    <div class="relative flex items-center justify-center min-h-screen bg-gray-100 dark:bg-gray-900">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 text-center">
            <h1 class="text-3xl md:text-6xl font-bold mb-10 text-center text-gray-800 dark:text-white">
                أختر الخدمة التي ترغب في الوصول إليها
            </h1>
            <div class="flex flex-col sm:flex-row justify-center items-center gap-6 mx-4">
                <a href="{{ url('/association') }}"
                    class="w-full sm:w-1/3 p-6 bg-blue-500 hover:bg-blue-600 text-white text-2xl font-semibold rounded-lg shadow-lg transition duration-300">
                    إدارة الجمعية
                </a>
                <a href="{{ url('/quran-program') }}"
                    class="w-full sm:w-1/3 p-6 bg-green-500 hover:bg-green-600 text-white text-2xl font-semibold rounded-lg shadow-lg transition duration-300">
                    برامج القرآن الرقمية
                </a>
            </div>
        </div>
    </div>
</body>

</html>
