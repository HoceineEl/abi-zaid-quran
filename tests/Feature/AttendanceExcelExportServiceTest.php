<?php

use App\Enums\MemorizationScore;
use App\Models\Attendance;
use App\Models\MemoGroup;
use App\Models\Memorizer;
use App\Models\User;
use App\Services\AttendanceExcelExportService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;

beforeEach(function () {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite.database', ':memory:');
    config()->set('database.connections.sqlite.foreign_key_constraints', true);

    DB::purge('sqlite');
    DB::reconnect('sqlite');

    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->nullable();
        $table->timestamp('email_verified_at')->nullable();
        $table->string('password')->nullable();
        $table->string('phone')->nullable();
        $table->string('role')->nullable();
        $table->string('sex')->nullable();
        $table->rememberToken();
        $table->timestamps();
    });

    Schema::create('guardians', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->string('phone')->nullable();
        $table->timestamps();
    });

    Schema::create('memo_groups', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->float('price')->default(70);
        $table->foreignId('teacher_id')->nullable();
        $table->json('days')->nullable();
        $table->timestamps();
    });

    Schema::create('memorizers', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('phone')->nullable();
        $table->string('address')->nullable();
        $table->date('birth_date')->nullable();
        $table->string('sex')->nullable();
        $table->string('city')->nullable();
        $table->string('photo')->nullable();
        $table->boolean('exempt')->default(false);
        $table->foreignId('memo_group_id');
        $table->foreignId('teacher_id')->nullable();
        $table->foreignId('guardian_id')->nullable();
        $table->foreignId('round_id')->nullable();
        $table->timestamps();
    });

    Schema::create('attendances', function (Blueprint $table) {
        $table->id();
        $table->foreignId('memorizer_id');
        $table->date('date');
        $table->time('check_in_time')->nullable();
        $table->time('check_out_time')->nullable();
        $table->json('notes')->nullable();
        $table->string('score')->nullable();
        $table->string('custom_note')->nullable();
        $table->foreignId('created_by')->nullable();
        $table->timestamps();
    });
});

it('exports a single group with correct day statuses and without a details sheet', function () {
    $teacher = User::factory()->create([
        'role' => 'teacher',
        'email' => 'teacher1@example.com',
    ]);

    $group = MemoGroup::factory()->create([
        'name' => 'مجموعة الاختبار',
        'teacher_id' => $teacher->id,
        'days' => ['thursday', 'friday', 'saturday'],
    ]);

    $memorizer = Memorizer::factory()->create([
        'name' => 'أحمد',
        'memo_group_id' => $group->id,
    ]);

    Attendance::create([
        'memorizer_id' => $memorizer->id,
        'date' => '2026-01-01',
        'check_in_time' => '08:00:00',
        'score' => MemorizationScore::EXCELLENT,
    ]);

    Attendance::create([
        'memorizer_id' => $memorizer->id,
        'date' => '2026-01-02',
        'check_in_time' => null,
    ]);

    $response = app(AttendanceExcelExportService::class)->download($group, [
        'date_from' => '2026-01-01',
        'date_to' => '2026-01-03',
        'include_contact_columns' => false,
    ]);

    $spreadsheet = IOFactory::load($response->getFile()->getPathname());

    expect($spreadsheet->getSheetNames())->toBe([
        'ملخص',
        'الحضور والتقييم',
    ]);

    $sheet = $spreadsheet->getSheetByName('الحضور والتقييم');

    expect($sheet)->not->toBeNull();
    expect($sheet->getCell('B2')->getValue())->toBe('أحمد');
    expect($sheet->getCell('D2')->getValue())->toBe('غائب');
    expect($sheet->getCell('E2')->getValue())->toBe('غ.م');
    expect($sheet->getCell('B1')->getValue())->toBe('اسم الطالب');
    expect($sheet->getCell('C1')->getValue())->toContain('01/01');
    expect($sheet->getCell('A1')->getValue())->toBe('#');
    expect($sheet->getCell('B1')->getValue())->not->toBe('رقم الطالب');

    $presentCell = $sheet->getCell('C2')->getValue();
    expect($presentCell)->toBeInstanceOf(RichText::class);
    expect((string) $presentCell)->toBe("حاضر\nممتاز");
    expect($sheet->getStyle('C2')->getFill()->getStartColor()->getRGB())->toBe('BBF7D0');
    expect($sheet->getStyle('D2')->getFill()->getStartColor()->getRGB())->toBe('FECACA');
    expect($sheet->getStyle('E2')->getFill()->getStartColor()->getRGB())->toBe('E5E7EB');
    expect($presentCell->getRichTextElements()[2]->getFont()->getColor()->getRGB())->toBe('065F46');
});

