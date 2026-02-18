<?php

namespace App\Filament\Association\Resources;

use App\Filament\Association\Resources\TeacherResource\Pages;
use App\Filament\Association\Resources\TeacherResource\RelationManagers\ReminderLogsRelationManager;
use App\Filament\Association\Resources\TeacherResource\RelationManagers\AttendanceLogsRelationManager;
use App\Models\User;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Hidden;
use STS\FilamentImpersonate\Tables\Actions\Impersonate;

class TeacherResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationLabel = 'الأساتذة';

    protected static ?string $modelLabel = 'أستاذ(ة)';

    protected static ?string $pluralModelLabel = 'الأساتذة';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('role', 'teacher');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('الاسم')
                    ->required()
                    ->maxLength(255),

                TextInput::make('email')
                    ->label('البريد الإلكتروني')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true),

                TextInput::make('password')
                    ->label('كلمة المرور')
                    ->password()
                    ->revealable()
                    ->required(fn($record) => ! $record)
                    ->dehydrated(fn($state) => filled($state)),

                TextInput::make('phone')
                    ->label('رقم الهاتف')
                    ->maxLength(255),

                ToggleButtons::make('sex')
                    ->label('الجنس')
                    ->inline()
                    ->options([
                        'male' => 'ذكر',
                        'female' => 'أنثى',
                    ])
                    ->default('female')
                    ->required(),

                Hidden::make('role')
                    ->default('teacher'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable(),

                TextColumn::make('phone')
                    ->label('رقم الهاتف')
                    ->searchable()
                    ->extraCellAttributes(['dir' => 'ltr'])
                    ->alignRight(),

                SelectColumn::make('sex')
                    ->label('الجنس')
                    ->options([
                        'male' => 'ذكر',
                        'female' => 'أنثى',
                    ])
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('تاريخ التحديث')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
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
            ->actions([
                Impersonate::make()
                    ->redirectTo('/teacher'),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            AttendanceLogsRelationManager::class,
            ReminderLogsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTeachers::route('/'),
            'create' => Pages\CreateTeacher::route('/create'),
            'view' => Pages\ViewTeacher::route('/{record}'),
            'edit' => Pages\EditTeacher::route('/{record}/edit'),
        ];
    }
}
