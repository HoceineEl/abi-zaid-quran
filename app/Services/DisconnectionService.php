<?php

namespace App\Services;

use App\Models\Student;
use App\Models\Group;
use App\Models\StudentDisconnection;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class DisconnectionService
{
  public function getDisconnectedStudentsFromWorkingGroups(array $excludedGroups = []): Collection
  {
    return Student::with(['group', 'progresses'])
      ->whereHas('group', function ($query) use ($excludedGroups) {
        $query->active();
        if (!empty($excludedGroups)) {
          $query->whereNotIn('id', $excludedGroups);
        }
      })
      ->get()
      ->filter(function ($student) {
        return $student->hasConsecutiveAbsentDaysInWorkingGroup(2) &&
          !$this->studentAlreadyDisconnected($student);
      })
      ->sortByDesc(function ($student) {
        return $student->getLastPresentDate();
      });
  }

  public function addDisconnectedStudents(array $excludedGroups = []): int
  {
    $students = $this->getDisconnectedStudentsFromWorkingGroups($excludedGroups);
    $addedCount = 0;

    foreach ($students as $student) {
      $disconnectionDate = $student->getDisconnectionDateBasedOnGroupActivity();

      if ($disconnectionDate) {
        StudentDisconnection::create([
          'student_id' => $student->id,
          'group_id' => $student->group_id,
          'disconnection_date' => $disconnectionDate,
        ]);
        $addedCount++;
      }
    }

    return $addedCount;
  }

  public function checkReturnedStudents(): int
  {
    $disconnections = StudentDisconnection::with('student')
      ->where('has_returned', false)
      ->get();

    $returnedCount = 0;

    foreach ($disconnections as $disconnection) {
      if ($this->hasStudentReturned($disconnection)) {
        $disconnection->update(['has_returned' => true]);
        $returnedCount++;
      }
    }

    return $returnedCount;
  }

  public function getActiveGroups(): Collection
  {
    return Group::active()->get();
  }

  public function getWorkingGroupsOnDate(string $date): Collection
  {
    return Group::working($date)->get();
  }

  public function getWorkingGroupsInDateRange(string $startDate, string $endDate): Collection
  {
    return Group::workingInDateRange($startDate, $endDate)->get();
  }

  private function studentAlreadyDisconnected(Student $student): bool
  {
    // Only check if student has an active disconnection (hasn't returned)
    return StudentDisconnection::where('student_id', $student->id)
      ->where('has_returned', false)
      ->exists();
  }

  private function hasStudentReturned(StudentDisconnection $disconnection): bool
  {
    return $disconnection->student->progresses()
      ->where('status', 'memorized')
      ->where('date', '>', $disconnection->disconnection_date)
      ->exists();
  }

  public function getDisconnectionStats(): array
  {
    $total = StudentDisconnection::count();
    $notReturned = StudentDisconnection::where('has_returned', false)->count();
    $returned = StudentDisconnection::where('has_returned', true)->count();

    return [
      'total' => $total,
      'not_returned' => $notReturned,
      'returned' => $returned,
    ];
  }
}
