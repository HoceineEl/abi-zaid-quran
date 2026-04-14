<?php

namespace App\Filament\Association\Resources;

use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Association\Resources\GroupResource\Pages\ListGroups;
use App\Filament\Association\Resources\GroupResource\Pages\ViewGroup;
use InvalidArgumentException;
use Throwable;
use App\Enums\Days;
use App\Filament\Association\Resources\GroupResource\Pages;
use App\Filament\Association\Resources\GroupResource\RelationManagers\AttendancesRelationManager;
use App\Filament\Association\Resources\GroupResource\RelationManagers\AttendancesScoreRelationManager;
use App\Filament\Association\Resources\GroupResource\RelationManagers\MemorizersRelationManager;
use App\Filament\Association\Resources\GroupResource\RelationManagers\PaymentsRelationManager;
use App\Filament\Association\Resources\GroupResource\RelationManagers\AttendanceTeacherRelationManager;
use App\Models\MemoGroup;
use App\Exports\GroupStudentsPaymentExport;
use App\Services\AttendanceExcelExportService;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use App\Models\User;
use Maatwebsite\Excel\Facades\Excel;

class GroupResource extends Resource
{
    protected static ?string $model = MemoGroup::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'المجموعات';

    protected static ?string $modelLabel = 'مجموعة';

    protected static ?string $pluralModelLabel = 'المجموعات';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('الإسم')
                    ->required(),
                TextInput::make('price')
                    ->label('الثمن الذي تدفع هذه المجموعة ')
                    ->suffix('درهم')
                    ->default(70)
                    ->required(),
                Select::make('teacher_id')
                    ->options(fn() => User::where('role', 'teacher')->pluck('name', 'id'))
                    ->label('المدرس')
                    ->searchable()
                    ->preload(),
                ToggleButtons::make('days')
                    ->multiple()
                    ->inline()
                    ->options(Days::class)
                    ->label('الأيام')
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name')
                    ->label('الإسم'),
                TextEntry::make('teacher.name')
                    ->label('المدرس'),
                TextEntry::make('arabic_days')
                    ->label('الأيام')
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(query: function ($query, string $search) {
                        return $query->where(function ($query) use ($search) {
                            $query->where('name', 'like', '%' . $search . '%')
                                ->orWhereHas('memorizers', function ($query) use ($search) {
                                    $query->where('name', 'like', '%' . $search . '%');
                                });
                        });
                    })
                    ->badge()
                    ->label('الإسم')
                    ->sortable(),
                TextColumn::make('teacher.name')
                    ->searchable()
                    ->label('المدرس')
                    ->sortable(),
                TextColumn::make('arabic_days')
                    ->searchable(false)
                    ->label('الأيام')
                    ->sortable(false),

                TextColumn::make('memorizers_count')
                    ->searchable(false)
                    ->getStateUsing(fn($record) => $record->memorizers_count)
                    ->label('عدد الطلاب')
                    ->sortable(false),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()
                    ->slideOver()
                    ->hidden(fn() => auth()->user()->isTeacher()),
                ViewAction::make(),
                Action::make('export_students_payment')
                    ->label('تصدير Excel')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->hidden(fn() => auth()->user()->isTeacher())
                    ->schema([
                        Select::make('selected_month')
                            ->label('الشهر المختار')
                            ->options([
                                '01' => 'يناير',
                                '02' => 'فبراير',
                                '03' => 'مارس',
                                '04' => 'أبريل',
                                '05' => 'مايو',
                                '06' => 'يونيو',
                                '07' => 'يوليو',
                                '08' => 'أغسطس',
                                '09' => 'سبتمبر',
                                '10' => 'أكتوبر',
                                '11' => 'نوفمبر',
                                '12' => 'ديسمبر',
                            ])
                            ->default('09') // September
                            ->required(),
                    ])
                    ->action(function (MemoGroup $record, array $data) {
                        $fileName = 'قائمة_طلاب_' . str_replace(' ', '_', $record->name) . '_' . now()->format('Y-m-d') . '.xlsx';
                        
                        return Excel::download(
                            new GroupStudentsPaymentExport($record, $data['selected_month']),
                            $fileName
                        );
                    }),
                Action::make('export_attendance_grades')
                    ->label('تصدير حضور وتقييم Excel')
                    ->icon('heroicon-o-table-cells')
                    ->color('primary')
                    ->hidden(fn() => auth()->user()->isTeacher())
                    ->schema(static::getAttendanceExportFormSchema())
                    ->action(fn(MemoGroup $record, array $data) => static::exportAttendanceWorkbook($record, $data)),
            ])
            ->recordUrl(fn($record) => GroupResource::getUrl('view', ['record' => $record->id]))
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->hidden(fn() => auth()->user()->isTeacher()),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        if (auth()->check() && auth()->user()->isTeacher()) {
            return [
                AttendanceTeacherRelationManager::class,
                AttendancesScoreRelationManager::class,
            ];
        }

        return [
            MemorizersRelationManager::class,
            AttendancesRelationManager::class,
            AttendancesScoreRelationManager::class,
            PaymentsRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with('teacher')
            ->withCount('memorizers');

        if (auth()->user()->isTeacher()) {
            $today = strtolower(now()->format('l'));

            return $query->where(function ($q) use ($today) {
                $q->where('teacher_id', auth()->user()->id)
                    ->whereJsonContains('days', $today);
            });
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGroups::route('/'),
            'view' => ViewGroup::route('/{record}'),
        ];
    }

    public static function getAttendanceExportFormSchema(): array
    {
        return [
            DatePicker::make('date_from')
                ->label('من تاريخ')
                ->default(now()->subDays(6)->format('Y-m-d'))
                ->required(),
            DatePicker::make('date_to')
                ->label('إلى تاريخ')
                ->default(now()->format('Y-m-d'))
                ->required(),
            Select::make('sex_filter')
                ->label('تصفية الجنس')
                ->options([
                    'male' => 'الذكور فقط',
                    'female' => 'الإناث فقط',
                ])
                ->placeholder('الكل'),
            Toggle::make('include_student_numbers')
                ->label('تضمين أرقام الطلاب')
                ->default(false),
            Toggle::make('include_contact_columns')
                ->label('تضمين بيانات التواصل')
                ->default(false),
        ];
    }

    public static function exportAttendanceWorkbook(MemoGroup $group, array $data)
    {
        if (! $group->memorizers()->exists()) {
            Notification::make()
                ->title('لا يمكن التصدير')
                ->body('هذه المجموعة لا تحتوي على طلاب حالياً.')
                ->danger()
                ->send();

            return null;
        }

        try {
            return app(AttendanceExcelExportService::class)->download($group, $data);
        } catch (InvalidArgumentException $exception) {
            Notification::make()
                ->title('تعذر إنشاء الملف')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return null;
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('حدث خطأ أثناء إنشاء الملف')
                ->body('تعذر إنشاء ملف Excel حالياً. حاول مرة أخرى.')
                ->danger()
                ->send();

            return null;
        }
    }

    public static function exportAllAttendanceWorkbooks(array $data)
    {
        try {
            return app(AttendanceExcelExportService::class)->downloadAllGroups($data);
        } catch (InvalidArgumentException $exception) {
            Notification::make()
                ->title('تعذر إنشاء الملف')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return null;
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('حدث خطأ أثناء إنشاء الملف')
                ->body('تعذر إنشاء ملف Excel حالياً. حاول مرة أخرى.')
                ->danger()
                ->send();

            return null;
        }
    }
}
