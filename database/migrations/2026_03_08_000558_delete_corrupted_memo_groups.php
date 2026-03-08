<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Delete MemoGroup records with corrupted names (no Arabic characters).
        // Corrupted records have Windows-1256 Arabic text misread as Latin-1,
        // resulting in names with zero Arabic Unicode characters (U+0600–U+06FF).
        DB::table('memo_groups')
            ->get(['id', 'name'])
            ->filter(fn($row) => !preg_match('/[\x{0600}-\x{06FF}]/u', $row->name))
            ->each(fn($row) => DB::table('memo_groups')->where('id', $row->id)->delete());
    }

    public function down(): void
    {
        // Irreversible — corrupted data cannot be restored.
    }
};
