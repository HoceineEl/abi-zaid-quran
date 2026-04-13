<?php

namespace Database\Seeders;

use App\Models\MemoGroup;
use App\Models\Memorizer;
use App\Models\Payment;
use App\Models\ReminderLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

class AssociationTestDataSeeder extends Seeder
{
    private const TEST_PHONE = '212697361188';

    public function run(): void
    {
        $this->command->info('🧹 Clearing old test memorizers and payments...');

        // Remove previous test memorizers to start clean
        Memorizer::whereIn('phone', [self::TEST_PHONE, '0697361188', '697361188'])->forceDelete();

        $this->command->info('👤 Ensuring association admin user exists...');

        $admin = User::firstOrCreate(
            ['email' => 'admin@association.com'],
            [
                'name'     => 'مدير الجمعية',
                'password' => Hash::make('password'),
                'role'     => 'admin',
                'phone'    => self::TEST_PHONE,
            ]
        );

        $this->command->info('👩‍🏫 Ensuring teachers and groups exist...');

        $teacher1 = User::firstOrCreate(
            ['email' => 'teacher1@test.com'],
            [
                'name'     => 'الأستاذ محمد الأمين',
                'password' => Hash::make('password'),
                'role'     => 'teacher',
                'phone'    => '0612345601',
                'sex'      => 'male',
            ]
        );

        $teacher2 = User::firstOrCreate(
            ['email' => 'teacher2@test.com'],
            [
                'name'     => 'الأستاذة فاطمة الزهراء',
                'password' => Hash::make('password'),
                'role'     => 'teacher',
                'phone'    => '0612345602',
                'sex'      => 'female',
            ]
        );

        $group1 = MemoGroup::firstOrCreate(
            ['name' => 'حلقة الفجر'],
            ['teacher_id' => $teacher1->id, 'price' => 100]
        );

        $group2 = MemoGroup::firstOrCreate(
            ['name' => 'حلقة المغرب'],
            ['teacher_id' => $teacher2->id, 'price' => 100]
        );

        $this->command->info('🧑‍🎓 Creating test memorizers with phone ' . self::TEST_PHONE . '...');

        $memorizers = [
            // --- 30 unpaid this month (main test targets for reminder) ---
            ['name' => 'عبد الرحمن البوعزاوي',    'group' => $group1, 'paid' => false, 'exempt' => false],
            ['name' => 'يوسف المرابط',              'group' => $group1, 'paid' => false, 'exempt' => false],
            ['name' => 'إبراهيم الشرقاوي',          'group' => $group1, 'paid' => false, 'exempt' => false],
            ['name' => 'سعيد بن عمر',               'group' => $group1, 'paid' => false, 'exempt' => false],
            ['name' => 'عمر الإدريسي',               'group' => $group1, 'paid' => false, 'exempt' => false],
            ['name' => 'محمد الأمين الزياني',        'group' => $group1, 'paid' => false, 'exempt' => false],
            ['name' => 'عبد العزيز الفاسي',          'group' => $group1, 'paid' => false, 'exempt' => false],
            ['name' => 'يحيى بن سالم',               'group' => $group1, 'paid' => false, 'exempt' => false],
            ['name' => 'خالد الوزاني',               'group' => $group1, 'paid' => false, 'exempt' => false],
            ['name' => 'عثمان الرباطي',              'group' => $group1, 'paid' => false, 'exempt' => false],
            ['name' => 'طه الصديقي',                 'group' => $group2, 'paid' => false, 'exempt' => false],
            ['name' => 'نوح العمراني',               'group' => $group2, 'paid' => false, 'exempt' => false],
            ['name' => 'هارون المريني',              'group' => $group2, 'paid' => false, 'exempt' => false],
            ['name' => 'سليمان الغرناطي',            'group' => $group2, 'paid' => false, 'exempt' => false],
            ['name' => 'إسماعيل الجزولي',            'group' => $group2, 'paid' => false, 'exempt' => false],
            ['name' => 'زيد البكري',                 'group' => $group2, 'paid' => false, 'exempt' => false],
            ['name' => 'بلال الحسناوي',              'group' => $group2, 'paid' => false, 'exempt' => false],
            ['name' => 'عمر بن الخطاب الأنصاري',    'group' => $group2, 'paid' => false, 'exempt' => false],
            ['name' => 'سفيان الثوري',               'group' => $group2, 'paid' => false, 'exempt' => false],
            ['name' => 'معاذ الكتاني',               'group' => $group2, 'paid' => false, 'exempt' => false],
            ['name' => 'أنس الفهري',                 'group' => $group1, 'paid' => false, 'exempt' => false],
            ['name' => 'جابر الشريف',                'group' => $group1, 'paid' => false, 'exempt' => false],
            ['name' => 'حذيفة العسقلاني',            'group' => $group1, 'paid' => false, 'exempt' => false],
            ['name' => 'سلمان الفارسي الصغير',       'group' => $group2, 'paid' => false, 'exempt' => false],
            ['name' => 'عبد الله بن مسعود الزغاري', 'group' => $group2, 'paid' => false, 'exempt' => false],
            ['name' => 'مصعب الخير',                 'group' => $group1, 'paid' => false, 'exempt' => false],
            ['name' => 'ربيع بن أنس',                'group' => $group2, 'paid' => false, 'exempt' => false],
            ['name' => 'وليد الدرقاوي',              'group' => $group1, 'paid' => false, 'exempt' => false],
            ['name' => 'عياض المكناسي',              'group' => $group2, 'paid' => false, 'exempt' => false],
            ['name' => 'كريم الوادي',                'group' => $group1, 'paid' => false, 'exempt' => false],

            // --- 5 already paid this month (green, excluded from reminder) ---
            ['name' => 'أيمن الغازي',               'group' => $group1, 'paid' => true,  'exempt' => false],
            ['name' => 'نور الدين الصغير',           'group' => $group2, 'paid' => true,  'exempt' => false],
            ['name' => 'رضوان التازي',               'group' => $group1, 'paid' => true,  'exempt' => false],
            ['name' => 'فؤاد المنصوري',              'group' => $group2, 'paid' => true,  'exempt' => false],
            ['name' => 'إلياس السوسي',               'group' => $group1, 'paid' => true,  'exempt' => false],

            // --- 5 exempt (excluded from reminder) ---
            ['name' => 'طارق المنصوري',              'group' => $group2, 'paid' => false, 'exempt' => true],
            ['name' => 'إدريس الأندلسي',             'group' => $group1, 'paid' => false, 'exempt' => true],
            ['name' => 'محمد الغزالي',               'group' => $group2, 'paid' => false, 'exempt' => true],
            ['name' => 'عبد الوهاب الشاذلي',        'group' => $group1, 'paid' => false, 'exempt' => true],
            ['name' => 'صالح المغراوي',              'group' => $group2, 'paid' => false, 'exempt' => true],
        ];

        foreach ($memorizers as $data) {
            $memorizer = Memorizer::create([
                'name'          => $data['name'],
                'phone'         => self::TEST_PHONE,
                'memo_group_id' => $data['group']->id,
                'exempt'        => $data['exempt'],
                'city'          => 'أسفي',
            ]);

            if ($data['paid']) {
                Payment::create([
                    'memorizer_id' => $memorizer->id,
                    'amount'       => 100,
                    'payment_date' => now()->startOfMonth()->addDays(rand(1, 10)),
                ]);
            }

        }

        $this->command->info('🗑️  Clearing payment/reminder caches...');
        Cache::flush();

        $this->command->newLine();
        $this->command->table(
            ['الاسم', 'المجموعة', 'الحالة', 'الهاتف'],
            collect($memorizers)->map(fn ($d) => [
                $d['name'],
                $d['group']->name,
                $d['exempt'] ? '🟡 معفى' : ($d['paid'] ? '🟢 مدفوع' : '🔴 غير مدفوع'),
                self::TEST_PHONE,
            ])->toArray()
        );

        $this->command->info('');
        $this->command->info('✅ Done! Login at /association with: admin@association.com / password');
        $this->command->info('📱 All memorizers have phone: +' . self::TEST_PHONE);
        $this->command->info('   • 30 unpaid → will receive reminders');
        $this->command->info('   • 5 already paid → green, excluded from reminder');
        $this->command->info('   • 5 exempt → excluded from reminder');
    }
}
