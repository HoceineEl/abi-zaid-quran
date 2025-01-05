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
use App\Models\Memorizer;
use App\Models\Round;
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
                        Select::make('guardian_id')
                            ->label('ولي الأمر')
                            ->relationship('guardian', 'name')
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->label('الإسم')
                                    ->required(),
                                TextInput::make('phone')
                                    ->label('الهاتف')
                                    ->required(),
                                TextInput::make('address')
                                    ->label('العنوان'),
                                TextInput::make('city')
                                    ->label('المدينة')
                                    ->default('أسفي'),
                            ])
                            ->searchable()
                            ->preload(),
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
                        Select::make('teacher_id')
                            ->label('المعلم')
                            ->relationship('teacher', 'name')
                            ->required(),
                        Select::make('round_id')
                            ->label('الحلقة')
                            ->relationship('round', 'name')
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->label('اسم الحلقة')
                                    ->required(),
                                CheckboxList::make('days')
                                    ->label('أيام الحلقة')
                                    ->options(Days::class)
                                    ->columns(3)
                                    ->required(),
                            ])
                            ->searchable()
                            ->preload(),
                        ToggleButtons::make('sex')
                            ->inline()
                            ->options([
                                'male' => 'ذكر',
                                'female' => 'أنثى',
                            ])->default('male')
                            ->label('الجنس')
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
                TextColumn::make('name')
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
                    ->toggleable()
                    ->sortable()
                    ->label('الإسم')
                    ->description(fn($record) => new HtmlString("
                        <div class='flex flex-col'>
                            <span class='text-xs text-gray-500'>{$record->city}</span>
                            <span class='text-sm'>{$record->address}</span>
                        </div>
                    ")),
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
                    ->sortable(false)
                    ->label('الهاتف')
                    ->html()
                    ->description(fn($record) => $record->city),
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
                TextColumn::make('group.name')
                    ->searchable()
                    ->toggleable()
                    ->sortable()
                    ->label('المجموعة'),
                TextColumn::make('round.name')
                    ->searchable()
                    ->toggleable()
                    ->sortable()
                    ->label('الحلقة'),
                TextColumn::make('teacher.name')
                    ->searchable()
                    ->toggleable()
                    ->sortable()
                    ->label('المعلم'),

                ToggleColumn::make('exempt')
                    ->searchable()
                    ->toggleable()
                    ->sortable()
                    ->label('معفي'),
            ])
            ->filters([
                //
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

                        return self::getWhatsAppUrl($record);
                    }, true),


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
                                'amount' => 100,
                                'payment_date' => now(),
                            ]);
                        }

                        Notification::make()
                            ->title('تم الدفع بنجاح')
                            ->success()
                            ->send();
                    }),
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
                    ->relationship('teacher', 'name')
                    ->multiple()
                    ->preload(),



            ])
            ->modifyQueryUsing(function (Builder $query) {
                $query->with(['group:id,name', 'teacher:id,name', 'round:id,name', 'guardian:id,name,phone', 'payments:id,memorizer_id,payment_date', 'reminderLogs:id,memorizer_id,created_at']);
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

        // Get gender-specific terms
        $genderTerms = $record->sex === 'female' ? [
            'prefix' => $isParent ? 'ابنتكم' : 'أختي الطالبة',
            'pronoun' => $isParent ? 'ها' : 'ك',
        ] : [
            'prefix' => $isParent ? 'ابنكم' : 'أخي الطالب',
            'pronoun' => $isParent ? 'ه' : 'ك',
        ];

        $message = <<<MSG
        السلام عليكم ورحمة الله وبركاته 🌸

        *{$genderTerms['prefix']} {$record->name}* الغالي(ة) ✨

        نرجو أن تكون{$genderTerms['pronoun']} بخير وعافية 💝
        نود تذكير{$genderTerms['pronoun']} بأن آخر موعد لأداء واجب التحفيظ الشهري هو يوم 5 من الشهر الحالي 📅

        جزا{$genderTerms['pronoun']} الله خيراً على تعاون{$genderTerms['pronoun']} معنا 🤲
        وبارك الله في{$genderTerms['pronoun']} وفي حفظ{$genderTerms['pronoun']} للقرآن الكريم 🌟
        MSG;

        return $phone ? route('memorizer-whatsapp', [
            'number' => $phone,
            'message' => $message,
            'memorizer_id' => $record->id
        ]) : null;
    }
}
