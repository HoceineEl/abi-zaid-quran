<x-filament::modal.heading>
    إيصال دفع
</x-filament::modal.heading>

<div x-data="{ print() { window.print() } }" class="receipt-preview">
    <style>
        @media print {
            body * {
                visibility: hidden;
            }

            .receipt-preview,
            .receipt-preview * {
                visibility: visible;
            }

            .receipt-preview {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }

            .no-print {
                display: none !important;
            }
        }

        .receipt-preview {
            font-family: cairo, sans-serif;
            padding: 20px;
            color: #333;
            background: white;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #eee;
            padding-bottom: 20px;
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
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        .print-button {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
        }
    </style>

    <div>
        <div class="header">
            @if (file_exists(public_path('images/logo.png')))
                <img src="{{ public_path('images/logo.png') }}" alt="Logo"
                    style="max-width: 150px; margin-bottom: 15px;">
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
                    <th>المبلغ</th>
                    <th>تاريخ الدفع</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($payments as $payment)
                    <tr>
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

    <button type="button" x-on:click="print"
        class="print-button no-print filament-button filament-button-size-md inline-flex items-center justify-center py-2 gap-1 font-medium rounded-lg border transition-colors outline-none focus:ring-offset-2 focus:ring-2 focus:ring-inset min-h-[2.25rem] px-4 text-sm text-white shadow focus:ring-white border-transparent bg-primary-600 hover:bg-primary-500 focus:bg-primary-700 focus:ring-offset-primary-700">
        <span class="fi-btn-label">
            طباعة الإيصال
        </span>
    </button>
</div>
