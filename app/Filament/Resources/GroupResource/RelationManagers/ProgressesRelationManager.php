<?php

namespace App\Filament\Resources\GroupResource\RelationManagers;

use App\Classes\Core;
use App\Helpers\ProgressFormHelper;
use App\Models\Page;
use App\Models\Progress;
use App\Models\Student;
use App\Services\WhatsAppService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Colors\Color;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ProgressesRelationManager extends RelationManager
{
    protected static string $relationship = 'progresses';

    protected static ?string $title = 'التقدم';

    protected static ?string $navigationLabel = 'التقدم';

    protected static ?string $modelLabel = 'تقدم';

    protected static ?string $pluralModelLabel = 'تقدمات';

    public function form(Form $form): Form
    {
        return $form
            ->schema(ProgressFormHelper::getProgressFormSchema(group: $this->ownerRecord));
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('date')
            ->columns([
                Tables\Columns\TextColumn::make('date')->label('التاريخ'),
                TextColumn::make('student.name')
                    ->label('الطالب'),
                TextColumn::make('status')
                    ->formatStateUsing(function ($state) {
                        return match ($state) {
                            'memorized' => 'تم',
                            'absent' => 'غائب',
                        };
                    })
                    ->badge()
                    ->placeholder('تم التذكير')
                    ->label('الحالة'),
                TextColumn::make('createdBy.name')->label('سجل بواسطة'),
            ])
            ->paginated(false)
            ->headerActions([
                Tables\Actions\CreateAction::make(),
                Action::make('make_others_as_absent')
                    ->visible(fn () => $this->ownerRecord->managers->contains(auth()->user()))
                    ->label('تسجيل البقية كغائبين اليوم')
                    ->color('danger')
                    ->action(function () {
                        $selectedDate = $this->tableFilters['date']['value'] ?? now()->format('Y-m-d');
                        $this->ownerRecord->students->filter(function ($student) use ($selectedDate) {
                            return $student->progresses->where('date', $selectedDate)->count() == 0;
                        })->each(function ($student) use ($selectedDate) {
                            $student->progresses()->create([
                                'date' => $selectedDate,
                                'status' => 'absent',
                                'comment' => 'message_sent',
                                'page_id' => null,
                                'lines_from' => null,
                                'lines_to' => null,
                            ]);
                            Notification::make()
                                ->title('تم تسجيل الطالب ' . $student->name . ' كغائب اليوم')
                                ->color('success')
                                ->icon('heroicon-o-check-circle')
                                ->send();
                            if ($selectedDate == now()->format('Y-m-d')) {
                                Core::sendMessageToStudent($student);
                            }
                        });
                    }),
                Action::make('group')
                    ->label('تسجيل تقدم جماعي')
                    ->visible(fn () => $this->ownerRecord->managers->contains(auth()->user()))
                    ->color(Color::Teal)
                    ->form(function (Get $get) {
                        $students = $this->ownerRecord->students->filter(function ($student) {
                            return $student->progresses->where('date', now()->format('Y-m-d'))->count() == 0;
                        })->pluck('name', 'id');

                        return [
                            Grid::make()
                                ->schema([
                                    Select::make('students')
                                        ->label('الطلاب')
                                        ->options(function (Get $get) {
                                            return  $this->ownerRecord->students->filter(function ($student) use ($get) {
                                                return $student->progresses->where('date', $get('date'))->count() == 0;
                                            })->pluck('name', 'id');
                                        })
                                        ->required()
                                        ->default(fn () => $students->keys()->toArray())
                                        ->multiple(),
                                    DatePicker::make('date')
                                        ->label('التاريخ')
                                        ->reactive()
                                        ->default(now()->format('Y-m-d'))
                                        ->required(),
                                    ToggleButtons::make('status')
                                        ->label('الحالة')
                                        ->inline()
                                        ->reactive()
                                        ->icons([
                                            'memorized' => 'heroicon-o-check-circle',
                                            'absent' => 'heroicon-o-x-circle',
                                        ])
                                        ->grouped()
                                        ->default('memorized')
                                        ->colors([
                                            'memorized' => 'primary',
                                            'absent' => 'danger',
                                        ])
                                        ->options([
                                            'memorized' => 'أتم الحفظ',
                                            'absent' => 'غائب',
                                        ])
                                        ->required(),
                                    ToggleButtons::make('comment')
                                        ->label('التعليق')
                                        ->inline()
                                        ->default('message_sent')
                                        ->colors([
                                            'message_sent' => 'success',
                                            'call_made' => 'warning',
                                        ])
                                        ->options([
                                            'message_sent' => 'تم إرسال الرسالة',
                                            'call_made' => 'تم الاتصال',
                                        ]),
                                ]),
                            MarkdownEditor::make('notes')
                                ->label('ملاحظات')
                                ->columnSpanFull()
                                ->placeholder('أدخل ملاحظاتك هنا'),
                        ];
                    })
                    ->action(function (array $data) {
                        foreach ($data['students'] as $studentId) {
                            $student = Student::find($studentId);
                            $student->progresses()->create([
                                'date' => $data['date'],
                                'status' => $data['status'],
                                'comment' => $data['comment'],
                                'page_id' => $data['page_id'] ?? null,
                                'lines_from' => $data['lines_from'] ?? null,
                                'lines_to' => $data['lines_to'] ?? null,
                                'notes' => $data['notes'],
                            ]);
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->form(fn (Progress $record) => ProgressFormHelper::getProgressFormSchema(group: $this->ownerRecord, student: $record->student))
                    ->slideOver(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->filters([
                SelectFilter::make('date')
                    ->label('التاريخ')
                    ->visible(fn () => $this->ownerRecord->managers->contains(auth()->user()))
                    ->options(function () {
                        return Progress::all()->pluck('date', 'date');
                    })
                    ->default(now()->format('Y-m-d')),
            ], FiltersLayout::AboveContent)
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
    public function isReadOnly(): bool
    {
        return  !$this->ownerRecord->managers->contains(auth()->user());
    }
}