it('exports all groups into separate sheets and keeps students visible without attendance', function () {
    $teacherOne = User::factory()->create([
        'role' => 'teacher',
        'email' => 'teacher2@example.com',
        'sex' => 'male',
    ]);
    $teacherTwo = User::factory()->create([
        'role' => 'teacher',
        'email' => 'teacher3@example.com',
        'sex' => 'female',
    ]);

    $groupOne = MemoGroup::factory()->create([
        'name' => 'المجموعة الأولى',
        'teacher_id' => $teacherOne->id,
        'days' => ['thursday'],
    ]);
    $groupTwo = MemoGroup::factory()->create([
        'name' => 'المجموعة الثانية',
        'teacher_id' => $teacherTwo->id,
        'days' => ['thursday'],
    ]);

    $memorizerOne = Memorizer::factory()->create([
        'name' => 'زيد',
        'memo_group_id' => $groupOne->id,
    ]);
    $memorizerTwo = Memorizer::factory()->create([
        'name' => 'خالد',
        'memo_group_id' => $groupTwo->id,
    ]);

    Attendance::create([
        'memorizer_id' => $memorizerTwo->id,
        'date' => '2026-01-01',
        'check_in_time' => '08:15:00',
    ]);

    $response = app(AttendanceExcelExportService::class)->downloadAllGroups([
        'date_from' => '2026-01-01',
        'date_to' => '2026-01-01',
        'include_contact_columns' => false,
    ]);

    $spreadsheet = IOFactory::load($response->getFile()->getPathname());

    expect($spreadsheet->getSheetNames())->toBe([
        'ملخص',
        'المجموعة الأولى',
        'المجموعة الثانية',
    ]);

    $firstGroupSheet = $spreadsheet->getSheetByName('المجموعة الأولى');
    $secondGroupSheet = $spreadsheet->getSheetByName('المجموعة الثانية');

    expect($firstGroupSheet)->not->toBeNull();
    expect($secondGroupSheet)->not->toBeNull();

    expect($firstGroupSheet->getCell('B2')->getValue())->toBe($memorizerOne->name);
    expect($firstGroupSheet->getCell('C2')->getValue())->toBe('غ.م');
    expect($firstGroupSheet->getStyle('C2')->getFill()->getStartColor()->getRGB())->toBe('E5E7EB');

    expect($secondGroupSheet->getCell('B2')->getValue())->toBe($memorizerTwo->name);
    expect($secondGroupSheet->getCell('C2')->getValue())->toBe('حاضر');
    expect($secondGroupSheet->getStyle('C2')->getFill()->getStartColor()->getRGB())->toBe('BBF7D0');
});

it('can export only one gender in all-groups mode', function () {
    $maleTeacher = User::factory()->create([
        'role' => 'teacher',
        'email' => 'teacher4@example.com',
        'sex' => 'male',
    ]);
    $femaleTeacher = User::factory()->create([
        'role' => 'teacher',
        'email' => 'teacher5@example.com',
        'sex' => 'female',
    ]);

    $maleGroup = MemoGroup::factory()->create([
        'name' => 'مجموعة الذكور',
        'teacher_id' => $maleTeacher->id,
        'days' => ['thursday'],
    ]);
    $femaleGroup = MemoGroup::factory()->create([
        'name' => 'مجموعة الإناث',
        'teacher_id' => $femaleTeacher->id,
        'days' => ['thursday'],
    ]);

    Memorizer::factory()->create([
        'name' => 'سعد',
        'memo_group_id' => $maleGroup->id,
    ]);
    Memorizer::factory()->create([
        'name' => 'آمنة',
        'memo_group_id' => $femaleGroup->id,
    ]);

    $response = app(AttendanceExcelExportService::class)->downloadAllGroups([
        'date_from' => '2026-01-01',
        'date_to' => '2026-01-01',
        'sex_filter' => 'female',
    ]);

    $spreadsheet = IOFactory::load($response->getFile()->getPathname());

    expect($spreadsheet->getSheetNames())->toBe([
        'ملخص',
        'مجموعة الإناث',
    ]);
    expect($spreadsheet->getSheetByName('مجموعة الذكور'))->toBeNull();
    expect($spreadsheet->getSheetByName('مجموعة الإناث')->getCell('B2')->getValue())->toBe('آمنة');
});
