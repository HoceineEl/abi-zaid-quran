<?php

namespace App\Filament\Association\Resources;

use App\Enums\SchoolLevel;
use App\Exports\MemorizersFilteredExport;
use App\Exports\RegistrationImportFailuresExport;
use App\Exports\YearlyPaymentExport;
use App\Filament\Association\Actions\SendPaymentRemindersAction;
use App\Filament\Association\Actions\SendPaymentRemindersBulkAction;
use App\Filament\Association\Resources\GroupResource\RelationManagers\MemorizersRelationManager;
use App\Filament\Association\Resources\MemorizerResource\Pages;
use App\Filament\Association\Resources\MemorizerResource\RelationManagers\PaymentsRelationManager;
use App\Filament\Association\Resources\MemorizerResource\RelationManagers\ReminderLogsRelationManager;
use App\Filament\Exports\MemorizerExporter;
use App\Filament\Imports\MemorizerImporter;
use App\Models\MemoGroup;
use App\Models\Memorizer;
use App\Models\User;
use App\Models\WhatsAppMessageHistory;
use App\Services\RegistrationFormsImportService;
use App\Services\YearlyPaymentPdfService;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\ExportAction;
use Filament\Tables\Actions\ExportBulkAction;
use Filament\Tables\Actions\ImportAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;
use Mpdf\Mpdf;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;

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

                        ...static::studentProfileFields(),
                        Select::make('memo_group_id')
                            ->label('المجموعة')
                            ->hiddenOn(MemorizersRelationManager::class)
                            ->relationship('group', 'name')
                            ->placeholder('بدون مجموعة')
                            ->searchable()
                            ->preload(),

                        TextInput::make('city')
                            ->label('المدينة')
                            ->default('أسفي'),
                        FileUpload::make('photo')
                            ->image()
                            ->avatar()
                            ->directory('memorizers-photos')
                            ->maxSize(512)
                            ->imageResizeMode('cover')
                            ->imageResizeTargetWidth(200)
                            ->imageResizeTargetHeight(200)
                            ->label('الصورة'),
                        Toggle::make('exempt')
                            ->label('معفى من الدفع')
                            ->default(false),
                        Textarea::make('notes')
                            ->label('ملاحظات')
                            ->columnSpanFull(),
                    ])->columns(),
            ]);
    }

    /** @return array<int, \Filament\Forms\Components\Component> */
    public static function groupStudentFormSchema(): array
    {
        return [
            TextInput::make('name')
                ->label('الإسم الكامل')
                ->required()
                ->columnSpanFull(),
            ...static::studentProfileFields(),
            PhoneInput::make('phone')
                ->label('هاتف ولي الأمر')
                ->initialCountry('ma')
                ->defaultCountry('MA')
                ->formatAsYouType()
                ->showFlags(),
            TextInput::make('address')
                ->label('العنوان'),
            TextInput::make('city')
                ->label('المدينة')
                ->default('أسفي'),
        ];
    }

    /** @return array<int, \Filament\Forms\Components\Field> */
    public static function studentProfileFields(): array
    {
        return [
            DatePicker::make('birth_date')
                ->label('تاريخ الازدياد')
                ->maxDate(now())
                ->required(),
            Select::make('school_level')
                ->label('المستوى الدراسي')
                ->options(SchoolLevel::class)
                ->required(),
            TextInput::make('memorized_hizbs')
                ->label('المحفوظ الإجمالي')
                ->numeric()
                ->minValue(0)
                ->maxValue(60)
                ->suffix('حزب')
                ->helperText('عدد الأحزاب التي يحفظها الطالب، من 0 إلى 60.')
                ->required(),
            Toggle::make('attended_summer_session')
                ->label('حضر الدورة الصيفية')
                ->inline(false)
                ->default(false),
        ];
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
                    ->placeholder('بدون مجموعة')
                    ->color('indigo')
                    ->sortable()
                    ->label('المجموعة'),
                TextColumn::make('phone')
                    ->searchable(query: fn ($query, string $search) => $query->where(function ($query) use ($search) {
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
                    ->description(fn ($record) => $record->city),
                TextColumn::make('number')
                    ->label('الرقم')
                    ->searchable(query: fn ($query, string $search) => $query->where(function ($query) use ($search) {
                        $query->whereRaw("CONCAT(DATE_FORMAT(memorizers.created_at, '%y%m%d'), LPAD(memorizers.id, 2, '0')) = ?", [$search]);
                    }))
                    ->toggleable()
                    ->sortable(
                        query: fn ($query, string $direction) => $query->orderByRaw("CONCAT(DATE_FORMAT(created_at, '%y%m%d'), LPAD(id, 2, '0')) $direction")
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
                    ->description(fn (Memorizer $record) => match ($record->sex) {
                        'male' => 'ذكر',
                        'female' => 'أنثى',
                        default => 'ذكر',
                    }),
                TextColumn::make('age')
                    ->label('العمر')
                    ->badge()
                    ->color('gray')
                    ->toggleable()
                    ->placeholder('—')
                    ->sortable(query: fn ($query, string $direction) => $query->orderBy('birth_date', $direction === 'asc' ? 'desc' : 'asc')),
                TextColumn::make('school_level')
                    ->label('المستوى الدراسي')
                    ->badge()
                    ->color('info')
                    ->toggleable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('memorized_hizbs')
                    ->label('المحفوظ الإجمالي')
                    ->suffix(' حزب')
                    ->toggleable()
                    ->sortable()
                    ->placeholder('—'),
                IconColumn::make('attended_summer_session')
                    ->label('الدورة الصيفية')
                    ->boolean()
                    ->toggleable()
                    ->sortable(),

            ])

            ->headerActions([
                SendPaymentRemindersAction::make(),

                Action::make('export_attendance_grades')
                    ->label('تصدير حضور وتقييم Excel')
                    ->icon('heroicon-o-table-cells')
                    ->color('primary')
                    ->form([
                        Select::make('memo_group_id')
                            ->label('المجموعة')
                            ->options(fn () => MemoGroup::orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->placeholder('كل المجموعات'),
                        ...GroupResource::getAttendanceExportFormSchema(),
                    ])
                    ->action(function (array $data) {
                        if (blank($data['memo_group_id'] ?? null)) {
                            return GroupResource::exportAllAttendanceWorkbooks($data);
                        }

                        $group = MemoGroup::find($data['memo_group_id']);

                        if (! $group) {
                            Notification::make()
                                ->title('تعذر العثور على المجموعة')
                                ->danger()
                                ->send();

                            return null;
                        }

                        return GroupResource::exportAttendanceWorkbook($group, $data);
                    }),

                Action::make('export_yearly_payments')
                    ->label('تصدير متابعة الأداء السنوية')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('warning')
                    ->modalHeading('تصدير متابعة أداء الواجب')
                    ->modalDescription('سيتم تصدير جميع الطلاب مع حالة كل شهر داخل الفترة المحددة (مدفوع / غير مدفوع / معفي).')
                    ->modalSubmitActionLabel('تصدير')
                    ->form([
                        ToggleButtons::make('format')
                            ->label('صيغة التصدير')
                            ->options([
                                'pdf' => 'PDF',
                                'excel' => 'Excel',
                            ])
                            ->icons([
                                'pdf' => 'heroicon-o-document-text',
                                'excel' => 'heroicon-o-table-cells',
                            ])
                            ->colors([
                                'pdf' => 'danger',
                                'excel' => 'success',
                            ])
                            ->default('pdf')
                            ->inline()
                            ->required(),
                        DatePicker::make('start_date')
                            ->label('تاريخ البداية')
                            ->native(false)
                            ->displayFormat('Y-m-d')
                            ->default('2025-09-01')
                            ->required(),
                        DatePicker::make('end_date')
                            ->label('تاريخ النهاية')
                            ->native(false)
                            ->displayFormat('Y-m-d')
                            ->default(now()->toDateString())
                            ->required()
                            ->afterOrEqual('start_date'),
                    ])
                    ->action(function (array $data) {
                        $start = Carbon::parse($data['start_date']);
                        $end = Carbon::parse($data['end_date']);

                        if ($end->lt($start)) {
                            Notification::make()
                                ->title('تاريخ النهاية يجب أن يكون بعد تاريخ البداية')
                                ->danger()
                                ->send();

                            return null;
                        }

                        $baseName = "متابعة-أداء-الواجب-{$start->format('Y-m-d')}-إلى-{$end->format('Y-m-d')}";

                        if ($data['format'] === 'excel') {
                            return Excel::download(
                                new YearlyPaymentExport($start, $end),
                                "{$baseName}.xlsx",
                            );
                        }

                        $pdf = app(YearlyPaymentPdfService::class)->generate($start, $end);

                        return response()->streamDownload(
                            function () use ($pdf) {
                                echo $pdf;
                            },
                            "{$baseName}.pdf",
                            ['Content-Type' => 'application/pdf'],
                        );
                    }),

                Action::make('export_filtered_memorizers')
                    ->label('تصدير اللائحة المفلترة Excel')
                    ->icon('heroicon-o-table-cells')
                    ->color('success')
                    ->modalHeading('تصدير اللائحة المفلترة')
                    ->modalDescription('سيتم تصدير الطلاب الظاهرين حاليا حسب الفلاتر والبحث المطبقين.')
                    ->modalSubmitActionLabel('تصدير')
                    ->form([
                        TextInput::make('heading')
                            ->label('عنوان الملف')
                            ->default('لائحة الطلاب')
                            ->required(),
                    ])
                    ->action(function (array $data, Component $livewire) {
                        $query = $livewire->getFilteredSortedTableQuery();

                        if (! $query->clone()->exists()) {
                            Notification::make()
                                ->title('لا يوجد طلاب مطابقون للفلاتر المحددة')
                                ->warning()
                                ->send();

                            return null;
                        }

                        return Excel::download(
                            new MemorizersFilteredExport($query, $data['heading']),
                            $data['heading'].'-'.now()->format('Y-m-d').'.xlsx',
                        );
                    }),

                ExportAction::make()
                    ->label('تصدير البيانات')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->exporter(MemorizerExporter::class),

                Action::make('import_registration_forms')
                    ->label('استيراد استمارات التسجيل')
                    ->icon('heroicon-o-document-arrow-up')
                    ->color('primary')
                    ->modalHeading('استيراد استمارات التسجيل')
                    ->modalDescription('ارفع ملف أو عدة ملفات مملوءة من نموذج التسجيل. التلاميذ المكررون (نفس الاسم ورقم الواتساب) يُتجاهلون تلقائيًا.')
                    ->modalSubmitActionLabel('استيراد')
                    ->form([
                        FileUpload::make('files')
                            ->label('ملفات Excel')
                            ->multiple()
                            ->required()
                            ->disk('local')
                            ->directory('registration-imports')
                            ->storeFileNamesIn('file_names')
                            ->acceptedFileTypes([
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'application/vnd.ms-excel',
                            ])
                            ->maxSize(10240),
                    ])
                    ->extraModalFooterActions([
                        Action::make('download_registration_template')
                            ->label('تحميل نموذج التسجيل')
                            ->icon('heroicon-o-arrow-down-tray')
                            ->color('gray')
                            ->action(fn () => response()->download(
                                resource_path(RegistrationFormsImportService::TEMPLATE_PATH),
                                'نموذج-تسجيل-التلاميذ.xlsx',
                            )),
                    ])
                    ->action(function (array $data, RegistrationFormsImportService $importer) {
                        $disk = Storage::disk('local');
                        $files = collect($data['files'])
                            ->mapWithKeys(fn (string $path): array => [$disk->path($path) => $data['file_names'][$path] ?? basename($path)])
                            ->all();

                        $result = $importer->import($files);
                        $disk->delete($data['files']);

                        $failedCount = count($result['failures']);

                        Notification::make()
                            ->title("تم استيراد {$result['created']} تلميذ")
                            ->body(collect([
                                "واجب التسجيل: {$result['registration_payments']} — شهر أكتوبر: {$result['monthly_payments']}",
                                $result['duplicates'] ? "مكرر تم تجاهله: {$result['duplicates']}" : null,
                                $failedCount ? "أسطر لم تُستورد: {$failedCount} (تم تحميل ملف بالأسباب)" : null,
                            ])->filter()->implode('<br>'))
                            ->color($failedCount ? 'warning' : 'success')
                            ->icon($failedCount ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-circle')
                            ->persistent()
                            ->send();

                        if ($failedCount) {
                            return Excel::download(
                                new RegistrationImportFailuresExport($result['failures']),
                                'أسطر-غير-مستوردة-'.now()->format('Y-m-d-His').'.xlsx',
                            );
                        }

                        return null;
                    }),

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
                        $qrCode = 'data:image/svg+xml;base64,'.base64_encode($svg);

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
                    ->url(fn (Memorizer $record) => self::getWhatsAppUrl($record), true),
                Action::make('view_messages')
                    ->tooltip('سجل الرسائل')
                    ->iconButton()
                    ->icon('heroicon-o-envelope')
                    ->color('info')
                    ->visible(fn (): bool => auth()->user()?->hasAssociationAccess() ?? false)
                    ->modalHeading(fn (Memorizer $record) => "رسائل {$record->name}")
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('إغلاق')
                    ->modalWidth('3xl')
                    ->modalContent(fn (Memorizer $record) => view(
                        'filament.association.memorizer-messages',
                        ['messages' => WhatsAppMessageHistory::where('metadata->memorizer_id', $record->id)
                            ->latest()
                            ->limit(20)
                            ->get()],
                    )),

            ], ActionsPosition::BeforeColumns)
            ->bulkActions([
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
                SendPaymentRemindersBulkAction::make(),
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('attach_to_group')
                        ->label('إلحاق بمجموعة')
                        ->icon('heroicon-o-user-group')
                        ->form([
                            Select::make('memo_group_id')
                                ->label('المجموعة')
                                ->options(fn () => MemoGroup::pluck('name', 'id'))
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
                    ExportBulkAction::make()
                        ->label('تصدير البيانات')
                        ->icon('heroicon-o-arrow-up-tray')
                        ->exporter(MemorizerExporter::class),
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

                Filter::make('age')
                    ->label('العمر')
                    ->form([
                        Select::make('ages')
                            ->label('أعمار محددة')
                            ->multiple()
                            ->options(array_combine(range(3, 40), range(3, 40)))
                            ->helperText('اختر عمرا أو أكثر (بالسنوات الكاملة)'),
                        TextInput::make('min_age')
                            ->label('من عمر')
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('max_age')
                            ->label('إلى عمر')
                            ->numeric()
                            ->minValue(0),
                    ])
                    ->columns(3)
                    ->query(function (Builder $query, array $data): Builder {
                        $ages = array_map('intval', array_filter($data['ages'] ?? [], fn ($age) => filled($age)));

                        return $query
                            ->when($ages, fn (Builder $q) => $q->whereNotNull('birth_date')->where(
                                fn (Builder $q) => collect($ages)->each(fn (int $age) => $q->orWhere(
                                    fn (Builder $q) => $q->ageBetween($age, $age),
                                )),
                            ))
                            ->when(
                                filled($data['min_age'] ?? null) || filled($data['max_age'] ?? null),
                                fn (Builder $q) => $q->ageBetween(
                                    filled($data['min_age'] ?? null) ? (int) $data['min_age'] : null,
                                    filled($data['max_age'] ?? null) ? (int) $data['max_age'] : null,
                                ),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if (filled($data['ages'] ?? [])) {
                            $indicators[] = 'العمر: '.implode('، ', $data['ages']);
                        }

                        if (filled($data['min_age'] ?? null)) {
                            $indicators[] = 'من عمر '.$data['min_age'];
                        }

                        if (filled($data['max_age'] ?? null)) {
                            $indicators[] = 'إلى عمر '.$data['max_age'];
                        }

                        return $indicators;
                    }),

                Tables\Filters\SelectFilter::make('school_level')
                    ->label('المستوى الدراسي')
                    ->options(SchoolLevel::class)
                    ->multiple(),
                Filter::make('memorized_hizbs')
                    ->label('المحفوظ الإجمالي')
                    ->form([
                        TextInput::make('min_hizbs')
                            ->label('من حزب')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(60),
                        TextInput::make('max_hizbs')
                            ->label('إلى حزب')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(60),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(filled($data['min_hizbs'] ?? null), fn (Builder $q) => $q->where('memorized_hizbs', '>=', (int) $data['min_hizbs']))
                        ->when(filled($data['max_hizbs'] ?? null), fn (Builder $q) => $q->where('memorized_hizbs', '<=', (int) $data['max_hizbs'])))
                    ->indicateUsing(fn (array $data): array => array_values(array_filter([
                        filled($data['min_hizbs'] ?? null) ? 'من حزب '.$data['min_hizbs'] : null,
                        filled($data['max_hizbs'] ?? null) ? 'إلى حزب '.$data['max_hizbs'] : null,
                    ]))),
                TernaryFilter::make('attended_summer_session')
                    ->label('الدورة الصيفية')
                    ->trueLabel('حضر الدورة الصيفية')
                    ->falseLabel('لم يحضر الدورة الصيفية')
                    ->queries(
                        true: fn (Builder $query) => $query->attendedSummerSession(),
                        false: fn (Builder $query) => $query->attendedSummerSession(false),
                    ),
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
                                        $query->monthly()->whereYear('payment_date', now()->year)->whereMonth('payment_date', now()->month);
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

                Filter::make('created_after')
                    ->label('تاريخ الإنشاء بعد')
                    ->form([
                        \Filament\Forms\Components\DateTimePicker::make('created_after')
                            ->label('تم إنشاؤه بعد'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['created_after'],
                            fn (Builder $query, $date): Builder => $query->where('created_at', '>=', $date),
                        );
                    }),
            ])
            ->modifyQueryUsing(function (Builder $query) {
                $query
                    ->with(['group:id,name', 'round:id,name', 'guardian:id,name,phone', 'payments:id,memorizer_id,payment_date', 'reminderLogs:id,memorizer_id,created_at']);
            })
            ->recordClasses(fn (Memorizer $record): ?string => ! $record->has_payment_this_month && $record->has_sent_reminder_this_month
                ? 'bg-yellow-50 dark:bg-yellow-950/20'
                : null);
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
        ];
    }

    public static function getWhatsAppUrl(Memorizer $record)
    {
        // Determine if we should contact parent or student
        $isParent = ! $record->phone && $record->guardian?->phone;
        $phone = $isParent ? $record->guardian?->phone : $record->phone;

        // Return null if no phone number available
        if (! $phone) {
            return null;
        }

        // Format phone number
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phone) === 9 && in_array(substr($phone, 0, 1), ['6', '7'])) {
            $phone = '+212'.$phone;
        } elseif (strlen($phone) === 10 && in_array(substr($phone, 0, 2), ['06', '07'])) {
            $phone = '+212'.substr($phone, 1);
        } elseif (strlen($phone) === 12 && substr($phone, 0, 3) === '212') {
            $phone = '+'.$phone;
        }

        $message = <<<'MSG'
        السلام عليكم ورحمة الله وبركاته 
        معكم إدارة جمعية بن ابي زيد القرواني  (دار القرآن الكريم)
        نذكركم بأداء الواجب الشهري 
        ولكم جزيل الشكر
        MSG;

        return $phone ? route('memorizer-whatsapp', [
            'number' => $phone,
            'message' => $message,
            'memorizer_id' => $record->id,
        ]) : null;
    }
}
