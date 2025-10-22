<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\GroupMessageTemplate;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create a simple and concise default reminder template
        $template = GroupMessageTemplate::create([
            'name' => 'تذكير بسيط - القالب الافتراضي',
            'content' => <<<'ARABIC'
السلام عليكم ورحمة الله وبركاته
*أخي الطالب {student_name}*،
نذكرك بالواجب المقرر اليوم، لعل المانع خير.
بارك الله في وقتك وجهدك وزادك علماً ونفعاً. 🤲
ARABIC
        ]);

        // Get all active Quran groups
        $groups = DB::table('groups')
            ->where('is_quran_group', true)
            ->whereNull('deleted_at')
            ->pluck('id');

        // Attach this template to all groups as their default
        foreach ($groups as $groupId) {
            DB::table('group_message_template_pivot')->insert([
                'group_id' => $groupId,
                'group_message_template_id' => $template->id,
                'is_default' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove the default template
        $template = GroupMessageTemplate::where('name', 'تذكير بسيط - القالب الافتراضي')->first();

        if ($template) {
            // Remove pivot entries
            DB::table('group_message_template_pivot')
                ->where('group_message_template_id', $template->id)
                ->delete();

            // Delete the template
            $template->delete();
        }
    }
};
