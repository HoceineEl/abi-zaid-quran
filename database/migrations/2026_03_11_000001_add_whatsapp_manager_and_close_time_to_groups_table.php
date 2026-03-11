<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->foreignId('whatsapp_manager_id')
                ->nullable()
                ->after('whatsapp_group_jid')
                ->constrained('users')
                ->nullOnDelete();
            $table->time('auto_attendance_close_time')
                ->default(config('whatsapp.automation.default_close_time', '23:00:00'))
                ->after('whatsapp_manager_id');
        });

        $adminId = DB::table('users')
            ->where('role', 'admin')
            ->orderBy('id')
            ->value('id');

        if (! $adminId) {
            return;
        }

        $managerCoverage = DB::table('group_manager')
            ->select('manager_id', DB::raw('COUNT(*) as groups_count'))
            ->groupBy('manager_id')
            ->pluck('groups_count', 'manager_id');

        $managerRoles = DB::table('users')
            ->pluck('role', 'id');

        $groupIds = DB::table('groups')->pluck('id');

        foreach ($groupIds as $groupId) {
            $managerIds = DB::table('group_manager')
                ->where('group_id', $groupId)
                ->pluck('manager_id')
                ->all();

            $candidateIds = collect($managerIds)
                ->filter(fn (int $managerId) => $managerId !== $adminId)
                ->filter(fn (int $managerId) => ($managerRoles[$managerId] ?? null) !== 'teacher')
                // Prefer the least repeated manager across all groups.
                ->sortBy(fn (int $managerId) => [
                    (int) ($managerCoverage[$managerId] ?? PHP_INT_MAX),
                    $managerId,
                ])
                ->values();

            DB::table('groups')
                ->where('id', $groupId)
                ->update([
                    'whatsapp_manager_id' => $candidateIds->first() ?? $adminId,
                    'auto_attendance_close_time' => config('whatsapp.automation.default_close_time', '23:00:00'),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('whatsapp_manager_id');
            $table->dropColumn('auto_attendance_close_time');
        });
    }
};
