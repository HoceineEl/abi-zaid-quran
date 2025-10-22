<?php

namespace App\Filament\Resources;

use App\Enums\DisconnectionStatus;
use App\Enums\MessageResponseStatus;
use App\Filament\Resources\StudentDisconnectionResource\Pages;
use Illuminate\Support\Collection;
use App\Models\StudentDisconnection;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\ToggleColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class StudentDisconnectionResource extends Resource
{
    protected static ?string $model = StudentDisconnection::class;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationLabel = 'الطلاب المنقطعون';

    protected static ?string $modelLabel = 'طالب منقطع';

    protected static ?string $pluralModelLabel = 'الطلاب المنقطعون';

    protected static ?int $navigationSort = 5;


    public static function canAccess(): bool
    {
        return auth()->user()->isAdministrator() || auth()->user()->email === 'mehdi@mehdi.com' || auth()->user()->phone === '0701255179' || auth()->user()->email === 'youssef@abi-zaid.com';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('student_id')
                    ->label('الطالب')
                    ->relationship('student', 'name')
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                        if ($state) {
                            $student = \App\Models\Student::find($state);
                            if ($student && $student->group) {
                                $set('group_id', $student->group_id);
                                $set('disconnection_date', now()->format('Y-m-d'));
                            }
                        }
                    }),

                Forms\Components\Select::make('group_id')
                    ->label('المجموعة')
                    ->relationship(
                        name: 'group',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) => $query->withoutGlobalScope('userGroups')
                    )
                    ->searchable()
                    ->required()
                    ->disabled(),

                Forms\Components\DatePicker::make('disconnection_date')
                    ->label('تاريخ الانقطاع')
                    ->required()
                    ->default(now()),

                Forms\Components\DatePicker::make('contact_date')
                    ->label('تاريخ التواصل')
                    ->nullable(),

                Forms\Components\ToggleButtons::make('message_response')
                    ->label('تفاعل مع الرسالة')
                    ->options(MessageResponseStatus::class)
                    ->default(MessageResponseStatus::NotContacted->value)
                    ->nullable(),

                Forms\Components\Textarea::make('notes')
                    ->label('ملاحظات')
                    ->nullable()
                    ->rows(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('disconnection_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('student.name')
                    ->label('اسم الطالب')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('group.name')
                    ->label('المجموعة')
                    ->searchable()
                    ->sortable(),


                Tables\Columns\TextColumn::make('student.last_present_date')
                    ->label('آخر حضور')
                    ->date('Y-m-d')
                    ->sortable()
                    ->placeholder('لا يوجد'),

                Tables\Columns\TextColumn::make('consecutive_absent_days')
                    ->label('أيام الغياب المتتالية')
                    ->state(function (StudentDisconnection $record): string {
                        $consecutiveDays = $record->student->getCurrentConsecutiveAbsentDays();
                        return $consecutiveDays . ' يوم';
                    })
                    ->badge()
                    ->color(fn (StudentDisconnection $record): string =>
                        $record->student->getCurrentConsecutiveAbsentDays() >= 5 ? 'danger' :
                        ($record->student->getCurrentConsecutiveAbsentDays() >= 3 ? 'warning' : 'gray')
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('disconnection_duration')
                    ->label('مدة الانقطاع')
                    ->state(function (StudentDisconnection $record): string {
                        $daysSinceLastPresent = $record->student->getDaysSinceLastPresentAttribute();
                        if ($daysSinceLastPresent === null) {
                            return 'غير محدد';
                        }
                        return $daysSinceLastPresent . ' يوم';
                    })
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('contact_date')
                    ->label('تاريخ التواصل')
                    ->date('Y-m-d')
                    ->sortable()
                    ->placeholder('لم يتم التواصل'),

                ToggleColumn::make('has_been_contacted')
                    ->label('تم التواصل')
                    ->state(function (StudentDisconnection $record): bool {
                        return $record->contact_date !== null;
                    })
                    ->updateStateUsing(function (StudentDisconnection $record, $state) {
                        if ($state) {
                            $record->update([
                                'contact_date' => now()->format('Y-m-d'),
                                'message_response' => MessageResponseStatus::No,
                            ]);
                        } else {
                            $record->update([
                                'contact_date' => null,
                                'message_response' => MessageResponseStatus::NotContacted,
                            ]);
                        }
                    }),
                SelectColumn::make('message_response')
                    ->label('تفاعل مع الرسالة')
                    ->options([
                        MessageResponseStatus::Yes->value => MessageResponseStatus::Yes->getLabel(),
                        MessageResponseStatus::No->value => MessageResponseStatus::No->getLabel(),
                        MessageResponseStatus::NotContacted->value => MessageResponseStatus::NotContacted->getLabel(),
                    ])
                    ->afterStateUpdated(function (StudentDisconnection $record, $state) {
                        if ($state === MessageResponseStatus::Yes->value || $state === MessageResponseStatus::No->value) {
                            $record->update([
                                'contact_date' => now()->format('Y-m-d'),
                                'message_response' => $state,
                            ]);
                        } elseif ($state === MessageResponseStatus::NotContacted->value) {
                            $record->update([
                                'contact_date' => null,
                                'message_response' => $state,
                            ]);
                        }
                    })
                    ->selectablePlaceholder(false)
                    ->rules(['required']),

                Tables\Columns\TextColumn::make('status')
                    ->label('الحالة')
                    ->badge(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('group_id')
                    ->label('المجموعة')
                    ->relationship('group', 'name', modifyQueryUsing: fn (Builder $query) => $query->withoutGlobalScope('userGroups')),

                Tables\Filters\SelectFilter::make('message_response')
                    ->label('تفاعل مع الرسالة')
                    ->options(MessageResponseStatus::class),

                Tables\Filters\Filter::make('last_present_date')
                    ->label('آخر حضور')
                    ->form([
                        Forms\Components\DatePicker::make('last_present_from')
                            ->label('من تاريخ')
                            ->displayFormat('m/d/Y')
                            ->default(now()->subDays(14)->format('Y-m-d')),
                        Forms\Components\DatePicker::make('last_present_to')
                            ->label('إلى تاريخ')
                            ->displayFormat('m/d/Y')
                            ->default(now()->format('Y-m-d')),
                    ])
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['last_present_from']) {
                            $indicators[] = 'من: ' . \Carbon\Carbon::parse($data['last_present_from'])->format('m/d/Y');
                        }
                        if ($data['last_present_to']) {
                            $indicators[] = 'إلى: ' . \Carbon\Carbon::parse($data['last_present_to'])->format('m/d/Y');
                        }
                        return $indicators;
                    })
                    ->default([
                        'last_present_from' => now()->subDays(14)->format('Y-m-d'),
                        'last_present_to' => now()->format('Y-m-d'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        // Apply default 14-day filter on last present date
                        $fromDate = $data['last_present_from'] ?? now()->subDays(14)->format('Y-m-d');
                        $toDate = $data['last_present_to'] ?? now()->format('Y-m-d');

                        return $query->whereHas('student', function ($studentQuery) use ($fromDate, $toDate) {
                            $studentQuery->whereHas('progresses', function ($progressQuery) use ($fromDate, $toDate) {
                                $progressQuery->where('status', 'memorized')
                                    ->whereBetween('date', [$fromDate, $toDate])
                                    ->whereRaw('date = (SELECT MAX(p2.date) FROM progress p2 WHERE p2.student_id = progress.student_id AND p2.status = "memorized")');
                            });
                        });
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                // Tables\Actions\Action::make('mark_contacted')
                // ->label('تم التواصل')
                // ->icon('heroicon-o-phone')
                // ->color('info')
                // ->form([
                //     Forms\Components\DatePicker::make('contact_date')
                //         ->label('تاريخ التواصل')
                //         ->required()
                //         ->default(now()),
                //     Forms\Components\Select::make('message_response')
                //         ->label('تفاعل مع الرسالة')
                //         ->options(MessageResponseStatus::class)
                //         ->required(),
                //     Forms\Components\Textarea::make('notes')
                //         ->label('ملاحظات')
                //         ->rows(2),
                // ])
                // ->action(function (StudentDisconnection $record, array $data) {
                //     $record->update([
                //         'contact_date' => $data['contact_date'],
                //         'message_response' => $data['message_response'],
                //         'notes' => $data['notes'] ?? $record->notes,
                //     ]);
                // })
                // ->visible(fn(StudentDisconnection $record) => !$record->contact_date)
                // ->requiresConfirmation()
                // ->modalHeading('تحديث حالة التواصل'),

                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('mark_contacted_bulk')
                        ->label('تم التواصل')
                        ->icon('heroicon-o-phone')
                        ->color('info')
                        ->form([
                            Forms\Components\DatePicker::make('contact_date')
                                ->label('تاريخ التواصل')
                                ->required()
                                ->default(now()),
                            Forms\Components\Select::make('message_response')
                                ->label('تفاعل مع الرسالة')
                                ->options(MessageResponseStatus::class)
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $records->each(function ($record) use ($data) {
                                $record->update([
                                    'contact_date' => $data['contact_date'],
                                    'message_response' => $data['message_response'],
                                ]);
                            });
                        })
                        ->requiresConfirmation()
                        ->modalHeading('تحديث حالة التواصل للطلاب المحددين'),
                    Tables\Actions\BulkAction::make('update_notes_bulk')
                        ->label('تحديث الملاحظات')
                        ->icon('heroicon-o-pencil-square')
                        ->color('warning')
                        ->form([
                            Forms\Components\Textarea::make('notes')
                                ->label('ملاحظات')
                                ->rows(3)
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $records->each(function ($record) use ($data) {
                                $record->update([
                                    'notes' => $data['notes'],
                                ]);
                            });
                        })
                        ->requiresConfirmation()
                        ->modalHeading('تحديث الملاحظات للطلاب المحددين'),
                    Tables\Actions\BulkAction::make('reset_contact_status')
                        ->label('إعادة تعيين حالة التواصل')
                        ->icon('heroicon-o-arrow-path')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->modalHeading('إعادة تعيين حالة التواصل')
                        ->modalDescription('سيتم حذف تاريخ التواصل ورد الرسالة للطلاب المحددين.')
                        ->action(function (Collection $records) {
                            $records->each(function ($record) {
                                $record->update([
                                    'contact_date' => null,
                                    'message_response' => null,
                                ]);
                            });
                        }),
                    Tables\Actions\BulkAction::make('mark_contacted_for_uncontacted')
                        ->label('تم التواصل (للغير متواصلين فقط)')
                        ->icon('heroicon-o-phone')
                        ->color('info')
                        ->form([
                            Forms\Components\DatePicker::make('contact_date')
                                ->label('تاريخ التواصل')
                                ->required()
                                ->default(now()),
                            Forms\Components\Select::make('message_response')
                                ->label('تفاعل مع الرسالة')
                                ->options(MessageResponseStatus::class)
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $records->filter(function ($record) {
                                return !$record->contact_date;
                            })->each(function ($record) use ($data) {
                                $record->update([
                                    'contact_date' => $data['contact_date'],
                                    'message_response' => $data['message_response'],
                                ]);
                            });
                        })
                        ->requiresConfirmation()
                        ->modalHeading('تحديث حالة التواصل للطلاب غير المتواصلين')
                        ->modalDescription('سيتم تحديث حالة التواصل للطلاب الذين لم يتم التواصل معهم بعد.'),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
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
            'index' => Pages\ListStudentDisconnections::route('/'),
            'create' => Pages\CreateStudentDisconnection::route('/create'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'student',
                'group' => fn ($query) => $query->withoutGlobalScope('userGroups')
            ]);
    }
}
