<!DOCTYPE html>
<html dir="rtl" lang="ar">

<head>
    <title>جاري التحويل إلى واتساب...</title>
    <meta charset="UTF-8">
    @laravelPWA
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            margin: 0;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f3f4f6;
            font-family: system-ui, -apple-system, sans-serif;
        }

        .container {
            background: white;
            padding: 2rem;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 1rem;
            animation: bounce 1s infinite;
        }

        .title {
            font-size: 1.5rem;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }

        .subtitle {
            color: #6b7280;
            margin-bottom: 1rem;
        }

        .loader {
            width: 32px;
            height: 32px;
            border: 4px solid #22c55e;
            border-top: 4px solid transparent;
            border-radius: 50%;
            margin: 0 auto;
            animation: spin 1s linear infinite;
        }

        @keyframes bounce {

            0%,
            100% {
                transform: translateY(-25%);
                animation-timing-function: cubic-bezier(0.8, 0, 1, 1);
            }

            50% {
                transform: translateY(0);
                animation-timing-function: cubic-bezier(0, 0, 0.2, 1);
            }
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <svg class="icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="#22c55e">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
        </svg>
        <h1 class="title">جاري التحويل إلى واتساب</h1>
        <p class="subtitle">سيتم فتح تطبيق واتساب تلقائياً خلال ثوانٍ...</p>
        <div class="loader"></div>
    </div>

    <script>
        window.onload = function() {
            window.open('https://wa.me/{{ $number }}?text={{ $message }}', '_blank');
            window.close();
        }
    </script>
</body>

</html>
