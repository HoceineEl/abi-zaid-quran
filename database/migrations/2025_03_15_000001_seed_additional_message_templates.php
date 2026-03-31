<?php

use App\Models\GroupMessageTemplate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite' && Schema::hasColumn('group_message_templates', 'group_id')) {
            return;
        }

        // Define the message templates to be seeded
        $templates = [
            [
                'name' => 'تذكير بالواجب - مع تشجيع',
                'content' => <<<MSG
السلام عليكم ورحمة الله وبركاته
*أخي الطالب {student_name}*،
نذكرك بالواجب المقرر اليوم، لعل المانع خير.
نحن نقدر جهودك ومثابرتك، واصل التميز! 🌟
MSG
            ],
            [
                'name' => 'تذكير بالواجب - مع نصيحة',
                'content' => <<<MSG
السلام عليكم ورحمة الله وبركاته
*أخي الطالب {student_name}*،
لم نتلق واجبك اليوم، لعل المانع خير.
تذكر أن الانتظام في المراجعة هو سر النجاح والتفوق. 📚
MSG
            ],
            [
                'name' => 'تذكير بالواجب - مع دعاء',
                'content' => <<<MSG
السلام عليكم ورحمة الله وبركاته
*أخي الطالب {student_name}*،
نذكرك بالواجب المقرر اليوم، لعل المانع خير.
بارك الله في وقتك وجهدك وزادك علماً ونفعاً. 🤲
MSG
            ],
            [
                'name' => 'تذكير بالحضور - مجموعة حضورية',
                'content' => <<<MSG
السلام عليكم ورحمة الله وبركاته
*أخي الطالب {student_name}*،
نود تذكيرك بموعد الحصة الحضورية غداً في تمام الساعة 4:00 مساءً.
نتطلع لحضورك ومشاركتك الفعالة. 🕌
MSG
            ],
            [
                'name' => 'تهنئة بإتمام الحفظ',
                'content' => <<<MSG
السلام عليكم ورحمة الله وبركاته
*أخي الطالب {student_name}*،
نهنئك بإتمام حفظ الجزء المقرر! 🎉
بارك الله في جهودك وجعل القرآن شفيعاً لك يوم القيامة.
واصل التميز والإبداع! 💫
MSG
            ],
            [
                'name' => 'تشجيع بعد فترة انقطاع',
                'content' => <<<MSG
السلام عليكم ورحمة الله وبركاته
*أخي الطالب {student_name}*،
سررنا بعودتك للمشاركة في المجموعة {group_name} بعد فترة الانقطاع.
آخر حضور لك كان بتاريخ: {last_presence}
تذكر أن الاستمرارية هي مفتاح النجاح في حفظ كتاب الله.
نحن هنا لدعمك ومساعدتك! 🌟
MSG
            ],
            [
                'name' => 'تذكير بالمراجعة',
                'content' => <<<MSG
السلام عليكم ورحمة الله وبركاته
*أخي الطالب {student_name}*،
نذكرك بواجب المراجعة اليوم {curr_date}.
المراجعة المنتظمة هي أساس الحفظ المتين.
_بارك الله فيك وزادك حرصا_ 🌟
MSG
            ],
            [
                'name' => 'تذكير بالتثبيت',
                'content' => <<<MSG
السلام عليكم ورحمة الله وبركاته
*أخي الطالب {student_name}*،
لا تنس واجب التثبيت اليوم.
التثبيت يساعد على ترسيخ الحفظ وتقويته.
_بارك الله فيك وزادك حرصا_ 🌟
MSG
            ],
            [
                'name' => 'تذكير بالاعتصام',
                'content' => <<<MSG
السلام عليكم ورحمة الله وبركاته
*أخي الطالب {student_name}*،
نذكرك بواجب الاعتصام اليوم.
الاعتصام بكتاب الله هو النجاة والفلاح.
_بارك الله فيك وزادك حرصا_ 🌟
MSG
            ],
            [
                'name' => 'تذكير بالسرد',
                'content' => <<<MSG
السلام عليكم ورحمة الله وبركاته
*أخي الطالب {student_name}*،
نذكرك بواجب السرد اليوم ✨
المرجو المبادرة قبل غلق المجموعة
السرد المنتظم يساعد على تقوية الحفظ وتثبيته.
_زادك الله حرصا_ 🌙
MSG
            ],
        ];
        foreach ($templates as $template) {
            GroupMessageTemplate::insert([
                'name' => $template['name'],
                'content' => $template['content'],
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
        if (DB::getDriverName() === 'sqlite' && Schema::hasColumn('group_message_templates', 'group_id')) {
            return;
        }

        // Define the template names to be removed
        $templateNames = [
            'تذكير بالواجب - مع تشجيع',
            'تذكير بالواجب - مع نصيحة',
            'تذكير بالواجب - مع دعاء',
            'تذكير بالحضور - مجموعة حضورية',
            'تهنئة بإتمام الحفظ',
            'تشجيع بعد فترة انقطاع',
            'تذكير بالمراجعة',
            'تذكير بالتثبيت',
            'تذكير بالاعتصام',
            'تذكير بالسرد',
        ];

        // Get the IDs of the templates to be removed
        $templateIds = DB::table('group_message_templates')
            ->whereIn('name', $templateNames)
            ->pluck('id');

        // Remove the associations from the pivot table
        DB::table('group_message_template_pivot')
            ->whereIn('group_message_template_id', $templateIds)
            ->delete();

        // Remove the templates
        DB::table('group_message_templates')
            ->whereIn('name', $templateNames)
            ->delete();
    }
};
