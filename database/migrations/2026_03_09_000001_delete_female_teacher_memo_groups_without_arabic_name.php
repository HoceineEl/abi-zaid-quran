<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $arabicDays = ['الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت', 'الأحد'];

        $hasNoArabicDay = function ($row) use ($arabicDays) {
            foreach ($arabicDays as $day) {
                if (str_contains($row->name, $day)) {
                    return false;
                }
            }
            return true;
        };

        // Delete memo groups with no teacher assigned (orphaned groups), if name contains no Arabic day.
        DB::table('memo_groups')
            ->leftJoin('users', 'memo_groups.teacher_id', '=', 'users.id')
            ->whereNull('users.id')
            ->get(['memo_groups.id', 'memo_groups.name'])
            ->filter($hasNoArabicDay)
            ->each(fn($row) => DB::table('memo_groups')->where('id', $row->id)->delete());

        // Delete memo groups where the teacher is female AND the name contains no Arabic day.
        // Explicitly skip male teachers to avoid accidental deletion.
        DB::table('memo_groups')
            ->join('users', 'memo_groups.teacher_id', '=', 'users.id')
            ->where('users.sex', 'female')
            ->get(['memo_groups.id', 'memo_groups.name'])
            ->filter($hasNoArabicDay)
            ->each(fn($row) => DB::table('memo_groups')->where('id', $row->id)->delete());
    }

    public function down(): void
    {
        // Irreversible — deleted records cannot be restored.
    }
};
