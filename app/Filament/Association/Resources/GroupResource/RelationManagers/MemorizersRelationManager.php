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
use Filament\Forms\Components\TextInput;
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
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Enums\ActionsPosition;
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
                IconColumn::make('has_payment_this_month')
                    ->label('الدفع')
                    ->boolean()
                    ->tooltip(fn(Memorizer $record) => $record->has_payment_this_month ? 'تم الدفع' : 'إضغط لتسجيل الدفع')
                    ->action(
                        Action::make('pay_this_month')
                            ->label('دفع')
                            ->icon('heroicon-o-currency-dollar')
                            ->color('success')
                            ->requiresConfirmation()
                            ->hidden(fn(Memorizer $record) => $record->has_payment_this_month)
                            ->modalDescription('هل تريد تسجيل دفعة جديدة لهذا الشهر؟')
                            ->modalHeading('تسجيل دفعة جديدة')
                            ->form(function (Memorizer $record) {
                                return [
                                    TextInput::make('amount')
                                        ->label('المبلغ')
                                        ->helperText('المبلغ المستحق للشهر')
                                        ->numeric()
                                        ->default(fn() => $record->group->price ?? 100),
                                ];
                            })
                            ->action(function (Memorizer $record, array $data) {
                                $record->payments()->create([
                                    'amount' => $data['amount'],
                                    'payment_date' => now(),
                                ]);

                                Notification::make()
                                    ->title('تم تسجيل الدفعة بنجاح')
                                    ->success()
                                    ->send();
                            }),
                    )
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle'),
                TextColumn::make('name')
                    ->searchable()
                    ->color(fn(Memorizer $record) => $record->present_today ? 'success' : ($record->absent_today ? 'danger' : 'default'))
                    ->label('الإسم')
                    ->sortable(),
                TextColumn::make('phone')
                    ->label('رقم الهاتف')
                    ->copyable()
                    ->copyMessage('تم نسخ رقم الهاتف')
                    ->copyMessageDuration(1500),
                ToggleColumn::make('exempt')
                    ->label('معفي')
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

                Action::make('send_payment_reminders')
                    ->tooltip('إرسال تذكير بالدفع')
                    ->iconButton()
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->hidden(function (Memorizer $record) {
                        // Skip if no phone number available
                        if (!$record->phone && !$record->guardian?->phone) {
                            return true;
                        }

                        // Skip if student has already paid this month
                        if ($record->has_payment_this_month) {
                            return true;
                        }
                    })
                    ->url(function (Memorizer $record) {

                        return MemorizerResource::getWhatsAppUrl($record);
                    }, true),
            ], ActionsPosition::BeforeColumns)
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
                            if (! $memorizer->has_payment_this_month) {
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
