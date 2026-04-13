<?php

namespace App\Filament\Association\Resources;

use App\Enums\WhatsAppMessageStatus;
use App\Filament\Association\Resources\WhatsAppMessageHistoryResource\Pages;
use App\Jobs\SendWhatsAppMessageJob;
use App\Models\WhatsAppMessageHistory;
use App\Models\WhatsAppSession;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WhatsAppMessageHistoryResource extends Resource
{
    protected static ?string $model = WhatsAppMessageHistory::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-bottom-center-text';

    protected static ?string $navigationLabel = 'سجل الرسائل';

    protected static ?string $navigationGroup = 'التواصل';

    protected static ?int $navigationSort = 20;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAssociationAccess() ?? false;
    }

    public static function getModelLabel(): string
    {
        return 'رسالة';
    }

    public static function getPluralModelLabel(): string
    {
        return 'سجل الرسائل';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->forUserSession(auth()->id())->latest())
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
                    ->searchable()
                    ->description(fn (WhatsAppMessageHistory $record): ?string => $record->recipient_phone)
                    ->extraCellAttributes(['style' => 'direction: ltr; text-align: right;']),

                TextColumn::make('message_content')
                    ->label('الرسالة')
                    ->limit(80)
                    ->tooltip(fn (WhatsAppMessageHistory $record): ?string => $record->message_content)
                    ->searchable(),

                TextColumn::make('message_type')
                    ->label('النوع')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'text' => 'نصي',
                        'image' => 'صورة',
                        'document' => 'مستند',
                        'audio' => 'صوتي',
                        default => $state,
                    })
                    ->toggleable()
                    ->toggledHiddenByDefault(),

                TextColumn::make('sent_at')
                    ->label('وقت الإرسال')
                    ->dateTime('Y-m-d H:i')
                    ->description(fn (WhatsAppMessageHistory $record): ?string => $record->created_at?->diffForHumans())
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('retry_count')
                    ->label('المحاولات')
                    ->badge()
                    ->color('warning')
                    ->formatStateUsing(fn (int $state): ?int => $state > 0 ? $state : null)
                    ->toggleable()
                    ->toggledHiddenByDefault(),

                TextColumn::make('error_message')
                    ->label('سبب الفشل')
                    ->limit(60)
                    ->color('danger')
                    ->toggleable()
                    ->toggledHiddenByDefault(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->multiple()
                    ->options(
                        collect(WhatsAppMessageStatus::cases())
                            ->mapWithKeys(fn (WhatsAppMessageStatus $case): array => [$case->value => $case->getLabel()])
                            ->all()
                    ),

                Filter::make('date_range')
                    ->label('الفترة الزمنية')
                    ->form([
                        DatePicker::make('from')
                            ->label('من تاريخ')
                            ->native(false),
                        DatePicker::make('until')
                            ->label('إلى تاريخ')
                            ->native(false),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'], fn (Builder $q, string $date) => $q->whereDate('created_at', '>=', $date))
                        ->when($data['until'], fn (Builder $q, string $date) => $q->whereDate('created_at', '<=', $date))),

                Filter::make('payment_reminders_only')
                    ->label('تذكيرات الدفع فقط')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereJsonContains('metadata->payment_reminder', true)),
            ])
            ->actions([
                Action::make('view_message')
                    ->label('عرض الرسالة')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading('نص الرسالة الكاملة')
                    ->modalContent(fn (WhatsAppMessageHistory $record) => view(
                        'filament.association.whatsapp-message-preview',
                        ['message' => $record->message_content, 'record' => $record],
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('إغلاق'),

                Action::make('retry')
                    ->label('إعادة الإرسال')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (WhatsAppMessageHistory $record): bool => $record->isRetryable())
                    ->requiresConfirmation()
                    ->modalHeading('إعادة إرسال الرسالة')
                    ->modalDescription('سيتم إعادة جدولة الرسالة للإرسال.')
                    ->action(fn (WhatsAppMessageHistory $record) => self::retryMessage($record)),

                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('30s');
    }

    protected static function retryMessage(WhatsAppMessageHistory $record): void
    {
        $session = WhatsAppSession::find($record->session_id);

        if (! $session || ! $session->isConnected()) {
            Notification::make()
                ->title('جلسة واتساب غير متصلة')
                ->danger()
                ->send();

            return;
        }

        $record->update(['status' => WhatsAppMessageStatus::QUEUED]);

        SendWhatsAppMessageJob::dispatch(
            $session->id,
            $record->recipient_phone,
            $record->message_content,
            $record->message_type,
            $record->metadata['memorizer_id'] ?? null,
            ['sender_user_id' => auth()->id()],
        )->delay(now()->addSeconds(SendWhatsAppMessageJob::getStaggeredDelay($session->id)));

        Notification::make()
            ->title('تمت جدولة الرسالة للإرسال مجددًا')
            ->success()
            ->send();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWhatsAppMessageHistories::route('/'),
        ];
    }
}
