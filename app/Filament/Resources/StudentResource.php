<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\StudentResource\Pages\ListStudents;
use App\Filament\Resources\StudentResource\Pages\CreateStudent;
use App\Filament\Resources\StudentResource\Pages\EditStudent;
use App\Filament\Resources\StudentResource\Pages;
use App\Models\Group;
use App\Models\Student;
use App\Enums\MessageResponseStatus;
use App\Tables\Columns\StudentProgress;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StudentResource extends Resource
{
    protected static ?string $model = Student::class;

    protected static ?string $navigationLabel = 'الطلاب';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $modelLabel = 'طالب';

    protected static ?string $pluralModelLabel = 'طلاب';

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $shouldRegisterNavigation = true;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('الاسم')
                    ->required(),
                TextInput::make('phone')
                    ->label('رقم الهاتف')
                    ->default('06')
                    ->required(),
                Select::make('group_id')
                    ->options(Group::all()->pluck('fullName', 'id')->toArray())
                    ->label('المجموعة'),
                Select::make('sex')
                    ->label('الجنس')
                    ->options([
                        'male' => 'ذكر',
                        'female' => 'أنثى',
                    ])
                    ->default('male'),
                TextInput::make('city')
                    ->label('المدينة')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('الاسم'),
                TextColumn::make('group.type')->label('نوع الحفظ')
                    ->formatStateUsing(function ($state) {
                        return match ($state) {
                            'two_lines' => 'سطران',
                            'half_page' => 'نصف صفحة',
                            default => $state,
                        };
                    }),
                TextColumn::make('phone')
                    ->extraCellAttributes(['dir' => 'ltr'])
                    ->alignRight()
                    ->label('رقم الهاتف'),
                TextColumn::make('group.name')->label('المجموعة')
                    ->badge(),
                // StudentProgress::make('progress')->label('التقدم'),
                TextColumn::make('sex')->label('الجنس')
                    ->formatStateUsing(function ($state) {
                        return match ($state) {
                            'male' => 'ذكر',
                            'female' => 'أنثى',
                        };
                    }),
                TextColumn::make('city')->label('المدينة'),
                TextColumn::make('created_at')
                    ->label('انضم منذ')
                    ->since()
                    ->toggleable()
                    ->toggledHiddenByDefault(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('add_disconnection')
                    ->label('إضافة انقطاع')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('danger')
                    ->schema([
                        DatePicker::make('disconnection_date')
                            ->label('تاريخ الانقطاع')
                            ->required()
                            ->default(now()),
                        Textarea::make('notes')
                            ->label('ملاحظات')
                            ->rows(3),
                    ])
                    ->action(function (Student $record, array $data) {
                        $record->disconnections()->create([
                            'group_id' => $record->group_id,
                            'disconnection_date' => $data['disconnection_date'],
                            'notes' => $data['notes'] ?? null,
                        ]);
                    })
                    ->requiresConfirmation()
                    ->modalHeading('إضافة انقطاع للطالب')
                    ->modalDescription('سيتم إضافة سجل انقطاع جديد للطالب.')
                    ->modalSubmitActionLabel('إضافة الانقطاع'),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function canAccess(): bool
    {
        return auth()->user()->isAdministrator();
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStudents::route('/'),
            'create' => CreateStudent::route('/create'),
            'edit' => EditStudent::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'phone'];
    }
}
