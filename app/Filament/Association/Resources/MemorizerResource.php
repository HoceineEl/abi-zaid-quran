<?php

namespace App\Filament\Association\Resources;

use App\Classes\Core;
use App\Enums\Days;
use App\Filament\Association\Resources\GroupResource\RelationManagers\MemorizersRelationManager;
use App\Filament\Association\Resources\MemorizerResource\Pages;
use App\Filament\Association\Resources\MemorizerResource\RelationManagers\PaymentsRelationManager;
use App\Filament\Association\Resources\MemorizerResource\RelationManagers\ReminderLogsRelationManager;
use App\Filament\Exports\MemorizerExporter;
use App\Filament\Imports\MemorizerImporter;
use App\Models\MemoGroup;
use App\Models\Memorizer;
use App\Models\Round;
use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Filament\Actions\Action as ActionsAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Assets\Js;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Facades\FilamentAsset;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\ExportAction;
use Filament\Tables\Actions\ExportBulkAction;
use Filament\Tables\Actions\ImportAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rules\File;
use Livewire\Component;
use Mpdf\Mpdf;

use function GuzzleHttp\default_ca_bundle;

class MemorizerResource extends Resource
{
    protected static ?string $model = Memorizer::class;

    protected static ?string $navigationIcon = 'heroicon-o-user';

    protected static ?string $navigationLabel = 'الطلاب';

    protected static ?string $modelLabel = 'طالب';

