<?php

namespace App\Filament\Resources\GroupResource\Pages;

use App\Filament\Resources\GroupResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions\Action;
use App\Classes\Core;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;

class ViewGroup extends ViewRecord
{
    protected static string $resource = GroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('edit')
                ->url(fn() => $this->getResource()::getUrl('edit', ['record' => $this->record]))
                ->icon('heroicon-o-pencil')
                ->visible(fn() => Core::canChange())
                ->label('تعديل'),

            Action::make('send_whatsapp_group')
                ->label('أرسل رسالة للغائبين')
                ->icon('heroicon-o-users')
                ->color('info')
                ->action(function () {
                    Core::sendMessageToAbsence($this->record);
                }),

            Action::make('remind_manager')
                ->label('تذكير المشرفين')
                ->icon('heroicon-o-bell')
                ->visible(fn() => auth()->user()->isAdministrator())
                ->form([
                    \Filament\Forms\Components\Textarea::make('message')
                        ->label('الرسالة')
                        ->default('من فضلكم قوموا بتسجيل الحضور للطلاب اليوم.')
                        ->rows(10)
                        ->required(),
                ])
                ->action(function (array $data) {
                    $data['message'] = $data['message'] ?? 'من فضلكم قوموا بتسجيل الحضور للطلاب اليوم.';
                    $data['students'] = $this->record->managers()->pluck('id')->toArray();
                    $data['message_type'] = 'custom';
                    Core::sendMessageToSpecific($data, 'manager');
                }),
        ];
    }


    public  function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make()
                    ->schema([
                        TextEntry::make('name')
                            ->label('اسم المجموعة'),
                        TextEntry::make('type')
                            ->label('نوع المجموعة')
                            ->formatStateUsing(
                                function ($state) {
                                    return match ($state) {
                                        'two_lines' => 'سطران',
                                        'half_page' => 'نصف صفحة',
                                        default => $state,
                                    };
                                }
                            ),
                        TextEntry::make('created_at')
                            ->label('تاريخ الإنشاء')
                            ->dateTime('Y-m-d H:i:s'),
                        TextEntry::make('students_count')
                            ->label('عدد الطلاب')
                            ->state(fn($record) => $record->students()->count()),
                        TextEntry::make('managers.name')
                            ->label('المشرفون')
                            ->bulleted(),
                    ])
                    ->columns(2),
            ]);
    }
}
