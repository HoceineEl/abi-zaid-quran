<!DOCTYPE html>
<html dir="rtl" lang="ar">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إيصال دفع</title>
    <style>
        @page {
            size: A6;
            margin: 0;
        }

        body {
            font-family: cairo, sans-serif;
            margin: 0;
            padding: 10px;
            color: #333;
            background: white;
            font-size: 12px;
        }

        .receipt {
            max-width: 400px;
            margin: 0 auto;
            padding: 10px;
        }

        .header {
            text-align: center;
            margin-bottom: 15px;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
        }

        .logo-container {
            width: 80px;
            height: 80px;
            margin: 0 auto 10px;
            border-radius: 50%;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .title {
            font-size: 16px;
            font-weight: bold;
            color: #2c3e50;
            margin: 5px 0;
        }

        .receipt-number {
            color: #7f8c8d;
            font-size: 12px;
        }

        .info-section {
            margin-bottom: 15px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            border-bottom: 1px dashed #eee;
            padding-bottom: 3px;
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
            margin: 10px 0;
            font-size: 11px;
        }

        .payments-table th {
            background-color: #f8f9fa;
            padding: 6px;
            text-align: right;
            border-bottom: 2px solid #ddd;
        }

        .payments-table td {
            padding: 6px;
            border-bottom: 1px solid #eee;
        }

        .total {
            text-align: left;
            margin-top: 10px;
            font-size: 14px;
            font-weight: bold;
        }

        .footer {
            margin-top: 20px;
            text-align: center;
            color: #7f8c8d;
            font-size: 10px;
            padding-top: 10px;
            border-top: 1px solid #eee;
        }
    </style>
</head>

<body>
    <div class="receipt">
        <div class="header">
            @if (file_exists(public_path('logo.jpg')))
                <div class="logo-container">
                    <img src="{{ asset('logo.jpg') }}" alt="Logo">
                </div>
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
                        <td>{{ round($payment->amount) }} درهم</td>
                        <td>{{ $payment->payment_date }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="total">
            المجموع الكلي: {{ round($payments->sum('amount')) }} درهم
        </div>

        <div class="footer">
            <p>شكراً لكم - {{ config('app.name') }}</p>
            <p>تم إصدار هذا الإيصال بتاريخ {{ now()->format('Y/m/d H:i:s') }}</p>
        </div>
    </div>

    @if ($autoPrint)
        <script>
            window.onload = function() {
                window.print();
            };
        </script>
    @endif
</body>

</html>
