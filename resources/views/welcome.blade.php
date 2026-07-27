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
            --ink: #060d12;
            --gold: #d9b46a;
            --line: rgba(255, 255, 255, .10);
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
            font-family: 'Cairo', system-ui, sans-serif;
            color: #fff;
            background: var(--ink);
            background-image:
                radial-gradient(120% 90% at 88% 4%, #1d6b57 0%, rgba(29, 107, 87, 0) 55%),
                radial-gradient(110% 85% at 6% 96%, #22346f 0%, rgba(34, 52, 111, 0) 58%),
                linear-gradient(168deg, #08251f 0%, #0a1626 46%, #060d12 100%);
            background-attachment: fixed;
            -webkit-font-smoothing: antialiased;
        }

        body::after {
            content: "";
            position: fixed;
            inset: 0;
            pointer-events: none;
            opacity: .25;
            mix-blend-mode: overlay;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='160' height='160'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.85' numOctaves='3'/%3E%3C/filter%3E%3Crect width='160' height='160' filter='url(%23n)'/%3E%3C/svg%3E");
        }

        .shell {
            width: 100%;
            max-width: 46rem;
            margin: 0 auto;
            padding: 0 1.5rem;
        }

        main {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 4.5rem 0 3rem;
        }

        .mark {
            width: 3.5rem;
            height: 3.5rem;
            border-radius: 999px;
            object-fit: cover;
            background: #fff;
            box-shadow: 0 0 0 1px var(--line);
        }

        .eyebrow {
            margin: 1.75rem 0 .5rem;
            font-size: .75rem;
            font-weight: 500;
            letter-spacing: .22em;
            color: var(--gold);
        }

        h1 {
            font-family: 'Amiri', serif;
            font-weight: 700;
            font-size: clamp(2.1rem, 7vw, 3.4rem);
            line-height: 1.35;
            margin: 0;
        }

        .lede {
            margin: 1rem 0 0;
            max-width: 32ch;
            font-size: 1rem;
            font-weight: 300;
            line-height: 1.9;
            color: rgba(255, 255, 255, .58);
        }

        nav {
            margin-top: 3.25rem;
            border-top: 1px solid var(--line);
        }

        .entry {
            display: flex;
            align-items: center;
            gap: 1.25rem;
            padding: 1.25rem .25rem;
            border-bottom: 1px solid var(--line);
            text-decoration: none;
            color: inherit;
            transition: padding-inline .25s ease, background-color .25s ease;
        }

        .entry:hover,
        .entry:focus-visible {
            background: rgba(255, 255, 255, .04);
            padding-inline: 1rem .5rem;
            outline: none;
        }

        .num {
            font-family: 'Amiri', serif;
            font-size: .95rem;
            color: rgba(255, 255, 255, .35);
        }

        .entry-body {
            flex: 1;
            min-width: 0;
        }

        .entry-title {
            display: block;
            font-size: 1.15rem;
            font-weight: 600;
        }

        .entry-note {
            display: block;
            margin-top: .2rem;
            font-size: .85rem;
            font-weight: 300;
            color: rgba(255, 255, 255, .5);
        }

        .arrow {
            flex: none;
            color: var(--gold);
            opacity: .45;
            transform: translateX(6px);
            transition: opacity .25s ease, transform .25s ease;
        }

        .entry:hover .arrow,
        .entry:focus-visible .arrow {
            opacity: 1;
            transform: translateX(0);
        }

        footer {
            padding: 1.75rem 0 2.25rem;
            font-size: .8rem;
            font-weight: 300;
            color: rgba(255, 255, 255, .35);
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
    <main>
        <div class="shell">
            <img class="mark" src="{{ asset('logo.jpg') }}" alt="شعار الجمعية">
            <p class="eyebrow">بوابة الجمعية</p>
            <h1>جمعية إبن أبي زيد<br>القيرواني</h1>
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
