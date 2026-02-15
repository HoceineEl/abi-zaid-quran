<?php

namespace App\Filament\Resources\GroupResource\Pages;

use App\Filament\Actions\CheckWhatsAppStatusAction;
use App\Filament\Resources\GroupResource;
use App\Models\Group;
use App\Models\Student;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\ActionSize;
use Illuminate\Database\Eloquent\Builder;
use Filament\Notifications\Notification;

class ListGroups extends ListRecords
{
    protected static string $resource = GroupResource::class;

    protected $attendanceGroups = null;

    /**
     * Get and cache groups with attendance data for badge calculations
     */
    protected function getGroupsWithAttendance()
    {
        if ($this->attendanceGroups === null) {
            $this->attendanceGroups = Group::with(['students' => function ($query) {
                $query->withCount(['progresses as attendance_count' => function ($q) {
                    $q->where('date', Carbon::now()->format('Y-m-d'))
                        ->where('status', 'memorized');
                }]);
            }])->get();
        }

        return $this->attendanceGroups;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->visible(auth()->user()->isAdministrator()),
            CheckWhatsAppStatusAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('جميع المجموعات')
                ->badge(Group::count()),

            'perfect' => Tab::make('مجموعات مثالية (100%)')
                ->badge(function () {
                    return $this->getGroupsWithAttendance()->filter(function ($group) {
                        return $this->calculateGroupAttendance($group) === 100;
                    })->count();
                })
                ->badgeColor('success')
                ->modifyQueryUsing(function (Builder $query) {
                    // Get the current IDs in the query
                    $groupIds = $query->pluck('id')->toArray();

                    if (!empty($groupIds)) {
                        // Get groups with students and attendance data
                        $groups = Group::with(['students' => function ($query) {
                            $query->withCount(['progresses as attendance_count' => function ($q) {
                                $q->where('date', Carbon::now()->format('Y-m-d'))
                                    ->where('status', 'memorized');
                            }]);
                        }])->whereIn('id', $groupIds)->get();

                        // Filter for groups with 100% attendance
                        $filteredIds = $groups->filter(function ($group) {
                            $attendance = $this->calculateGroupAttendance($group);
                            return $attendance === 100;
                        })->pluck('id')->toArray();

                        return $query->whereIn('id', $filteredIds);
                    }

                    return $query;
                }),

            'high' => Tab::make('مجموعات متميزة (90-99%)')
                ->badge(function () {
                    return $this->getGroupsWithAttendance()->filter(function ($group) {
                        $attendance = $this->calculateGroupAttendance($group);
                        return $attendance >= 90 && $attendance < 100;
                    })->count();
                })
                ->badgeColor('success')
                ->modifyQueryUsing(function (Builder $query) {
                    // Get the current IDs in the query
                    $groupIds = $query->pluck('id')->toArray();

                    if (!empty($groupIds)) {
                        // Get groups with students and attendance data
                        $groups = Group::with(['students' => function ($query) {
                            $query->withCount(['progresses as attendance_count' => function ($q) {
                                $q->where('date', Carbon::now()->format('Y-m-d'))
                                    ->where('status', 'memorized');
                            }]);
                        }])->whereIn('id', $groupIds)->get();

                        // Filter for groups with 90-99% attendance
                        $filteredIds = $groups->filter(function ($group) {
                            $attendance = $this->calculateGroupAttendance($group);
                            return $attendance >= 90 && $attendance < 100;
                        })->pluck('id')->toArray();

                        return $query->whereIn('id', $filteredIds);
                    }

                    return $query;
                }),

            'medium' => Tab::make('مجموعات متوسطة (70-89%)')
                ->badge(function () {
                    return $this->getGroupsWithAttendance()->filter(function ($group) {
                        $attendance = $this->calculateGroupAttendance($group);
                        return $attendance >= 70 && $attendance < 90;
                    })->count();
                })
                ->badgeColor('warning')
                ->modifyQueryUsing(function (Builder $query) {
                    // Get the current IDs in the query
                    $groupIds = $query->pluck('id')->toArray();

                    if (!empty($groupIds)) {
                        // Get groups with students and attendance data
                        $groups = Group::with(['students' => function ($query) {
                            $query->withCount(['progresses as attendance_count' => function ($q) {
                                $q->where('date', Carbon::now()->format('Y-m-d'))
                                    ->where('status', 'memorized');
                            }]);
                        }])->whereIn('id', $groupIds)->get();

                        // Filter for groups with 70-89% attendance
                        $filteredIds = $groups->filter(function ($group) {
                            $attendance = $this->calculateGroupAttendance($group);
                            return $attendance >= 70 && $attendance < 90;
                        })->pluck('id')->toArray();

                        return $query->whereIn('id', $filteredIds);
                    }

                    return $query;
                }),

            'low' => Tab::make('مجموعات تحتاج تحسين (<70%)')
                ->badge(function () {
                    return $this->getGroupsWithAttendance()->filter(function ($group) {
                        return $this->calculateGroupAttendance($group) < 70;
                    })->count();
                })
                ->badgeColor('danger')
                ->modifyQueryUsing(function (Builder $query) {
                    // Get the current IDs in the query
                    $groupIds = $query->pluck('id')->toArray();

                    if (!empty($groupIds)) {
                        // Get groups with students and attendance data
                        $groups = Group::with(['students' => function ($query) {
                            $query->withCount(['progresses as attendance_count' => function ($q) {
                                $q->where('date', Carbon::now()->format('Y-m-d'))
                                    ->where('status', 'memorized');
                            }]);
                        }])->whereIn('id', $groupIds)->get();

                        // Filter for groups with less than 70% attendance
                        $filteredIds = $groups->filter(function ($group) {
                            $attendance = $this->calculateGroupAttendance($group);
                            return $attendance < 70;
                        })->pluck('id')->toArray();

                        return $query->whereIn('id', $filteredIds);
                    }

                    return $query;
                }),
        ];
    }

    /**
     * Calculate attendance percentage for a group
     */
    protected function calculateGroupAttendance(Group $group): int
    {
        // If the students are already eager loaded with the attendance_count
        if ($group->relationLoaded('students')) {
            $students = $group->students;
            $totalStudents = $students->count();

            if ($totalStudents === 0) {
                return 0;
            }

            $presentStudents = $students->where('attendance_count', '>', 0)->count();
            return round(($presentStudents / $totalStudents) * 100);
        }

        // Otherwise, perform the calculation by querying
        $students = $group->students()
            ->withCount(['progresses as attendance_count' => function ($query) {
                $query->where('date', Carbon::now()->format('Y-m-d'))
                    ->where('status', 'memorized');
            }])
            ->get();

        $totalStudents = $students->count();

        if ($totalStudents === 0) {
            return 0;
        }

        $presentStudents = $students->where('attendance_count', '>', 0)->count();
        return round(($presentStudents / $totalStudents) * 100);
    }


    protected function getTableRecordsPerPageSelectOptions(): array
    {
        return [10, 25, 50, 100];
    }
}
