<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>جمعية إبن أبي زيد القيرواني</title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Google Fonts: Cairo (Modern replacement for Tajawal) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <!-- Custom Configuration for Tailwind -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        cairo: ['Cairo', 'sans-serif'],
                    },
                    animation: {
                        'float': 'float 6s ease-in-out infinite',
                        'pulse-slow': 'pulse 4s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                    },
                    keyframes: {
                        float: {
                            '0%, 100%': {
                                transform: 'translateY(0)'
                            },
                            '50%': {
                                transform: 'translateY(-20px)'
                            },
                        }
                    },
                    backgroundImage: {
                        'grid-white': "url(\"data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32' width='32' height='32' fill='none' stroke='rgb(255 255 255 / 0.05)'%3e%3cpath d='M0 .5H31.5V32'/%3e%3c/svg%3e\")",
                    }
                }
            }
        }
    </script>

    <style>
        body {
            font-family: 'Cairo', sans-serif;
            background-color: #0f172a;
            /* Slate 900 */
        }

        /* Spotlight Card Logic */
        .spotlight-card {
            position: relative;
            overflow: hidden;
            background: rgba(30, 41, 59, 0.4);
            /* Slate 800/40 */
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .spotlight-card::before {
            content: "";
            position: absolute;
            height: 100%;
            width: 100%;
            top: 0;
            left: 0;
            background: radial-gradient(800px circle at var(--mouse-x) var(--mouse-y),
                    rgba(255, 255, 255, 0.06),
                    transparent 40%);
            opacity: 0;
            transition: opacity 0.5s;
            pointer-events: none;
            z-index: 2;
        }

        .spotlight-card:hover::before {
            opacity: 1;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #0f172a;
        }

        ::-webkit-scrollbar-thumb {
            background: #334155;
            border-radius: 4px;
        }
    </style>
</head>

<body class="min-h-screen text-slate-200 selection:bg-blue-500/30 flex flex-col overflow-x-hidden">

    <!-- Background Effects -->
    <div class="fixed inset-0 z-0 pointer-events-none">
        <!-- Grid Pattern -->
        <div class="absolute inset-0 bg-grid-white/[0.02] bg-[size:50px_50px]"></div>
        <div class="absolute inset-0 bg-gradient-to-b from-slate-900/0 via-slate-900/80 to-slate-900 pointer-events-none"></div>

        <!-- Colorful Glows -->
        <div class="absolute top-[-10%] right-[-5%] w-[600px] h-[600px] bg-blue-600/20 rounded-full blur-[120px] animate-pulse-slow"></div>
        <div class="absolute bottom-[-10%] left-[-10%] w-[500px] h-[500px] bg-indigo-600/10 rounded-full blur-[120px] animate-pulse-slow" style="animation-delay: 2s;"></div>
    </div>

    <!-- Navbar (Optional for simple landing page, kept for UI completeness) -->
    <nav class="fixed top-0 left-0 right-0 z-50 border-b border-white/5 bg-slate-900/50 backdrop-blur-xl">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-center md:justify-between h-16">
                <div class="flex items-center gap-3">
                    <img src="{{ asset('logo.jpg') }}" alt="شعار الجمعية" class="h-10 w-10 rounded-full object-cover border-2 border-blue-500/30 shadow-lg" />
                    <span class="font-bold text-lg text-slate-200 hidden sm:block">بوابة الجمعية</span>
                </div>
                <div class="hidden md:flex items-center gap-4">
                    <span class="text-xs text-slate-500 font-mono">v2.0</span>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="relative z-10 flex-grow flex flex-col items-center justify-center pt-24 pb-12 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto w-full">

        <!-- Hero Section -->
        <div id="hero" class="text-center max-w-4xl mx-auto mb-20 relative opacity-0 translate-y-10 transition-all duration-1000 ease-out">

            <!-- Main Logo Animation -->
            <div class="relative mx-auto w-28 h-28 mb-10 animate-float group cursor-pointer">
                <div class="absolute inset-0 bg-blue-500/30 rounded-full blur-2xl group-hover:bg-blue-500/50 transition-all duration-500"></div>
                <div class="relative w-full h-full rounded-full border-2 border-blue-500/30 shadow-2xl ring-1 ring-white/10 overflow-hidden bg-white">
                    <img src="{{ asset('logo.jpg') }}" alt="شعار الجمعية" class="w-full h-full object-cover hover:scale-110 transition-transform duration-500">
                </div>
            </div>

            <!-- Hero Text -->
            <h1 class="text-4xl md:text-6xl font-black text-transparent bg-clip-text bg-gradient-to-b from-white via-slate-200 to-slate-500 tracking-tight leading-tight mb-6">
                مرحباً بكم في جمعية <br />
                <span class="text-blue-500 inline-block mt-2">إبن أبي زيد القيرواني</span>
            </h1>

            <p class="text-lg md:text-xl text-slate-400 max-w-2xl mx-auto font-light leading-relaxed">
                اختر الخدمة التي تريد الوصول إليها
            </p>
        </div>

        <!-- Cards Grid -->
        <div id="cards-grid" class="grid grid-cols-1 md:grid-cols-3 gap-6 w-full opacity-0 translate-y-10 transition-all duration-1000 delay-200 ease-out">

            <!-- Card 1: Association Management -->
            <!-- Link updated to /association -->
            <a href="/association" class="spotlight-card group rounded-3xl p-8 h-full transition-all duration-300 hover:-translate-y-1">
                <div class="relative z-10 flex flex-col h-full text-center md:text-right">
                    <div class="w-14 h-14 rounded-2xl bg-blue-500/10 border border-blue-500/20 flex items-center justify-center mb-6 group-hover:bg-blue-500/20 transition-colors mx-auto md:mx-0">
                        <!-- SVG from your code converted to Lucide equivalent for style consistency, or keep generic icon -->
                        <i data-lucide="building-2" class="w-7 h-7 text-blue-400"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-white mb-3 group-hover:text-blue-300 transition-colors">إدارة الجمعية</h3>
                    <p class="text-slate-400 text-sm leading-relaxed">الوصول إلى لوحة تحكم إدارة الجمعية</p>

                    <div class="mt-auto pt-8 flex items-center justify-center md:justify-start text-blue-400 text-sm font-medium opacity-0 group-hover:opacity-100 transition-all duration-300">
                        <span>دخول</span>
                        <i data-lucide="arrow-left" class="w-4 h-4 mr-2"></i>
                    </div>
                </div>
            </a>

            <!-- Card 2: Quran/Scientific Programs -->
            <!-- Link updated to /quran-program -->
            <a href="/quran-program" class="spotlight-card group rounded-3xl p-8 h-full transition-all duration-300 hover:-translate-y-1">
                <div class="relative z-10 flex flex-col h-full text-center md:text-right">
                    <div class="w-14 h-14 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center mb-6 group-hover:bg-emerald-500/20 transition-colors mx-auto md:mx-0">
                        <i data-lucide="book-open" class="w-7 h-7 text-emerald-400"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-white mb-3 group-hover:text-emerald-300 transition-colors">البرامج العلمية الرقمية</h3>
                    <p class="text-slate-400 text-sm leading-relaxed">الوصول إلى منصة البرامج التعليمية</p>

                    <div class="mt-auto pt-8 flex items-center justify-center md:justify-start text-emerald-400 text-sm font-medium opacity-0 group-hover:opacity-100 transition-all duration-300">
                        <span>دخول</span>
                        <i data-lucide="arrow-left" class="w-4 h-4 mr-2"></i>
                    </div>
                </div>
            </a>

            <!-- Card 3: Teachers -->
            <!-- Link updated to /teacher -->
            <a href="/teacher" class="spotlight-card group rounded-3xl p-8 h-full transition-all duration-300 hover:-translate-y-1">
                <div class="relative z-10 flex flex-col h-full text-center md:text-right">
                    <div class="w-14 h-14 rounded-2xl bg-purple-500/10 border border-purple-500/20 flex items-center justify-center mb-6 group-hover:bg-purple-500/20 transition-colors mx-auto md:mx-0">
                        <i data-lucide="users" class="w-7 h-7 text-purple-400"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-white mb-3 group-hover:text-purple-300 transition-colors">المعلمين</h3>
                    <p class="text-slate-400 text-sm leading-relaxed">الوصول إلى لوحة تحكم المعلمين</p>

                    <div class="mt-auto pt-8 flex items-center justify-center md:justify-start text-purple-400 text-sm font-medium opacity-0 group-hover:opacity-100 transition-all duration-300">
                        <span>دخول</span>
                        <i data-lucide="arrow-left" class="w-4 h-4 mr-2"></i>
                    </div>
                </div>
            </a>

        </div>

    </main>

    <!-- Footer -->
    <footer class="relative z-10 w-full py-8 border-t border-white/5 bg-slate-900/80 backdrop-blur-md mt-auto">
        <div class="max-w-7xl mx-auto px-4 text-center text-sm text-slate-500">
            <p class="font-medium">جميع الحقوق محفوظة © <span id="year">2025</span> جمعية إبن أبي زيد القيرواني</p>
        </div>
    </footer>

    <!-- Scripts -->
    <script>
        // Initialize Icons
        lucide.createIcons();

        // Set current year dynamically (Replaces {{ date('Y') }})
        document.getElementById('year').textContent = new Date().getFullYear();

        // Animation Trigger on Load
        window.addEventListener('DOMContentLoaded', () => {
            const hero = document.getElementById('hero');
            const grid = document.getElementById('cards-grid');

            setTimeout(() => {
                hero.classList.remove('opacity-0', 'translate-y-10');
                hero.classList.add('opacity-100', 'translate-y-0');

                grid.classList.remove('opacity-0', 'translate-y-10');
                grid.classList.add('opacity-100', 'translate-y-0');
            }, 100);

            // Spotlight Effect Logic
            const cards = document.querySelectorAll('.spotlight-card');
            const handleMouseMove = (e) => {
                cards.forEach(card => {
                    const rect = card.getBoundingClientRect();
                    const x = e.clientX - rect.left;
                    const y = e.clientY - rect.top;
                    card.style.setProperty('--mouse-x', `${x}px`);
                    card.style.setProperty('--mouse-y', `${y}px`);
                });
            };
            document.getElementById('cards-grid').addEventListener('mousemove', handleMouseMove);
        });
    </script>
</body>

</html>