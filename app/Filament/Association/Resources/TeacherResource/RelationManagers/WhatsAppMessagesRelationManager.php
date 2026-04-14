<?php

namespace App\Filament\Association\Resources\TeacherResource\RelationManagers;

use Illuminate\Database\Eloquent\Model;
use App\Enums\WhatsAppMessageStatus;
use App\Models\User;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WhatsAppMessagesRelationManager extends RelationManager
{
    protected static string $relationship = 'whatsAppMessages';

    protected static ?string $title = 'رسائل واتساب';

    protected static ?string $modelLabel = 'رسالة';

    protected static ?string $pluralModelLabel = 'الرسائل';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->hasAssociationAccess() ?? false;
    }

    public function table(Table $table): Table
    {
        /** @var User $teacher */
        $teacher = $this->ownerRecord;

        return $table
            ->query(fn () => $teacher->whatsAppMessages())
            ->columns([
                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->color(fn (WhatsAppMessageStatus $state): string => $state->getColor())
                    ->formatStateUsing(fn (WhatsAppMessageStatus $state): string => $state->getLabel())
                    ->sortable(),

                TextColumn::make('recipient_name')
                    ->label('المستلم')
                    ->weight(FontWeight::Bold)
                    ->searchable(),

                TextColumn::make('recipient_phone')
                    ->label('الهاتف')
                    ->extraCellAttributes(['dir' => 'ltr'])
                    ->alignRight(),

                TextColumn::make('message_content')
                    ->label('الرسالة')
                    ->limit(50),

                TextColumn::make('created_at')
                    ->label('تاريخ الإرسال')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->multiple()
                    ->options(
                        collect(WhatsAppMessageStatus::cases())
                            ->mapWithKeys(fn (WhatsAppMessageStatus $case): array => [$case->value => $case->getLabel()])
                            ->all()
                    ),

                Filter::make('payment_reminders_only')
                    ->label('تذكيرات الدفع فقط')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereJsonContains('metadata->payment_reminder', true)),

                Filter::make('created_at')
                    ->label('التاريخ')
                    ->schema([
                        DatePicker::make('date')
                            ->label('تاريخ')
                            ->default(now()),
                    ])
                    ->indicateUsing(fn (array $data) => $data['date']
                        ? Carbon::parse($data['date'])->translatedFormat('d M Y')
                        : null)
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['date'], fn (Builder $q, $date) => $q->whereDate('created_at', $date))),
            ]);
    }
}
