@php
    $isFamily = $payments->pluck('memorizer_id')->unique()->count() > 1;
    $arabicMonths = [
        1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
        5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'غشت',
        9 => 'شتنبر', 10 => 'أكتوبر', 11 => 'نونبر', 12 => 'دجنبر',
    ];
    $monthLabel = fn ($date) => $date ? ($arabicMonths[(int) $date->format('n')].' '.$date->format('Y')) : '—';
    $first = $payments->first();
    $count = $payments->count();
    $total = (float) $payments->sum('amount');
    $studentCount = $payments->pluck('memorizer_id')->unique()->count();
    $receiptNo = str_pad((string) $first->id, 5, '0', STR_PAD_LEFT);
    $orgName = 'جمعية إبن أبي زيد القيرواني';
    $orgSub = 'دار القرآن الكريم';
@endphp
<!DOCTYPE html>
<html dir="rtl" lang="ar">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $isFamily ? 'إيصال دفع جماعي' : 'إيصال دفع' }} #{{ $receiptNo }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --emerald: #0d5d3f;
            --emerald-dark: #0a4a32;
            --emerald-soft: #e8f3ee;
            --gold: #b8860b;
            --cream: #faf4e3;
            --ink: #1f2d27;
            --muted: #738079;
            --line: #e7ece9;
            --danger: #9c2b2b;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        @page {
            size: A5 portrait;
            margin: 7mm;
        }

        html, body {
            background: #eef1f0;
            color: var(--ink);
            font-family: 'Cairo', sans-serif;
            -webkit-font-smoothing: antialiased;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .sheet {
            width: 131mm;
            margin: 6mm auto;
        }

        .receipt {
            position: relative;
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(13, 93, 63, .12);
            border: 1px solid var(--line);
        }

        /* ===== Header ===== */
        .rc-head {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding: 18px 22px;
            color: #fff;
            background: linear-gradient(135deg, var(--emerald) 0%, var(--emerald-dark) 100%);
        }

        .rc-head::after {
            content: "";
            position: absolute;
            inset-inline: 0;
            bottom: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--gold), #e6c25a, var(--gold));
        }

        .rc-brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .rc-logo {
            width: 58px;
            height: 58px;
            border-radius: 50%;
            background: #fff;
            padding: 3px;
            overflow: hidden;
            flex-shrink: 0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .2);
        }

        .rc-logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }

        .rc-brand__name {
            font-size: 18px;
            font-weight: 800;
            line-height: 1.3;
        }

        .rc-brand__sub {
            font-size: 12px;
            font-weight: 500;
            opacity: .85;
            margin-top: 2px;
        }

        .rc-doc {
            text-align: center;
            background: rgba(255, 255, 255, .12);
            border: 1px solid rgba(255, 255, 255, .25);
            border-radius: 12px;
            padding: 8px 14px;
            flex-shrink: 0;
        }

        .rc-doc__title {
            font-size: 14px;
            font-weight: 800;
            letter-spacing: .3px;
        }

        .rc-doc__no {
            margin-top: 3px;
            font-size: 12px;
            font-weight: 700;
            color: #ffe9a8;
            direction: ltr;
        }

        /* ===== Body ===== */
        .rc-body {
            padding: 20px 22px 8px;
        }

        .rc-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 16px;
        }

        .rc-field {
            flex: 1 1 0;
            min-width: 120px;
            background: var(--emerald-soft);
            border: 1px solid #d7e8e0;
            border-radius: 10px;
            padding: 9px 12px;
        }

        .rc-field--wide {
            flex-basis: 100%;
        }

        .rc-field__label {
            font-size: 10.5px;
            font-weight: 700;
            color: var(--emerald);
            text-transform: uppercase;
            letter-spacing: .4px;
            margin-bottom: 2px;
        }

        .rc-field__value {
            font-size: 14px;
            font-weight: 700;
            color: var(--ink);
        }

        .rc-field__value.ltr {
            direction: ltr;
            text-align: right;
        }

        /* ===== Items table ===== */
        .rc-items {
            width: 100%;
            border-collapse: collapse;
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid var(--line);
            font-size: 12.5px;
        }

        .rc-items thead th {
            background: var(--emerald);
            color: #fff;
            font-weight: 700;
            padding: 9px 10px;
            text-align: right;
            font-size: 12px;
        }

        .rc-items thead th.num,
        .rc-items td.num {
            text-align: center;
            width: 34px;
        }

        .rc-items td {
            padding: 9px 10px;
            border-top: 1px solid var(--line);
            color: var(--ink);
        }

        .rc-items tbody tr:nth-child(even) {
            background: var(--cream);
        }

        .rc-items .amount {
            font-weight: 800;
            color: var(--emerald);
            white-space: nowrap;
        }

        .rc-items .student {
            font-weight: 700;
        }

        .rc-items .muted {
            color: var(--muted);
            font-size: 11.5px;
        }

        .rc-items .ltr {
            direction: ltr;
            text-align: right;
        }

        /* ===== Summary (total + stamp) ===== */
        .rc-summary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-top: 18px;
        }

        .rc-stamp {
            flex-shrink: 0;
            width: 96px;
            height: 96px;
            border-radius: 50%;
            border: 3px double var(--emerald);
            color: var(--emerald);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            transform: rotate(-11deg);
            opacity: .92;
        }

        .rc-stamp__check {
            font-size: 22px;
            line-height: 1;
        }

        .rc-stamp__text {
            font-size: 17px;
            font-weight: 900;
            letter-spacing: 1px;
            margin-top: 2px;
        }

        .rc-stamp__sub {
            font-size: 8px;
            font-weight: 700;
            letter-spacing: 2px;
            opacity: .7;
            margin-top: 1px;
        }

        .rc-total {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            background: linear-gradient(135deg, var(--emerald) 0%, var(--emerald-dark) 100%);
            color: #fff;
            border-radius: 14px;
            padding: 14px 20px;
            box-shadow: 0 6px 16px rgba(13, 93, 63, .25);
        }

        .rc-total__label {
            font-size: 13px;
            font-weight: 600;
            opacity: .9;
        }

        .rc-total__value {
            font-size: 30px;
            font-weight: 900;
            line-height: 1;
        }

        .rc-total__value small {
            font-size: 14px;
            font-weight: 700;
            opacity: .85;
            margin-inline-start: 4px;
        }

        /* ===== Signature ===== */
        .rc-sign {
            margin-top: 26px;
            display: flex;
            justify-content: flex-start;
        }

        .rc-sign__box {
            text-align: center;
            min-width: 150px;
        }

        .rc-sign__line {
            border-top: 1.5px dashed #c3cdc8;
            margin-bottom: 6px;
            height: 26px;
        }

        .rc-sign__label {
            font-size: 11px;
            font-weight: 600;
            color: var(--muted);
        }

        /* ===== Footer ===== */
        .rc-foot {
            margin-top: 14px;
            padding: 12px 22px 16px;
            border-top: 1px solid var(--line);
            background: #fafbfb;
            text-align: center;
        }

        .rc-foot__thanks {
            font-size: 13px;
            font-weight: 700;
            color: var(--emerald);
        }

        .rc-foot__meta {
            font-size: 10.5px;
            color: var(--muted);
            margin-top: 3px;
            direction: ltr;
        }

        @media print {
            html, body {
                background: #fff;
            }

            .sheet {
                width: auto;
                margin: 0;
            }

            .receipt {
                border: none;
                border-radius: 0;
                box-shadow: none;
            }
        }
    </style>
