<?php

use App\Enums\Days;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('memo_groups', function (Blueprint $table) {
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('days')->nullable();
        });

        // Parse existing group names to extract teacher and days
        $groups = DB::table('memo_groups')->get();
        foreach ($groups as $group) {
            if (str_contains($group->name, '-')) {
                [$teacherName, $daysText] = explode('-', $group->name);
                $teacherName = trim($teacherName);
                $daysText = trim($daysText);

                // Find teacher by name in users table
                $teacher = DB::table('users')
                    ->where('name', 'like', '%'.$teacherName.'%')
                    ->where('role', 'teacher')
                    ->first();

                if ($teacher) {
                    // Convert Arabic day names to enum values
                    $selectedDays = [];
                    $daysMap = array_flip(Days::toArray());

                    foreach ($daysMap as $arabicDay => $enumValue) {
                        if (str_contains($daysText, $arabicDay)) {
                            $selectedDays[] = $enumValue;
                        }
                    }

                    DB::table('memo_groups')
                        ->where('id', $group->id)
                        ->update([
                            'teacher_id' => $teacher->id,
                            'days' => $selectedDays,
                        ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('memo_groups', function (Blueprint $table) {
            $table->dropForeign(['teacher_id']);
            $table->dropColumn(['teacher_id', 'days']);
        });
    }
};
