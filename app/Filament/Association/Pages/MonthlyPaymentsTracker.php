<?php

namespace App\Filament\Association\Pages;

use App\Enums\PaymentType;
use App\Models\Memorizer;
use App\Models\Payment;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\IconColumn\IconColumnSize;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class MonthlyPaymentsTracker extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static string $view = 'filament.association.pages.monthly-payments-tracker';

    protected static ?int $navigationSort = 2;

    /** Maximum number of month columns rendered at once (keeps the grid usable). */
    protected const MAX_MONTHS = 24;

    public const ARABIC_MONTHS = [
        1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
        5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'غشت',
        9 => 'شتنبر', 10 => 'أكتوبر', 11 => 'نونبر', 12 => 'دجنبر',
    ];

    public ?string $preset = 'school_year';

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    /** Custom range bound to <input type="month"> (format Y-m). */
    public ?string $customFrom = null;

    public ?string $customTo = null;

    public static function getNavigationLabel(): string
    {
        return 'متابعة الأداء الشهري';
    }

    public function getTitle(): string
    {
        return 'متابعة الأداء الشهري';
    }

    public function getHeading(): string
    {
        return 'متابعة الأداء الشهري';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAssociationAccess() ?? false;
    }

    public function mount(): void
    {
        $this->applyPreset('school_year');
    }

    /** @return array<string, string> */
    public function presets(): array
    {
        return [
            'school_year' => 'السنة الدراسية',
            'current_year' => 'هذه السنة',
            'last_12' => 'آخر 12 شهر',
            'last_year' => 'السنة الماضية',
            'all' => 'الكل',
        ];
    }

    public function applyPreset(string $preset): void
    {
        $this->preset = $preset;
        $now = now();

        switch ($preset) {
            case 'school_year':
                $startYear = $now->month >= 9 ? $now->year : $now->year - 1;
                $this->dateFrom = Carbon::create($startYear, 9, 1)->startOfMonth()->toDateString();
                $this->dateTo = Carbon::create($startYear + 1, 8, 31)->endOfMonth()->toDateString();
                break;

            case 'last_12':
                $this->dateFrom = $now->copy()->subMonths(11)->startOfMonth()->toDateString();
                $this->dateTo = $now->copy()->endOfMonth()->toDateString();
                break;

            case 'last_year':
                $this->dateFrom = Carbon::create($now->year - 1, 1, 1)->startOfMonth()->toDateString();
                $this->dateTo = Carbon::create($now->year - 1, 12, 31)->endOfMonth()->toDateString();
                break;

            case 'all':
                $first = Payment::min('payment_date');
                $this->dateFrom = $first
                    ? Carbon::parse($first)->startOfMonth()->toDateString()
                    : $now->copy()->startOfYear()->toDateString();
                $this->dateTo = $now->copy()->endOfMonth()->toDateString();
                break;

            case 'current_year':
            default:
                $this->preset = 'current_year';
                $this->dateFrom = $now->copy()->startOfYear()->toDateString();
                $this->dateTo = $now->copy()->endOfMonth()->toDateString();
                break;
        }

        $this->customFrom = Carbon::parse($this->dateFrom)->format('Y-m');
        $this->customTo = Carbon::parse($this->dateTo)->format('Y-m');

        // Rebuild the table so the dynamic month columns + eager-load range refresh.
        // Guarded because the table isn't booted yet during mount().
        if (isset($this->table)) {
            $this->resetTable();
        }
    }

    public function updatedCustomFrom(): void
    {
        $this->applyCustomRange();
    }

    public function updatedCustomTo(): void
    {
        $this->applyCustomRange();
    }

    protected function applyCustomRange(): void
    {
        if (blank($this->customFrom) || blank($this->customTo)) {
            return;
        }

        $this->preset = 'custom';
        $this->dateFrom = Carbon::parse($this->customFrom.'-01')->startOfMonth()->toDateString();
        $this->dateTo = Carbon::parse($this->customTo.'-01')->endOfMonth()->toDateString();

        if (isset($this->table)) {
            $this->resetTable();
        }
    }

    /**
     * Ordered list of months in the active range (capped to the most recent MAX_MONTHS).
     *
     * @return array<int, array{year:int, month:int, key:string, label:string, short:string}>
     */
    public function monthsInRange(): array
    {
        $from = Carbon::parse($this->dateFrom ?? now()->startOfYear())->startOfMonth();
        $to = Carbon::parse($this->dateTo ?? now())->startOfMonth();

        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        $months = [];
        foreach (CarbonPeriod::create($from, '1 month', $to) as $cursor) {
            $month = (int) $cursor->format('n');
            $year = (int) $cursor->format('Y');

            $months[] = [
                'year' => $year,
                'month' => $month,
                'key' => $cursor->format('Y-m'),
                'label' => self::ARABIC_MONTHS[$month].' '.$year,
                'short' => self::ARABIC_MONTHS[$month].' '.$cursor->format('y'),
                'type' => PaymentType::MONTHLY,
            ];
        }

        if (count($months) > self::MAX_MONTHS) {
            $months = array_slice($months, -self::MAX_MONTHS);
        }

        return $months;
    }

    /** @return array{0: Carbon, 1: Carbon} */
    public function schoolYearBounds(): array
    {
        $reference = Carbon::parse($this->dateTo ?? now());
        $startYear = $reference->month >= 9 ? $reference->year : $reference->year - 1;

        return [
            Carbon::create($startYear, 9, 1)->startOfDay(),
            Carbon::create($startYear + 1, 8, 31)->endOfDay(),
        ];
    }

    /**
     * @return array{year:int, month:null, key:string, label:string, short:string, type:PaymentType}
     */
    public function feeSlot(PaymentType $type): array
    {
        [$start] = $this->schoolYearBounds();
        $schoolYear = $start->year.'/'.($start->year + 1);

        return [
            'year' => $start->year,
            'month' => null,
            'key' => $type->value,
            'label' => $type->getLabel().' '.$schoolYear,
            'short' => $type === PaymentType::REGISTRATION ? 'التسجيل' : 'الدورة الصيفية',
            'type' => $type,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $months
     * @return array<int, array<string, mixed>>
     */
    public function paymentSlots(array $months): array
    {
        return [
            $this->feeSlot(PaymentType::REGISTRATION),
            ...$months,
            $this->feeSlot(PaymentType::SUMMER_SESSION),
        ];
    }

    public function table(Table $table): Table
    {
        $months = $this->monthsInRange();
        $rangeStart = Carbon::parse($months[0]['key'].'-01')->startOfMonth();
        $rangeEnd = Carbon::parse(end($months)['key'].'-01')->endOfMonth();
        [$schoolYearStart, $schoolYearEnd] = $this->schoolYearBounds();

        return $table
            ->query(
                Memorizer::query()->with([
                    'group:id,name,price',
                    'guardian:id,name,phone',
                    'payments' => fn ($query) => $query
                        ->where(fn (Builder $query) => $query->monthly()->whereBetween('payment_date', [$rangeStart, $rangeEnd]))
                        ->orWhere(fn (Builder $query) => $query
                            ->whereNot('type', PaymentType::MONTHLY)
                            ->whereBetween('payment_date', [$schoolYearStart, $schoolYearEnd])),
                ])
            )
            ->defaultSort('name')
            ->searchable()
            ->searchDebounce('200ms')
            ->searchPlaceholder('بحث بالاسم أو الهاتف...')
            ->columns([
                TextColumn::make('name')
                    ->label('الطالب')
                    ->weight(FontWeight::Bold)
                    ->searchable()
                    ->sortable()
                    ->description(fn (Memorizer $record): ?string => $record->group?->name)
                    ->wrap(),

                TextColumn::make('displayPhone')
                    ->label('الهاتف')
                    ->toggleable()
                    ->copyable()
                    ->alignRight()
                    ->extraCellAttributes(['dir' => 'ltr'])
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $query) use ($search): void {
                            $query->where('phone', 'like', "%{$search}%")
                                ->orWhereHas('guardian', fn (Builder $q) => $q->where('phone', 'like', "%{$search}%"));
                        });
                    }),

                ...$this->buildMonthColumns($this->paymentSlots($months)),

                TextColumn::make('summary')
                    ->label('الملخص')
                    ->alignCenter()
                    ->badge()
                    ->extraCellAttributes(['dir' => 'ltr'])
                    ->state(fn (Memorizer $record): string => $this->summaryState($record, $months))
                    ->color(fn (Memorizer $record): string => $this->summaryColor($record, $months))
                    ->description(fn (Memorizer $record): ?string => $record->exempt
                        ? null
                        : number_format($this->rowTotal($record, $months)).' د.م', position: 'below'),
            ])
            ->filters([
                SelectFilter::make('memo_group_id')
                    ->label('المجموعة')
                    ->relationship('group', 'name')
                    ->multiple()
                    ->preload(),

                TernaryFilter::make('exempt')
                    ->label('معفى من الدفع'),

                TernaryFilter::make('unpaid_current_month')
                    ->label('دفع الشهر الحالي')
                    ->placeholder('الكل')
                    ->trueLabel('دفع الشهر الحالي')
                    ->falseLabel('لم يدفع الشهر الحالي')
                    ->queries(
                        true: fn (Builder $query) => $query->whereHas('payments', fn (Builder $q) => $q
                            ->monthly()
                            ->whereYear('payment_date', now()->year)
                            ->whereMonth('payment_date', now()->month)),
                        false: fn (Builder $query) => $query->where('exempt', false)
                            ->whereDoesntHave('payments', fn (Builder $q) => $q
                                ->monthly()
                                ->whereYear('payment_date', now()->year)
                                ->whereMonth('payment_date', now()->month)),
                    ),
            ])
            ->bulkActions([
                $this->familyPaymentBulkAction($months),
            ])
            ->striped()
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(50)
            ->emptyStateHeading('لا يوجد طلاب')
            ->emptyStateIcon('heroicon-o-user-group');
    }

    /**
     * @param  array<int, array{year:int, month:int, key:string, label:string, short:string}>  $months
     * @return array<int, IconColumn>
     */
    protected function buildMonthColumns(array $months): array
    {
        return collect($months)->map(function (array $month): IconColumn {
            return IconColumn::make('month_'.$month['key'])
                ->label($month['short'])
                ->alignCenter()
                ->size(IconColumnSize::Large)
                ->tooltip(fn (Memorizer $record): ?string => $this->cellTooltip($record, $month))
                ->state(fn (Memorizer $record): string => $this->cellType($record, $month))
                ->icon(fn (string $state): string => match ($state) {
                    'paid' => 'heroicon-s-check-circle',
                    'exempt' => 'heroicon-o-minus-circle',
                    default => 'heroicon-o-x-circle',
                })
                ->color(fn (string $state): string => match ($state) {
                    'paid' => 'success',
                    'exempt' => 'gray',
                    default => 'danger',
                })
                ->action($this->monthCellAction($month));
        })->all();
    }

    /**
     * @param  array{year:int, month:int, key:string, label:string, short:string}  $month
     */
    protected function cellType(Memorizer $record, array $month): string
    {
        if ($record->exempt) {
            return 'exempt';
        }

        return $this->paymentsFor($record, $month)->isNotEmpty() ? 'paid' : 'unpaid';
    }

    /**
     * @param  array{year:int, month:int, key:string, label:string, short:string}  $month
     */
    protected function cellTooltip(Memorizer $record, array $month): ?string
    {
        if ($record->exempt) {
            return 'معفى من الدفع';
        }

        $payments = $this->paymentsFor($record, $month);

        if ($payments->isEmpty()) {
            return $month['label'].' — غير مدفوع';
        }

        return $month['label'].' — '.number_format((float) $payments->sum('amount')).' د.م';
    }

    /**
     * Payments belonging to a given month, read from the eager-loaded collection (no query).
     *
     * @param  array{year:int, month:int, key:string, label:string, short:string}  $month
     */
    protected function paymentsFor(Memorizer $record, array $month): Collection
    {
        return $record->payments->filter(
            fn (Payment $payment): bool => $payment->type === $month['type']
                && ($month['month'] === null || $payment->payment_date?->format('Y-m') === $month['key'])
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $months
     */
    protected function monthlyPaymentsIn(Memorizer $record, array $months): Collection
    {
        $keys = collect($months)->pluck('key');

        return $record->payments->filter(
            fn (Payment $payment): bool => $payment->type === PaymentType::MONTHLY
                && $keys->contains($payment->payment_date?->format('Y-m'))
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $months
     */
    protected function rowTotal(Memorizer $record, array $months): float
    {
        if ($record->exempt) {
            return 0.0;
        }

        return (float) $this->monthlyPaymentsIn($record, $months)->sum('amount');
    }

    /**
     * @param  array<int, array<string, mixed>>  $months
     */
    protected function paidMonthsCount(Memorizer $record, array $months): int
    {
        return $this->monthlyPaymentsIn($record, $months)
            ->pluck('payment_date')
            ->map(fn ($date) => $date?->format('Y-m'))
            ->unique()
            ->count();
    }

    /**
     * @param  array<int, array<string, mixed>>  $months
     * @return array<int, array<string, mixed>>
     */
    protected function dueMonths(array $months): array
    {
        $currentKey = now()->format('Y-m');

        return array_values(array_filter($months, fn (array $month): bool => $month['key'] <= $currentKey));
    }

    /**
     * @param  array<int, array<string, mixed>>  $months
     */
    protected function summaryState(Memorizer $record, array $months): string
    {
        if ($record->exempt) {
            return 'معفى';
        }

        $dueMonths = $this->dueMonths($months);

        return $this->paidMonthsCount($record, $dueMonths).' / '.count($dueMonths);
    }

    /**
     * @param  array<int, array<string, mixed>>  $months
     */
    protected function summaryColor(Memorizer $record, array $months): string
    {
        if ($record->exempt) {
            return 'gray';
        }

        $dueMonths = $this->dueMonths($months);
        $total = count($dueMonths);
        $paid = $this->paidMonthsCount($record, $dueMonths);

        return match (true) {
            $total === 0 => 'gray',
            $paid >= $total => 'success',
            $paid / $total >= 0.5 => 'warning',
            default => 'danger',
        };
    }

    /**
     * Representative payment date stored for a given month (so the grid buckets it correctly).
     *
     * @param  array{year:int, month:int, key:string, label:string, short:string}  $month
     */
    protected function paymentDateFor(array $month): Carbon
    {
        $now = now();

        if ($month['month'] === null) {
            [$start, $end] = $this->schoolYearBounds();

            return $now->between($start, $end) ? $now : $start;
        }

        if ($month['year'] === $now->year && $month['month'] === $now->month) {
            return $now;
        }

        return Carbon::create($month['year'], $month['month'], 1)->startOfDay();
    }

    /**
     * Clickable month cell: pay when unpaid, view/print/delete when paid, disabled when exempt.
     *
     * @param  array{year:int, month:int, key:string, label:string, short:string}  $month
     */
    protected function monthCellAction(array $month): Action
    {
        return Action::make('cell_'.$month['key'])
            ->modalHeading(fn (Memorizer $record): string => $record->name.' — '.$month['label'])
            ->modalWidth('md')
            ->hidden(fn (Memorizer $record): bool => $record->exempt)
            ->fillForm(fn (Memorizer $record): array => [
                'amount' => $month['type'] === PaymentType::MONTHLY ? ($record->group?->price ?? 70) : null,
                'payment_date' => $this->paymentDateFor($month)->toDateString(),
            ])
            ->form(function (Memorizer $record) use ($month): array {
                if ($this->paymentsFor($record, $month)->isNotEmpty()) {
                    return [];
                }

                return [
                    Placeholder::make('hint')
                        ->hiddenLabel()
                        ->content($month['type'] === PaymentType::MONTHLY
                            ? 'سجّل دفعة الشهر ثم اطبع الإيصال.'
                            : 'سجّل '.$month['type']->getLabel().' ثم اطبع الإيصال.'),
                    TextInput::make('amount')
                        ->label('المبلغ (درهم)')
                        ->numeric()
                        ->minValue(0)
                        ->required(),
                    DatePicker::make('payment_date')
                        ->label('تاريخ الدفع')
                        ->required(),
                ];
            })
            ->modalContent(function (Memorizer $record) use ($month) {
                $payments = $this->paymentsFor($record, $month);

                if ($payments->isEmpty()) {
                    return null;
                }

                return view('filament.association.pages.partials.month-paid-info', [
                    'payments' => $payments,
                    'total' => (float) $payments->sum('amount'),
                    'month' => $month,
                ]);
            })
            ->modalSubmitAction(fn (Memorizer $record) => $this->paymentsFor($record, $month)->isNotEmpty() ? false : null)
            ->modalCancelActionLabel('إغلاق')
            ->extraModalFooterActions(function (Memorizer $record) use ($month): array {
                $payments = $this->paymentsFor($record, $month);

                if ($payments->isEmpty()) {
                    return [];
                }

                return [
                    Action::make('print_'.$month['key'])
                        ->label('طباعة الإيصال')
                        ->icon('heroicon-o-printer')
                        ->color('gray')
                        ->url(route('payments.receipt', ['payments' => $payments->pluck('id')->join(',')]), shouldOpenInNewTab: true),

                    Action::make('delete_'.$month['key'])
                        ->label('حذف الدفعة')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('حذف الدفعة')
                        ->action(function () use ($record, $month): void {
                            $this->paymentsFor($record, $month)->each->delete();

                            Notification::make()
                                ->title('تم حذف الدفعة')
                                ->success()
                                ->send();
                        })
                        ->cancelParentActions(),
                ];
            })
            ->action(function (Memorizer $record, array $data) use ($month): void {
                if ($this->paymentsFor($record, $month)->isNotEmpty()) {
                    return;
                }

                $payment = $record->payments()->create([
                    'type' => $month['type'],
                    'amount' => $data['amount'],
                    'payment_date' => $data['payment_date'],
                ]);

                $this->sendReceiptNotification(
                    'تم تسجيل الدفعة بنجاح',
                    route('payments.receipt', ['payments' => $payment->id]),
                );
            });
    }

    /**
     * Family / multi-student payment for a single month with one combined receipt.
     *
     * @param  array<int, array<string, mixed>>  $months
     */
    protected function familyPaymentBulkAction(array $months): BulkAction
    {
        return BulkAction::make('pay_family')
            ->label('دفع جماعي (عائلة)')
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->modalHeading('دفع جماعي للطلاب المحددين')
            ->modalDescription('سجّل دفعة شهر واحد لعدة طلاب (مثلاً إخوة من نفس العائلة) واطبع إيصالاً موحداً واحداً.')
            ->modalSubmitActionLabel('تأكيد الدفع وطباعة الإيصال')
            ->form(function ($livewire) use ($months): array {
                $records = $livewire->getSelectedTableRecords();

                $monthOptions = collect($this->paymentSlots($months))
                    ->mapWithKeys(fn (array $month): array => [$month['key'] => $month['label']])
                    ->toArray();

                $currentKey = now()->format('Y-m');
                if (! isset($monthOptions[$currentKey])) {
                    $monthOptions = [$currentKey => self::ARABIC_MONTHS[now()->month].' '.now()->year.' (الشهر الحالي)'] + $monthOptions;
                }

                $students = $records
                    ->map(fn (Memorizer $record): array => [
                        'id' => (string) $record->id,
                        'name' => $record->name.($record->exempt ? ' (معفى)' : ''),
                        'amount' => $record->exempt ? 0 : ($record->group?->price ?? 70),
                    ])
                    ->values()
                    ->all();

                return [
                    Select::make('month_key')
                        ->label('الشهر أو الواجب')
                        ->options($monthOptions)
                        ->default($currentKey)
                        ->required()
                        ->native(false),

                    Repeater::make('students')
                        ->label('الطلاب والمبالغ')
                        ->schema([
                            Hidden::make('id'),
                            TextInput::make('name')
                                ->label('الطالب')
                                ->disabled()
                                ->dehydrated(false)
                                ->columnSpan(2),
                            TextInput::make('amount')
                                ->label('المبلغ (درهم)')
                                ->numeric()
                                ->minValue(0)
                                ->required(),
                        ])
                        ->default($students)
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false)
                        ->columns(3),
                ];
            })
            ->action(function (Collection $records, array $data): void {
                $monthKey = $data['month_key'];
                $feeType = PaymentType::tryFrom($monthKey);

                if ($feeType) {
                    $slot = $this->feeSlot($feeType);
                } else {
                    [$year, $month] = array_map('intval', explode('-', $monthKey));
                    $slot = [
                        'year' => $year,
                        'month' => $month,
                        'key' => $monthKey,
                        'label' => self::ARABIC_MONTHS[$month].' '.$year,
                        'short' => '',
                        'type' => PaymentType::MONTHLY,
                    ];
                }

                [$schoolYearStart, $schoolYearEnd] = $this->schoolYearBounds();
                $paymentDate = $this->paymentDateFor($slot);

                $amounts = collect($data['students'] ?? [])
                    ->mapWithKeys(fn (array $row): array => [(int) $row['id'] => (float) ($row['amount'] ?? 0)]);

                $created = collect();
                $skipped = [];

                foreach ($records as $record) {
                    if ($record->exempt) {
                        continue;
                    }

                    $alreadyPaid = $record->payments()
                        ->ofType($slot['type'])
                        ->when(
                            $feeType,
                            fn (Builder $query) => $query->whereBetween('payment_date', [$schoolYearStart, $schoolYearEnd]),
                            fn (Builder $query) => $query->whereYear('payment_date', $slot['year'])->whereMonth('payment_date', $slot['month']),
                        )
                        ->exists();

                    if ($alreadyPaid) {
                        $skipped[] = $record->name;

                        continue;
                    }

                    $created->push($record->payments()->create([
                        'type' => $slot['type'],
                        'amount' => $amounts->get($record->id, $record->group?->price ?? 70),
                        'payment_date' => $paymentDate,
                    ]));
                }

                if ($created->isEmpty()) {
                    Notification::make()
                        ->title('لم يتم تسجيل أي دفعة')
                        ->body('قد يكون جميع الطلاب المحددين قد دفعوا هذا الشهر مسبقاً أو أنهم معفون.')
                        ->warning()
                        ->send();

                    return;
                }

                $body = 'تم تسجيل '.$created->count().' دفعة: '.$slot['label'].'.';
                if ($skipped !== []) {
                    $body .= ' تم تجاهل (مدفوع مسبقاً): '.implode('، ', $skipped).'.';
                }

                $this->sendReceiptNotification(
                    'تم الدفع الجماعي بنجاح',
                    route('payments.receipt', ['payments' => $created->pluck('id')->join(',')]),
                    $body,
                    'طباعة إيصال موحد',
                );
            })
            ->deselectRecordsAfterCompletion();
    }

    protected function sendReceiptNotification(string $title, string $receiptUrl, ?string $body = null, string $printLabel = 'طباعة الإيصال'): void
    {
        Notification::make()
            ->title($title)
            ->body($body)
            ->success()
            ->persistent()
            ->actions([
                NotificationAction::make('print_receipt')
                    ->label($printLabel)
                    ->icon('heroicon-o-printer')
                    ->button()
                    ->color('success')
                    ->url($receiptUrl, shouldOpenInNewTab: true),
            ])
            ->send();
    }
}
