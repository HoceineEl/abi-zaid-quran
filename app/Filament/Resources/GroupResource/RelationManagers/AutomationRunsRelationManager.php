<?php

namespace App\Filament\Resources\GroupResource\RelationManagers;

use App\Services\GroupWhatsAppAutomationService;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AutomationRunsRelationManager extends RelationManager
{
    protected static string $relationship = 'automationRuns';

    protected static bool $isLazy = false;

    protected static ?string $title = 'سجل التشغيل التلقائي';

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $modelLabel = 'تشغيل تلقائي';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('phase')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('run_date')
                    ->label('التاريخ')
                    ->date('Y-m-d')
                    ->sortable(),
                TextColumn::make('command_name')
                    ->label('الأمر')
                    ->state(fn () => 'groups:run-whatsapp-automation')
                    ->copyable(),
                TextColumn::make('phase')
                    ->label('المرحلة')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        GroupWhatsAppAutomationService::EVENING_REMINDER_PASS => 'تشغيل 20:00',
                        GroupWhatsAppAutomationService::CLOSE_PASS => 'تشغيل الإغلاق',
                        default => $state,
                    }),
                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'running' => 'warning',
                        'skipped' => 'gray',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'completed' => 'مكتمل',
                        'running' => 'قيد التشغيل',
                        'skipped' => 'تم التخطي',
                        'failed' => 'فشل',
                        default => $state,
                    }),
                TextColumn::make('details.matched_count')
                    ->label('المطابقات')
                    ->default('0'),
                TextColumn::make('details.marked_count')
                    ->label('تم تسجيلهم')
                    ->default('0'),
                TextColumn::make('details.reminder_count')
                    ->label('التذكيرات')
                    ->default('0'),
                TextColumn::make('details.reason')
                    ->label('سبب التخطي')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('error_message')
                    ->label('الخطأ')
                    ->placeholder('—')
                    ->limit(60)
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('started_at')
                    ->label('بدأ في')
                    ->dateTime('Y-m-d H:i:s')
                    ->toggleable(),
                TextColumn::make('completed_at')
                    ->label('انتهى في')
                    ->dateTime('Y-m-d H:i:s')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('الحالة')
                    ->options([
                        'completed' => 'مكتمل',
                        'running' => 'قيد التشغيل',
                        'skipped' => 'تم التخطي',
                        'failed' => 'فشل',
                    ]),
                Tables\Filters\SelectFilter::make('phase')
                    ->label('المرحلة')
                    ->options([
                        GroupWhatsAppAutomationService::EVENING_REMINDER_PASS => 'تشغيل 20:00',
                        GroupWhatsAppAutomationService::CLOSE_PASS => 'تشغيل الإغلاق',
                    ]),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