    protected static ?string $pluralModelLabel = 'الطلاب';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->schema([
                        TextInput::make('name')
                            ->label('الإسم')
                            ->required(),

                        TextInput::make('phone')
                            ->label('الهاتف (خاص)')
                            ->helperText('سيتم استخدام رقم هاتف ولي الأمر إذا لم يتم تحديد رقم هاتف خاص'),
                        TextInput::make('address')
                            ->label('العنوان'),

                        DatePicker::make('birth_date')
                            ->label('تاريخ الميلاد'),
                        Select::make('memo_group_id')
                            ->label('المجموعة')
                            ->hiddenOn(MemorizersRelationManager::class)
                            ->relationship('group', 'name')
                            ->required(),


                        TextInput::make('city')
                            ->label('المدينة')
                            ->default('أسفي'),
                        FileUpload::make('photo')
                            ->image()
                            ->avatar()
                            ->directory('memorizers-photos')
                            ->maxSize(1024)
                            ->maxFiles(1)
                            ->imageEditor()
                            ->label('الصورة'),
                        Toggle::make('exempt')
                            ->label('معفى من الدفع')
                            ->default(false),
                    ])->columns(),
            ]);
    }

    public static function table(Table $table): Table
    {

        return $table
            ->columns([

                ImageColumn::make('photo')
                    ->label('الصورة')
                    ->circular()
                    ->searchable()
                    ->toggleable()
                    ->toggledHiddenByDefault()
                    ->sortable()
                    ->size(50),
                IconColumn::make('exempt')
                    ->searchable()
                    ->toggleable()
                    ->sortable()
                    ->label('معفي')
                    ->boolean(),

                TextColumn::make('name')
                    ->weight(FontWeight::Bold)
                    ->icon(function (Memorizer $record) {
                        if ($record->has_payment_this_month) {
                            return 'heroicon-o-check-circle';
                        }
                        if ($record->has_reminder_this_month) {
                            return 'heroicon-o-exclamation-circle';
                        }


                        return null;
                    })
                    ->searchable()
                    ->color(function (Memorizer $record) {
                        if ($record->has_payment_this_month) {
                            return 'success';
                        }
                        if ($record->has_reminder_this_month) {
                            return 'warning';
                        }

                        return 'danger';
                    })
                    ->action(MemorizersRelationManager::getPayAction())
                    ->toggleable()
                    ->sortable()
                    ->label('الإسم'),
                TextColumn::make('group.name')
                    ->searchable()
                    ->toggleable()
                    ->badge()
                    ->color('indigo')
                    ->sortable()
                    ->label('المجموعة'),
                TextColumn::make('phone')
                    ->searchable(query: fn($query, string $search) => $query->where(function ($query) use ($search) {
                        $query->where('phone', $search)
                            ->orWhereHas('guardian', function ($query) use ($search) {
                                $query->where('phone', $search);
                            });
                    }))
                    ->toggleable()
                    ->copyable()
                    ->copyMessage('تم نسخ الهاتف')
                    ->copyMessageDuration(1500)
                    ->extraCellAttributes(['dir' => 'ltr'])
                    ->alignRight()
                    ->sortable(false)
                    ->label('الهاتف')
                    ->html()
                    ->description(fn($record) => $record->city),
                TextColumn::make('number')
                    ->label('الرقم')
                    ->searchable(query: fn($query, string $search) => $query->where(function ($query) use ($search) {
                        $query->whereRaw("CONCAT(DATE_FORMAT(memorizers.created_at, '%y%m%d'), LPAD(memorizers.id, 2, '0')) = ?", [$search]);
                    }))
                    ->toggleable()
                    ->sortable(
                        query: fn($query, string $direction) => $query->orderByRaw("CONCAT(DATE_FORMAT(created_at, '%y%m%d'), LPAD(id, 2, '0')) $direction")
                    )
                    ->copyable()
                    ->copyMessage('تم نسخ الرقم')
                    ->copyMessageDuration(1500),
                TextColumn::make('birth_date')
                    ->date('Y-m-d')
                    ->searchable()
                    ->toggleable()
                    ->sortable()
                    ->label('تاريخ الإزدياد')
                    ->description(fn(Memorizer $record) => match ($record->sex) {
                        'male' => 'ذكر',
                        'female' => 'أنثى',
                        default => 'ذكر',
                    }),



            ])

            ->headerActions([


                ExportAction::make()
                    ->label('تصدير البيانات')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->exporter(MemorizerExporter::class),

                ImportAction::make()
                    ->label('استيراد البيانات')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->importer(MemorizerImporter::class),
            ])
            ->actions([
                ActionGroup::make([
                    Tables\Actions\EditAction::make()->slideOver(),
                    MemorizersRelationManager::getTroublesAction(),
                ]),
                Action::make('generate_badge')
                    ->tooltip('إنشاء بطاقة')
                    ->label('')
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
                    // ->hidden(function (Memorizer $record) {
                    //     // Skip if no phone number available
                    //     if (!$record->phone && !$record->guardian?->phone) {
                    //         return true;
                    //     }

                    //     // Skip if student has already paid this month
                    //     if ($record->has_payment_this_month) {
                    //         return true;
                    //     }
                    // })
                    ->url(function (Memorizer $record) {

                        return self::getWhatsAppUrl($record);
                    }, true),




            ], ActionsPosition::BeforeColumns)
            ->bulkActions([
                ExportBulkAction::make()
                    ->label('تصدير البيانات')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->exporter(MemorizerExporter::class),
                Tables\Actions\BulkAction::make('pay_this_month')
                    ->label('دفع الشهر')
                    ->requiresConfirmation()
                    ->modalDescription('هل أنت متأكد من دفع الشهر للطلاب المحددين؟')
                    ->modalHeading('دفع الشهر')
                    ->action(function ($livewire) {
                        $records = $livewire->getSelectedTableRecords();
                        $records = Memorizer::find($records);
                        foreach ($records as $record) {
                            $record->payments()->create([
                                'amount' => $record->group->price ?? 70,
                                'payment_date' => now(),
                            ]);
                        }

                        Notification::make()
                            ->title('تم الدفع بنجاح')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\BulkAction::make('attach_to_group')
                    ->label('إلحاق بمجموعة')
                    ->icon('heroicon-o-user-group')
                    ->form([
                        Select::make('memo_group_id')
                            ->label('المجموعة')
                            ->options(MemoGroup::pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required(),
                    ])
                    ->action(function (array $data, $livewire) {
                        $recordIds = $livewire->getSelectedTableRecords()->pluck('id')->toArray();
                        Memorizer::whereIn('id', $recordIds)->update(['memo_group_id' => $data['memo_group_id']]);

                        Notification::make()
                            ->title('تم إلحاق الطلاب بالمجموعة بنجاح')
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])->filters([
                Tables\Filters\SelectFilter::make('memo_group_id')
                    ->label('المجموعة')
                    ->relationship('group', 'name')
                    ->multiple()
                    ->preload(),

                Tables\Filters\SelectFilter::make('teacher_id')
                    ->label('المعلم')
                    ->options(User::where('role', 'teacher')->pluck('name', 'id'))
                    ->preload()
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when($data['value'], function ($query) use ($data) {
                            $query->whereHas('group', function ($query) use ($data) {
                                $query->where('teacher_id', $data['value']);
                            });
                        });
                    }),

                TernaryFilter::make('exempt')
                    ->label('معفي')
                    ->options([
                        'yes' => 'معفي',
                        'no' => 'غير معفي',
                        'all' => 'الكل',
                    ]),
                Filter::make('doesnt_have_payment_this_month')
                    ->label('الدفع')
                    ->form([
                        Toggle::make('doesnt_have_payment_this_month')
                            ->label('لم يدفع هذا الشهر'),
                    ])
                    ->modifyQueryUsing(function (Builder $query, array $data) {
                        if ($data['doesnt_have_payment_this_month']) {
                            $query->where(function ($query) {
                                $query->where('exempt', false)
                                    ->whereDoesntHave('payments', function ($query) {
                                        $query->whereYear('payment_date', now()->year)->whereMonth('payment_date', now()->month);
                                    });
                            });
                        }
                    }),


                Tables\Filters\SelectFilter::make('sex')
                    ->label('الجنس')
                    ->options([
                        'male' => 'ذكر',
                        'female' => 'أنثى',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when($data['value'], function ($query) use ($data) {
                            $query->whereHas('teacher', fn ($q) => $q->where('sex', $data['value']));
                        });
                    }),

                Filter::make('no_sex_defined')
                    ->label('بدون جنس محدد')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->whereDoesntHave('teacher')),

                Filter::make('no_teacher')
                    ->label('بدون أستاذ')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->whereDoesntHave('teacher')),
            ])
            ->modifyQueryUsing(function (Builder $query) {
                $query
                    ->with(['group:id,name', 'round:id,name', 'guardian:id,name,phone', 'payments:id,memorizer_id,payment_date', 'reminderLogs:id,memorizer_id,created_at']);
            });
    }

    public static function getRelations(): array
    {
        return [
            PaymentsRelationManager::class,
            ReminderLogsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMemorizers::route('/'),
            'create' => Pages\CreateMemorizer::route('/create'),
            'edit' => Pages\EditMemorizer::route('/{record}/edit'),
        ];
    }

    public static function getWhatsAppUrl(Memorizer $record)
    {
        // Determine if we should contact parent or student
        $isParent = !$record->phone && $record->guardian?->phone;
        $phone = $isParent ? $record->guardian?->phone : $record->phone;

        // Return null if no phone number available
        if (!$phone) {
            return null;
        }

        // Format phone number
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phone) === 9 && in_array(substr($phone, 0, 1), ['6', '7'])) {
            $phone = '+212' . $phone;
        } elseif (strlen($phone) === 10 && in_array(substr($phone, 0, 2), ['06', '07'])) {
            $phone = '+212' . substr($phone, 1);
        } elseif (strlen($phone) === 12 && substr($phone, 0, 3) === '212') {
            $phone = '+' . $phone;
        }

        $message = <<<MSG
        السلام عليكم ورحمة الله وبركاته 
        معكم إدارة جمعية بن ابي زيد القرواني  (دار القرآن الكريم)
        نذكركم بأداء الواجب الشهري 
        ولكم جزيل الشكر
        MSG;

        return $phone ? route('memorizer-whatsapp', [
            'number' => $phone,
            'message' => $message,
            'memorizer_id' => $record->id
        ]) : null;
    }
}
