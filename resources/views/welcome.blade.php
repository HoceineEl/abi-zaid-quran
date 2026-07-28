<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>جمعية إبن أبي زيد القيرواني</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Cairo:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        :root {
            --paper: #faf9f5;
            --ink: #14191c;
            --muted: #6b7280;
            --line: #e4e1d8;
            --green: #145c43;
        }

        html,
        body {
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
            background: var(--paper);
            color: var(--ink);
            font-family: 'Cairo', system-ui, sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        .shell {
            width: 100%;
            max-width: 40rem;
            margin: 0 auto;
            padding: 0 1.5rem;
        }

        header {
            border-bottom: 1px solid var(--line);
        }

        .bar {
            display: flex;
            align-items: center;
            gap: .625rem;
            height: 4rem;
        }

        .bar img {
            width: 2rem;
            height: 2rem;
            border-radius: 999px;
            object-fit: cover;
        }

        .bar span {
            font-size: .875rem;
            font-weight: 600;
            letter-spacing: .01em;
        }

        main {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 3.5rem 0;
        }

        h1 {
            font-family: 'Amiri', serif;
            font-weight: 700;
            font-size: clamp(2rem, 6vw, 2.875rem);
            line-height: 1.4;
            margin: 0;
        }

        .lede {
            margin: .875rem 0 0;
            max-width: 34ch;
            font-size: 1rem;
            font-weight: 300;
            line-height: 1.85;
            color: var(--muted);
        }

        nav {
            margin-top: 3rem;
            border-top: 1px solid var(--line);
        }

        .entry {
            display: flex;
            align-items: center;
            gap: 1.125rem;
            padding: 1.375rem .5rem;
            border-bottom: 1px solid var(--line);
            text-decoration: none;
            color: inherit;
            transition: background-color .18s ease;
        }

        .entry:hover,
        .entry:focus-visible {
            background: #f1efe8;
            outline: none;
        }

        .num {
            font-family: 'Amiri', serif;
            font-size: .875rem;
            color: var(--muted);
        }

        .entry-body {
            flex: 1;
            min-width: 0;
        }

        .entry-title {
            display: block;
            font-size: 1.0625rem;
            font-weight: 600;
        }

        .entry-note {
            display: block;
            margin-top: .1875rem;
            font-size: .8125rem;
            font-weight: 300;
            color: var(--muted);
        }

        .arrow {
            flex: none;
            color: var(--green);
            transform: translateX(4px);
            transition: transform .18s ease;
        }

        .entry:hover .arrow,
        .entry:focus-visible .arrow {
            transform: translateX(0);
        }

        footer {
            border-top: 1px solid var(--line);
            padding: 1.5rem 0;
            font-size: .8125rem;
            font-weight: 300;
            color: var(--muted);
        }

        @media (prefers-reduced-motion: reduce) {

            .entry,
            .arrow {
                transition: none;
            }
        }
    </style>
</head>

<body>
    <header>
        <div class="shell bar">
            <img src="{{ asset('logo.jpg') }}" alt="شعار الجمعية">
            <span>جمعية إبن أبي زيد القيرواني</span>
        </div>
    </header>

    <main>
        <div class="shell">
            <h1>بوابة الجمعية</h1>
            <p class="lede">منصّة واحدة لإدارة الجمعية وبرامجها العلمية ومتابعة الحفظ. اختر وجهتك للمتابعة.</p>

            <nav>
                <a class="entry" href="/association">
                    <span class="num">٠١</span>
                    <span class="entry-body">
                        <span class="entry-title">إدارة الجمعية</span>
                        <span class="entry-note">الأعضاء والاشتراكات والتقارير المالية</span>
                    </span>
                    <svg class="arrow" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M19 12H5M11 18l-6-6 6-6" />
                    </svg>
                </a>

                <a class="entry" href="/quran-program">
                    <span class="num">٠٢</span>
                    <span class="entry-body">
                        <span class="entry-title">البرامج العلمية</span>
                        <span class="entry-note">الحلقات والمقررات ومتابعة الحفظ</span>
                    </span>
                    <svg class="arrow" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M19 12H5M11 18l-6-6 6-6" />
                    </svg>
                </a>

                <a class="entry" href="/teacher">
                    <span class="num">٠٣</span>
                    <span class="entry-body">
                        <span class="entry-title">فضاء المعلمين</span>
                        <span class="entry-note">الحضور والتقييم اليومي للطلبة</span>
                    </span>
                    <svg class="arrow" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M19 12H5M11 18l-6-6 6-6" />
                    </svg>
                </a>
            </nav>
        </div>
    </main>

    <footer>
        <div class="shell">جميع الحقوق محفوظة © {{ date('Y') }} جمعية إبن أبي زيد القيرواني</div>
    </footer>
</body>

</html>
