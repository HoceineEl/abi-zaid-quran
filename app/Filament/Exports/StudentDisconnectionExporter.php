<?php

namespace App\Filament\Exports;

use App\Enums\DisconnectionStatus;
use App\Enums\MessageResponseStatus;
use App\Enums\StudentReactionStatus;
use App\Models\StudentDisconnection;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Actions\Exports\Models\Export;

class StudentDisconnectionExporter extends Exporter
{
    protected static ?string $model = StudentDisconnection::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('student.name')
                ->label('اسم الطالب'),
            ExportColumn::make('group.name')
                ->label('المجموعة'),
            ExportColumn::make('disconnection_date')
                ->label('تاريخ الانقطاع')
                ->formatStateUsing(fn($state) => $state ? $state->format('Y-m-d') : ''),
            ExportColumn::make('disconnection_duration')
                ->label('مدة الانقطاع')
                ->formatStateUsing(function ($state, StudentDisconnection $record) {
                    $daysSinceLastPresent = $record->student->getDaysSinceLastPresentAttribute();
                    if ($daysSinceLastPresent === null) {
                        return 'غير محدد';
                    }
                    return $daysSinceLastPresent . ' يوم';
                }),
            ExportColumn::make('contact_date')
                ->label('تاريخ التواصل')
                ->formatStateUsing(fn($state) => $state ? $state->format('Y-m-d') : 'لم يتم التواصل'),
            ExportColumn::make('message_response')
                ->label('حالة التواصل')
                ->formatStateUsing(fn($state) => match ($state) {
                    MessageResponseStatus::NotContacted => 'لم يتم التواصل',
                    MessageResponseStatus::ReminderMessage => 'الرسالة التذكيرية',
                    MessageResponseStatus::WarningMessage => 'الرسالة الإندارية',
                    null => 'لم يتم التواصل',
                    default => 'لم يتم التواصل',
                }),
            ExportColumn::make('reminder_message_date')
                ->label('تاريخ الرسالة التذكيرية')
                ->formatStateUsing(fn($state) => $state ? $state->format('Y-m-d') : 'لم يتم الإرسال'),
            ExportColumn::make('warning_message_date')
                ->label('تاريخ الرسالة الإندارية')
                ->formatStateUsing(fn($state) => $state ? $state->format('Y-m-d') : 'لم يتم الإرسال'),
            ExportColumn::make('student_reaction')
                ->label('تفاعل الطالب')
                ->formatStateUsing(fn($state) => match ($state) {
                    StudentReactionStatus::ReactedToReminder => 'تفاعل مع التذكير',
                    StudentReactionStatus::ReactedToWarning => 'تفاعل مع الإنذار',
                    StudentReactionStatus::PositiveResponse => 'استجابة إيجابية',
                    StudentReactionStatus::NegativeResponse => 'استجابة سلبية',
                    StudentReactionStatus::NoResponse => 'لم يستجب',
                    null => 'لا يوجد',
                    default => 'لا يوجد',
                }),
            ExportColumn::make('student_reaction_date')
                ->label('تاريخ التفاعل')
                ->formatStateUsing(fn($state) => $state ? $state->format('Y-m-d') : 'لا يوجد'),
            ExportColumn::make('status')
                ->label('الحالة')
                ->formatStateUsing(fn($state) => match ($state) {
                    DisconnectionStatus::Disconnected => 'منقطع',
                    DisconnectionStatus::Contacted => 'تم الاتصال',
                    DisconnectionStatus::Responded => 'تم التواصل',
                    null => 'غير محدد',
                    default => 'غير محدد',
                }),
            ExportColumn::make('notes')
                ->label('ملاحظات'),
            ExportColumn::make('created_at')
                ->label('تاريخ الإنشاء')
                ->formatStateUsing(fn($state) => $state ? $state->format('Y-m-d H:i') : ''),
        ];
    }

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with(['student', 'group']);
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'تم تصدير ' . number_format($export->successful_rows) . ' ' . str('سجل')->plural($export->successful_rows) . ' بنجاح.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' فشل تصدير ' . number_format($failedRowsCount) . ' ' . str('سجل')->plural($failedRowsCount) . '.';
        }

        return $body;
    }

    public function getJobConnection(): ?string
    {
        return 'sync';
    }
}
