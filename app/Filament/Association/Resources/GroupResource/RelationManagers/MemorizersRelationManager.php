<?php

namespace App\Filament\Association\Resources\GroupResource\RelationManagers;

use App\Filament\Association\Resources\MemorizerResource;
use App\Models\Attendance;
use App\Models\Memorizer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Mpdf\Mpdf;

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
                TextColumn::make('number')
                    ->label('الرقم')
                    ->state(function ($record, $rowLoop) {
                        return $rowLoop->iteration;
                    })
                    ->sortable(),
                IconColumn::make('attendance_today')
                    ->label('حاضر')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->getStateUsing(fn(Memorizer $record) => $record->present_today),
                TextColumn::make('name')
                    ->searchable()
                    ->url(fn(Memorizer $record) => "tel:{$record->phone}")
                    ->description(fn(Memorizer $record) => $record->phone)
                    ->color(fn(Memorizer $record) => $record->present_today ? 'success' : ($record->absent_today ? 'danger' : 'default'))
                    ->label('الإسم')
                    ->sortable(),

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
                Action::make('generate_badge')
                    ->label('إنشاء بطاقة')
                    ->icon('heroicon-o-identification')
                    ->action(function (Memorizer $record) {
                        // Generate QR Code
                        $data = json_encode(['memorizer_id' => $record->id]);
                        $renderer = new ImageRenderer(
                            new RendererStyle(400),
                            new SvgImageBackEnd
                        );
                        $writer = new Writer($renderer);
                        $svg = $writer->writeString($data);
                        $qrCode = 'data:image/svg+xml;base64,' . base64_encode($svg);

                        // Generate Badge HTML
                        $badgeHtml = view('badges.student', [
                            'memorizer' => $record,
                            'qrCode' => $qrCode,
                        ])->render();

                        // Generate PDF with mpdf
                        $mpdf = new Mpdf([
                            'mode' => 'utf-8',
                            'format' => 'A4',
                            'orientation' => 'P',
                            'margin_left' => 0,
                            'margin_right' => 0,
                            'margin_top' => 0,
                            'margin_bottom' => 0,
                        ]);

                        $mpdf->SetDirectionality('rtl');
                        $mpdf->autoScriptToLang = true;
                        $mpdf->autoLangToFont = true;

                        $mpdf->WriteHTML($badgeHtml);

                        return response()->streamDownload(function () use ($mpdf) {
                            echo $mpdf->Output('', 'S');
                        }, "badge_{$record->id}.pdf");
                    }),
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
                Action::make('pay_monthly_fee')
                    ->label('تسديد الرسوم الشهرية')
                    ->requiresConfirmation()
                    ->icon('heroicon-o-currency-dollar')
                    ->color('indigo')
                    ->hidden(fn(Memorizer $record) => $record->hasPaymentThisMonth())
                    ->modalDescription('هل أنت متأكد من تسجيل دفع الرسوم الشهرية لهذا الطالب؟')
                    ->modalHeading('تأكيد تسديد الرسوم')
                    ->modalSubmitActionLabel('تأكيد الدفع')
                    ->action(function (Memorizer $record) {
                        $record->payments()->create([
                            'amount' => $record->exempt ? 0 : $this->ownerRecord->price,
                            'payment_date' => now(),
                        ]);

                        Notification::make()
                            ->title('تم الدفع بنجاح')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                BulkAction::make('pay_monthly_fee_bulk')
                    ->label('تسديد الرسوم للمحددين')
                    ->requiresConfirmation()
                    ->color('indigo')
                    ->icon('heroicon-o-currency-dollar')
                    ->modalDescription('هل أنت متأكد من تسجيل دفع الرسوم الشهرية للطلاب المحددين؟')
                    ->modalHeading('تأكيد تسديد الرسوم الجماعي')
                    ->modalSubmitActionLabel('تأكيد الدفع للجميع')
                    ->action(function ($livewire) {
                        $records = $livewire->getSelectedTableRecords();
                        $records = Memorizer::find($records);
                        $records->each(function (Memorizer $memorizer) {
                            if (! $memorizer->hasPaymentThisMonth()) {
                                $memorizer->payments()->create([
                                    'amount' => $memorizer->exempt ? 0 : $this->ownerRecord->price,
                                    'payment_date' => now(),
                                ]);
                            }
                        });

                        Notification::make()
                            ->title('تم الدفع بنجاح')
                            ->success()
                            ->send();
                    }),
                BulkAction::make('mark_attendance_bulk')
                    ->label('تسجيل الحضور للمحددين')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->action(function ($livewire) {
                        $records = $livewire->getSelectedTableRecords();
                        $records = Memorizer::find($records);
                        $records->each(function (Memorizer $memorizer) {
                            Attendance::firstOrCreate([
                                'memorizer_id' => $memorizer->id,
                                'date' => now()->toDateString(),
                            ], [
                                'check_in_time' => now()->toTimeString(),
                            ]);
                        });

                        Notification::make()
                            ->title('تم تسجيل الحضور بنجاح للطلاب المحددين')
                            ->success()
                            ->send();
                    }),
                BulkAction::make('mark_absence_bulk')
                    ->label('تسجيل الغياب للمحددين')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('تأكيد تسجيل الغياب الجماعي')
                    ->modalDescription('هل أنت متأكد من تسجيل الغياب للطلاب المحددين؟')
                    ->modalSubmitActionLabel('تأكيد الغياب للجميع')
                    ->action(function ($livewire) {
                        $records = $livewire->getSelectedTableRecords();
                        $records = Memorizer::find($records);
                        $records->each(function (Memorizer $memorizer) {
                            Attendance::updateOrCreate(
                                [
                                    'memorizer_id' => $memorizer->id,
                                    'date' => now()->toDateString(),
                                ],
                                [
                                    'check_in_time' => null,
                                ]
                            );
                        });

                        Notification::make()
                            ->title('تم تسجيل الغياب بنجاح للطلاب المحددين')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])->paginated(false);
    }
}
