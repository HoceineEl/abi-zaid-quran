<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>بطاقة الطالب</title>
    <style>
        body {
            font-family: cairo, sans-serif;
            background-color: #f0f0f0;
            direction: rtl;
        }

        .badge {
            width: 300px;
            height: 450px;
            margin: 0 auto;
            background-color: white;
            border-radius: 10px;
            overflow: hidden;
        }

        .header {
            background-color: #4a5568;
            color: white;
            text-align: center;
            padding: 20px;
        }

        .content {
            padding: 20px;
        }

        .qr-code {
            text-align: center;
            margin-top: 20px;
        }

        .qr-code img {
            width: 170px;
            height: 170px;
        }
    </style>
</head>

<body>
    <div class="badge">
        <div class="header">
            <h2>بطاقة الطالب</h2>
        </div>
        <div class="content">
            <p><strong>الاسم:</strong> {{ $memorizer->name }}</p>
            <p><strong>رقم الهاتف:</strong> {{ $memorizer->phone }}</p>
            <p><strong>المجموعة:</strong> {{ $memorizer->group->name }}</p>
            <div class="qr-code">
                <img src="{{ $qrCode }}" alt="QR Code">
            </div>
        </div>
    </div>
</body>

</html>
