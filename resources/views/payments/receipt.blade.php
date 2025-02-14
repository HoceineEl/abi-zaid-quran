<!DOCTYPE html>
<html dir="rtl" lang="ar">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إيصال دفع</title>
    <style>
        body {
            font-family: cairo, sans-serif;
            margin: 0;
            padding: 20px;
            color: #333;
        }

        .receipt {
            max-width: 800px;
            margin: 0 auto;
            border: 2px solid #ddd;
            padding: 20px;
            border-radius: 8px;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #eee;
            padding-bottom: 20px;
        }

        .logo {
            max-width: 150px;
            margin-bottom: 15px;
        }

        .title {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
            margin: 10px 0;
        }

        .receipt-number {
            color: #7f8c8d;
            font-size: 16px;
        }

        .info-section {
            margin-bottom: 25px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            border-bottom: 1px dashed #eee;
            padding-bottom: 5px;
        }

        .label {
            font-weight: bold;
            color: #34495e;
        }

        .value {
            color: #2c3e50;
        }

        .payments-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        .payments-table th {
            background-color: #f8f9fa;
            padding: 12px;
            text-align: right;
            border-bottom: 2px solid #ddd;
        }

        .payments-table td {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }

        .total {
            text-align: left;
            margin-top: 20px;
            font-size: 18px;
            font-weight: bold;
        }

        .footer {
            margin-top: 40px;
            text-align: center;
            color: #7f8c8d;
            font-size: 14px;
        }

        .qr-code {
            text-align: center;
            margin-top: 20px;
        }

        .qr-code img {
            width: 100px;
            height: 100px;
        }
    </style>
</head>

<body>
    <div class="receipt">
        <div class="header">
            @if (file_exists(public_path('images/logo.png')))
                <img src="{{ public_path('images/logo.png') }}" alt="Logo" class="logo">
            @endif
            <h1 class="title">إيصال دفع</h1>
            <div class="receipt-number">رقم الإيصال: {{ $payments->first()->id }}</div>
        </div>

        <div class="info-section">
            <div class="info-row">
                <span class="label">اسم الطالب:</span>
                <span class="value">{{ $payments->first()->memorizer->name }}</span>
            </div>
            <div class="info-row">
                <span class="label">تاريخ الإصدار:</span>
                <span class="value">{{ now()->format('Y/m/d') }}</span>
            </div>
            <div class="info-row">
                <span class="label">المجموعة:</span>
                <span class="value">{{ $payments->first()->memorizer->group->name ?? 'غير محدد' }}</span>
            </div>
        </div>

        <table class="payments-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>المبلغ</th>
                    <th>تاريخ الدفع</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($payments as $index => $payment)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ number_format($payment->amount, 2) }} ريال</td>
                        <td>{{ $payment->payment_date }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="total">
            المجموع الكلي: {{ number_format($payments->sum('amount'), 2) }} ريال
        </div>

        <div class="footer">
            <p>شكراً لكم - {{ config('app.name') }}</p>
            <p>تم إصدار هذا الإيصال بتاريخ {{ now()->format('Y/m/d H:i:s') }}</p>
        </div>

    </div>
</body>

</html>
