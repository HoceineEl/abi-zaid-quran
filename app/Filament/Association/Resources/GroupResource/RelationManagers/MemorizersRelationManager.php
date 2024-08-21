<?php

namespace App\Filament\Association\Resources\GroupResource\RelationManagers;

use App\Filament\Association\Resources\MemorizerResource;
use App\Helpers\ProgressFormHelper;
use App\Models\Memorizer;
use App\Models\Student;
use Filament\Forms;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Pagination\UrlWindow;

class MemorizersRelationManager extends RelationManager
{
    protected static string $relationship = 'memorizers';
    protected static bool $isLazy = false;

    protected static ?string $title = 'الطلبة';

    protected static ?string $navigationLabel = 'الطلبة';

    protected static ?string $modelLabel = 'طالب';

    protected static ?string $pluralModelLabel = 'الطلبة';
    public function form(Form $form): Form
    {
        return MemorizerResource::form($form);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('order_no')
                    ->getStateUsing(function ($record) {
                        $id = $record->id;
                        return  $this->ownerRecord->memorizers->pluck('id')->search($id) + 1;
                    })
                    ->label('الرقم'),
                TextColumn::make('name')
                    ->searchable()
                    ->label('الإسم')
                    ->sortable(),
                TextColumn::make('phone')
                    ->url(fn($record) => "tel:{$record->phone}", true)
                    ->badge()
                    ->searchable()
                    ->sortable()
                    ->label('رقم الهاتف'),
            ])
            ->filters([])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                    Tables\Actions\ViewAction::make(),
                ]),
                Tables\Actions\Action::make('send_whatsapp_msg')
                    ->color('success')
                    ->iconButton()
                    ->icon('heroicon-o-chat-bubble-oval-left')
                    ->label('إرسال رسالة واتساب')
                    ->modal()
                    ->form(
                        [
                            Textarea::make('message')
                                ->hint('السلام عليكم وإسم الطالب سيتم إضافته تلقائياً في  الرسالة.')
                                ->reactive()
                                ->default('نذكرك بالواجب الشهري، لعل المانع خير.')
                                ->label('الرسالة')
                                ->required(),
                        ]
                    )
                    ->action(function ($record, array $data, Action $action) {
                        $number = $record->phone;
                        if (substr($number, 0, 2) == '06' || substr($number, 0, 2) == '07') {
                            $number = '+212' . substr($number, 1);
                        }
                        $customMessage = $data['message'] ?? '';
                        $message = <<<EOT
                            *السلام عليكم ورحمة الله وبركاته*

                            أخي الطالب *{$record->name}*،

                            {$customMessage}

                            ---------------------
                            _جمعية إبن أبي زيد القيرواني_
                            EOT;

                        $whatsappUrl = "https://wa.me/{$number}?text=" . urlencode($message);
                        redirect($whatsappUrl);
                    }),
                Action::make('pay_this_month')
                    ->label('الدفع لهذا الشهر')
                    ->requiresConfirmation()
                    ->icon('tabler-cash')
                    ->color('indigo')
                    ->hidden(fn(Memorizer $record) =>  $record->hasPaymentThisMonth())
                    ->modalDescription('هل أنت متأكد من الدفع؟')
                    ->modalHeading('دفع الشهر')
                    ->action(function (Memorizer $record) {
                        $record->payments()->create([
                            'amount' => 100,
                            'payment_date' => now(),
                        ]);

                        Notification::make()
                            ->title('تم الدفع بنجاح')
                            ->success()
                            ->send();
                    }),
            ], ActionsPosition::BeforeColumns)
            ->bulkActions([
                Tables\Actions\BulkAction::make('pay_this_month')
                    ->label('دفع الشهر')
                    ->requiresConfirmation()
                    ->color('success')
                    ->icon('tabler-cash')
                    ->modalDescription('هل أنت متأكد من دفع الشهر للطلاب المحددين؟')
                    ->modalHeading('دفع الشهر')
                    ->action(function ($livewire) {
                        $records = $livewire->getSelectedTableRecords();
                        $records = Memorizer::find($records);
                        foreach ($records as $record) {
                            if (!$record->hasPaymentThisMonth()) {
                                $record->payments()->create([
                                    'amount' => $record->exempt ? 0 : $this->ownerRecord->price,
                                    'payment_date' => now(),
                                ]);
                            }
                        }

                        Notification::make()
                            ->title('تم الدفع بنجاح')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
