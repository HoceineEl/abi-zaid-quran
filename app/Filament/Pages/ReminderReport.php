<?php

namespace App\Filament\Pages;

use App\Enums\WhatsAppMessageStatus;
use App\Helpers\PhoneHelper;
use App\Models\Group;
use App\Models\Student;
use App\Models\WhatsAppMessageHistory;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReminderReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $navigationLabel = 'تقرير التذكيرات';

    protected static ?string $title = 'تقرير التذكيرات اليومي';

    protected static ?string $slug = 'reminder-report';

    protected static string $view = 'filament.pages.reminder-report';

    protected static ?string $navigationGroup = 'التقارير';

    public static function canAccess(): bool
    {
        return false;
    }

    private function getFilterDate(): string
    {
        return $this->getTableFilterState('date')['date'] ?? now()->toDateString();
    }

    /**
     * Fetch and cache WhatsApp message stats for a group on a given date.
     * Static cache avoids repeated DB queries across columns for the same row.
     */
    private function getGroupMessageStats(Group $group, string $date): array
    {
        static $cache = [];

        $key = "{$group->id}:{$date}";

        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $cleanedPhones = $group->students()->pluck('phone')
            ->map(fn($phone) => PhoneHelper::cleanPhoneNumber($phone))
            ->filter()
            ->values()
            ->toArray();

        $messages = WhatsAppMessageHistory::query()
            ->whereIn('recipient_phone', $cleanedPhones)
            ->whereDate('created_at', $date)
            ->with('sender')
            ->get();

        return $cache[$key] = [
            'count' => $messages->groupBy('recipient_phone')->count(),
            'senders' => $messages->pluck('sender.name')->unique()->filter()->join('، '),
            'sent' => $messages->where('status', WhatsAppMessageStatus::SENT)->count(),
            'queued' => $messages->where('status', WhatsAppMessageStatus::QUEUED)->count(),
            'failed' => $messages->where('status', WhatsAppMessageStatus::FAILED)->count(),
            'last_at' => $messages->max('created_at'),
        ];
    }

    private function formatStatusSummary(array $stats): string
    {
        $parts = array_filter([
            $stats['sent'] ? "{$stats['sent']} مُرسل" : null,
            $stats['queued'] ? "{$stats['queued']} قيد الإرسال" : null,
            $stats['failed'] ? "{$stats['failed']} فشل" : null,
        ]);

        return implode(' | ', $parts);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Group::query()->with('managers'))
            ->columns([
                TextColumn::make('name')
                    ->label('المجموعة')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('managers.name')
                    ->label('المشرفون')
                    ->badge(),

                TextColumn::make('reminded_count')
                    ->label('عدد المذكَّرين')
                    ->getStateUsing(fn (Group $record) => $this->getGroupMessageStats($record, $this->getFilterDate())['count'])
                    ->badge()
                    ->color('info'),

                TextColumn::make('senders')
                    ->label('أُرسل بواسطة')
                    ->getStateUsing(fn (Group $record) => $this->getGroupMessageStats($record, $this->getFilterDate())['senders']),

                TextColumn::make('status_summary')
                    ->label('الحالة')
                    ->getStateUsing(fn (Group $record) => $this->formatStatusSummary(
                        $this->getGroupMessageStats($record, $this->getFilterDate()),
                    )),

                TextColumn::make('last_sent_at')
                    ->label('آخر إرسال')
                    ->getStateUsing(fn (Group $record) => $this->getGroupMessageStats($record, $this->getFilterDate())['last_at'])
                    ->dateTime('H:i'),
            ])
            ->filters([
                Filter::make('date')
                    ->form([
                        DatePicker::make('date')
                            ->label('التاريخ')
                            ->default(fn() => WhatsAppMessageHistory::query()
                                ->selectRaw('DATE(created_at) as date')
                                ->orderByDesc('created_at')
                                ->value('date') ?? now()->toDateString())
                            ->native(false)
                            ->maxDate(now()),
                    ])
                    ->query(function (Builder $query, array $data): void {
                        $date = $data['date'] ?? now()->toDateString();

                        // WA history stores cleaned phones (e.g. 212XXXXXXXXX),
                        // students store raw phones (e.g. 0XXXXXXXXX), so we
                        // resolve the match in PHP rather than a direct SQL column compare.
                        $remindedPhones = WhatsAppMessageHistory::query()
                            ->whereDate('created_at', $date)
                            ->pluck('recipient_phone')
                            ->toArray();

                        if (empty($remindedPhones)) {
                            $query->whereRaw('1 = 0');
                            return;
                        }

                        $studentIds = Student::query()
                            ->whereNotNull('phone')
                            ->get(['id', 'phone'])
                            ->filter(fn($s) => in_array(PhoneHelper::cleanPhoneNumber($s->phone), $remindedPhones))
                            ->pluck('id');

                        if ($studentIds->isEmpty()) {
                            $query->whereRaw('1 = 0');
                            return;
                        }

                        $query->whereHas('students', fn($q) => $q->whereIn('id', $studentIds));
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (! $data['date']) {
                            return null;
                        }

                        return 'التاريخ: '.Carbon::parse($data['date'])->translatedFormat('d F Y');
                    }),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->emptyStateHeading('لا توجد مجموعات مذكَّرة')
            ->emptyStateDescription('لم يتم إرسال أي تذكيرات في هذا اليوم')
            ->emptyStateIcon('heroicon-o-bell-slash')
            ->paginated(false);
    }
}
