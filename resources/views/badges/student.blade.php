<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>بطاقة الطالب</title>
    <style>
        @font-face {
            font-family: 'Cairo';
            src: url({{ public_path('fonts/Cairo/Cairo-Regular.ttf') }}) format('truetype');
            font-weight: normal;
            font-style: normal;
        }

        @font-face {
            font-family: 'Cairo';
            src: url({{ public_path('fonts/Cairo/Cairo-Bold.ttf') }}) format('truetype');
            font-weight: bold;
            font-style: normal;
        }

        body {
            font-family: 'Cairo', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .badge-container {
            width: 400px;
            height: 650px;
            background-color: #ffffff;
            border-radius: 30px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            position: relative;
            display: flex;
            flex-direction: column;
        }

        .badge-header {
            background-color: #4a0e8f;
            padding: 20px;
            color: white;
            font-size: 20px;
            font-weight: bold;
            text-align: center;
            border-bottom: 5px solid #7b2cbf;
        }

        .badge-body {
            padding: 30px 20px;
            text-align: center;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }



        .student-photo {
            width: 150px;
            height: 150px;
            margin: 0 auto 20px;
            border: 4px solid #4a0e8f;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            position: relative;
            overflow: hidden;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .student-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }


        .student-name {
            font-size: 28px;
            font-weight: bold;
            color: #4a0e8f;
            margin-bottom: 20px;
        }

        .student-info {
            font-size: 18px;
            margin-bottom: 15px;
            color: #333;
            background-color: #f0f0f0;
            padding: 10px 20px;
            border-radius: 20px;
            display: inline-block;
        }

        .qr-code {
            margin-top: 20px;
        }

        .qr-code img {
            width: 120px;
            height: 120px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .badge-footer {
            background-color: #4a0e8f;
            color: white;
            padding: 15px;
            font-size: 16px;
            text-align: center;
            border-top: 3px solid #7b2cbf;
        }
    </style>
</head>

<body>
    <div class="badge-container">
        <div class="badge-header">
            جمعية إبن أبي زيد القيرواني
        </div>
        <div class="badge-body">
            <div>
                <div class="student-photo">
                    <img src="{{ $memorizer->photo_url }}" alt="صورة الطالب">
                </div>
                <div class="student-name">{{ $memorizer->name }}</div>
            </div>
            <div>
                <div class="student-info">رقم الهاتف: {{ $memorizer->phone }}</div>
                <div class="student-info">المجموعة: {{ $memorizer->group->name }}</div>
            </div>
            <div class="qr-code">
                <img src="{{ $qrCode }}" alt="QR Code">
            </div>
        </div>
        <div class="badge-footer">
            رقم الطالب: {{ $memorizer->id }}
        </div>
    </div>
</body>

</html>