</head>

<body>
    <div class="sheet">
        <div class="receipt">
            <header class="rc-head">
                <div class="rc-brand">
                    @if (file_exists(public_path('logo.jpg')))
                        <div class="rc-logo"><img src="{{ asset('logo.jpg') }}" alt="logo"></div>
                    @endif
                    <div>
                        <div class="rc-brand__name">{{ $orgName }}</div>
                        <div class="rc-brand__sub">{{ $orgSub }}</div>
                    </div>
                </div>
                <div class="rc-doc">
                    <div class="rc-doc__title">{{ $isFamily ? 'إيصال دفع جماعي' : 'إيصال دفع' }}</div>
                    <div class="rc-doc__no">N° {{ $receiptNo }}</div>
                </div>
            </header>

            <div class="rc-body">
                <div class="rc-meta">
                    @if ($isFamily)
                        <div class="rc-field">
                            <div class="rc-field__label">عدد الطلاب</div>
                            <div class="rc-field__value">{{ $studentCount }} طلاب</div>
                        </div>
                        <div class="rc-field">
                            <div class="rc-field__label">عدد الدفعات</div>
                            <div class="rc-field__value">{{ $count }}</div>
                        </div>
                        <div class="rc-field">
                            <div class="rc-field__label">تاريخ الإصدار</div>
                            <div class="rc-field__value ltr">{{ now()->format('Y/m/d') }}</div>
                        </div>
                    @else
                        <div class="rc-field rc-field--wide">
                            <div class="rc-field__label">اسم الطالب</div>
                            <div class="rc-field__value">{{ $first->memorizer->name }}</div>
                        </div>
                        <div class="rc-field">
                            <div class="rc-field__label">المجموعة</div>
                            <div class="rc-field__value">{{ $first->memorizer->group->name ?? '—' }}</div>
                        </div>
                        <div class="rc-field">
                            <div class="rc-field__label">تاريخ الإصدار</div>
                            <div class="rc-field__value ltr">{{ now()->format('Y/m/d') }}</div>
                        </div>
                    @endif
                </div>

                <table class="rc-items">
                    <thead>
                        @if ($isFamily)
                            <tr>
                                <th class="num">#</th>
                                <th>الطالب</th>
                                <th>الشهر</th>
                                <th>المبلغ</th>
                            </tr>
                        @else
                            <tr>
                                <th class="num">#</th>
                                <th>الشهر</th>
                                <th>تاريخ الدفع</th>
                                <th>المبلغ</th>
                            </tr>
                        @endif
                    </thead>
                    <tbody>
                        @foreach ($payments as $index => $payment)
                            @if ($isFamily)
                                <tr>
                                    <td class="num">{{ $index + 1 }}</td>
                                    <td>
                                        <div class="student">{{ $payment->memorizer->name }}</div>
                                        <div class="muted">{{ $payment->memorizer->group->name ?? '—' }}</div>
                                    </td>
                                    <td>{{ $monthLabel($payment->payment_date) }}</td>
                                    <td class="amount">{{ number_format((float) $payment->amount, 0) }} درهم</td>
                                </tr>
                            @else
                                <tr>
                                    <td class="num">{{ $index + 1 }}</td>
                                    <td>{{ $monthLabel($payment->payment_date) }}</td>
                                    <td class="ltr">{{ $payment->payment_date?->format('Y-m-d') }}</td>
                                    <td class="amount">{{ number_format((float) $payment->amount, 0) }} درهم</td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>

                <div class="rc-summary">
                    <div class="rc-stamp">
                        <span class="rc-stamp__check">✓</span>
                        <span class="rc-stamp__text">مدفوع</span>
                        <span class="rc-stamp__sub">PAID</span>
                    </div>
                    <div class="rc-total">
                        <span class="rc-total__label">المجموع الكلي</span>
                        <span class="rc-total__value">{{ number_format($total, 0) }}<small>درهم</small></span>
                    </div>
                </div>

                <div class="rc-sign">
                    <div class="rc-sign__box">
                        <div class="rc-sign__line"></div>
                        <div class="rc-sign__label">توقيع وختم المسؤول</div>
                    </div>
                </div>
            </div>

            <footer class="rc-foot">
                <div class="rc-foot__thanks">شكراً لكم — بارك الله فيكم</div>
                <div class="rc-foot__meta">{{ $orgName }} · {{ now()->format('Y/m/d H:i') }}</div>
            </footer>
        </div>
    </div>

    @if ($autoPrint)
        <script>
            window.addEventListener('load', function () {
                setTimeout(function () { window.print(); }, 350);
            });
        </script>
    @endif
</body>

</html>
