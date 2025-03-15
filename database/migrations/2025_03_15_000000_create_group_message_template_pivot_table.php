<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create a pivot table for the many-to-many relationship
        Schema::create('group_message_template_pivot', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignId('group_message_template_id')->constrained('group_message_templates')->cascadeOnDelete();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            // Add a unique constraint to prevent duplicate relationships
            $table->unique(['group_id', 'group_message_template_id'], 'group_template_unique');
        });

        // Copy existing relationships to the pivot table
        $templates = DB::table('group_message_templates')->get();
        foreach ($templates as $template) {
            if ($template->group_id) {
                DB::table('group_message_template_pivot')->insert([
                    'group_id' => $template->group_id,
                    'group_message_template_id' => $template->id,
                    'is_default' => $template->is_default,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Remove the group_id column from the group_message_templates table
        // First, get the actual foreign key constraint name
        $foreignKeys = DB::select("
            SELECT CONSTRAINT_NAME
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'group_message_templates'
            AND CONSTRAINT_TYPE = 'FOREIGN KEY'
            AND CONSTRAINT_NAME LIKE '%group_id%'
        ");

        if (!empty($foreignKeys)) {
            $foreignKeyName = $foreignKeys[0]->CONSTRAINT_NAME;

            Schema::table('group_message_templates', function (Blueprint $table) use ($foreignKeyName) {
                $table->dropForeign($foreignKeyName);
                $table->dropColumn('group_id');
                $table->dropColumn('is_default'); // Move is_default to the pivot table
            });
        } else {
            // If no foreign key constraint is found, just drop the columns
            Schema::table('group_message_templates', function (Blueprint $table) {
                $table->dropColumn('group_id');
                $table->dropColumn('is_default'); // Move is_default to the pivot table
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add back the group_id and is_default columns to the group_message_templates table
        Schema::table('group_message_templates', function (Blueprint $table) {
            $table->foreignId('group_id')->nullable()->constrained('groups')->nullOnDelete();
            $table->boolean('is_default')->default(false);
        });

        // Copy relationships back from the pivot table
        $pivots = DB::table('group_message_template_pivot')->get();
        foreach ($pivots as $pivot) {
            DB::table('group_message_templates')
                ->where('id', $pivot->group_message_template_id)
                ->update([
                    'group_id' => $pivot->group_id,
                    'is_default' => $pivot->is_default,
                ]);
        }

        // Drop the pivot table
        Schema::dropIfExists('group_message_template_pivot');
    }
};
