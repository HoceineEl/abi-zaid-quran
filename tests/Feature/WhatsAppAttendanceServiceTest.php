<?php

use App\Enums\MessageSubmissionType;
use App\Enums\WhatsAppConnectionStatus;
use App\Jobs\SendWhatsAppMessageJob;
use App\Models\Group;
use App\Models\GroupMessageTemplate;
use App\Models\Progress;
use App\Models\Student;
use App\Models\User;
use App\Models\WhatsAppMessageHistory;
use App\Models\WhatsAppSession;
use App\Services\WhatsAppAttendanceService;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    \Carbon\Carbon::setTestNow('2026-03-25 10:00:00');
});

afterEach(function () {
    \Carbon\Carbon::setTestNow();
    \Mockery::close();
});

function mockWhatsAppAttendanceApi(array $sendersByGroupJid): void
{
    $mock = \Mockery::mock(WhatsAppService::class);
    $mock->shouldReceive('getGroupAttendeesForDate')
        ->andReturnUsing(function (string $instanceName, string $groupJid) use ($sendersByGroupJid): array {
            return $sendersByGroupJid[$groupJid] ?? [];
        });

    app()->instance(WhatsAppService::class, $mock);
}

function createConnectedSessionFor(User $user): WhatsAppSession
{
    return WhatsAppSession::create([
        'user_id' => $user->id,
        'name' => 'test-session',
        'status' => WhatsAppConnectionStatus::CONNECTED,
        'connected_at' => now(),
        'last_activity_at' => now(),
    ]);
}

it('builds a bulk preview and queues reminders only for remaining students with valid phones', function () {
    Bus::fake();

    $user = User::factory()->create(['role' => 'admin']);
    $this->actingAs($user);
    $session = createConnectedSessionFor($user);

    $group = Group::factory()->create([
        'name' => 'المجموعة الأولى',
        'whatsapp_group_jid' => 'group-1@g.us',
        'message_submission_type' => MessageSubmissionType::Media,
    ]);

    $skippedGroup = Group::factory()->create([
        'name' => 'المجموعة الثانية',
        'whatsapp_group_jid' => null,
    ]);

    $matchedStudent = Student::factory()->create([
        'group_id' => $group->id,
        'name' => 'طالب مطابق',
        'phone' => '0611111111',
        'order_no' => 1,
    ]);

    $alreadyPresentStudent = Student::factory()->create([
        'group_id' => $group->id,
        'name' => 'طالب مسجل',
        'phone' => '0622222222',
        'order_no' => 2,
    ]);

    $remainingValidStudent = Student::factory()->create([
        'group_id' => $group->id,
        'name' => 'طالب متبق صالح',
        'phone' => '0633333333',
        'order_no' => 3,
    ]);

    $remainingInvalidStudent = Student::factory()->create([
        'group_id' => $group->id,
        'name' => 'طالب متبق بدون رقم صالح',
        'phone' => 'غير صالح',
        'order_no' => 4,
    ]);

    $alreadyAbsentStudent = Student::factory()->create([
        'group_id' => $group->id,
        'name' => 'طالب غائب مسبقاً',
        'phone' => '0644444444',
        'order_no' => 5,
    ]);

    $alreadyPresentStudent->progresses()->create([
        'date' => today()->format('Y-m-d'),
        'status' => 'memorized',
    ]);

    $alreadyAbsentStudent->progresses()->create([
        'date' => today()->format('Y-m-d'),
        'status' => 'absent',
        'with_reason' => false,
    ]);

    $templateId = DB::table('group_message_templates')->insertGetId([
        'name' => 'تذكير افتراضي',
        'content' => 'تذكير {student_name} في {group_name}',
        'group_id' => $group->id,
        'is_default' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $template = GroupMessageTemplate::findOrFail($templateId);
    $group->messageTemplates()->attach($template->id, ['is_default' => true]);

    mockWhatsAppAttendanceApi([
        'group-1@g.us' => ['212611111111'],
    ]);

    $service = app(WhatsAppAttendanceService::class);
    $preview = $service->buildBulkPreview(
        Group::query()
            ->with([
                'students.progresses' => fn ($query) => $query
                    ->where('date', today()->format('Y-m-d'))
                    ->select(['id', 'student_id', 'date', 'status', 'with_reason', 'comment']),
                'messageTemplates',
            ])
            ->get(),
        today()->format('Y-m-d'),
        false,
        true,
    );

    expect($preview['totals']['ready_group_count'])->toBe(1)
        ->and($preview['totals']['skipped_group_count'])->toBe(1)
        ->and($preview['totals']['matched_students'])->toBe(1)
        ->and($preview['totals']['already_present_students'])->toBe(1)
        ->and($preview['totals']['to_mark_present_students'])->toBe(1)
        ->and($preview['totals']['remaining_students'])->toBe(2)
        ->and($preview['totals']['planned_reminders'])->toBe(1)
        ->and($preview['totals']['planned_invalid_reminder_phones'])->toBe(1);

    $result = $service->applyBulkPreview($preview, false, true);

    expect($result['groups_processed'])->toBe(1)
        ->and($result['groups_skipped'])->toBe(1)
        ->and($result['students_marked_present'])->toBe(1)
        ->and($result['students_marked_absent'])->toBe(0)
        ->and($result['reminders_queued'])->toBe(1)
        ->and($result['invalid_reminder_phones'])->toBe(1)
        ->and($result['reminder_failures'])->toBe(0);

    expect(
        Progress::query()
            ->where('student_id', $matchedStudent->id)
            ->where('date', today()->format('Y-m-d'))
            ->where('status', 'memorized')
            ->exists()
    )->toBeTrue();

    expect(
        Progress::query()
            ->where('student_id', $remainingValidStudent->id)
            ->where('date', today()->format('Y-m-d'))
            ->exists()
    )->toBeFalse();

    expect(
        WhatsAppMessageHistory::query()
            ->where('session_id', $session->id)
            ->where('recipient_phone', '212633333333')
            ->where('message_content', 'تذكير طالب متبق صالح في المجموعة الأولى')
            ->exists()
    )->toBeTrue();

    expect(
        WhatsAppMessageHistory::query()
            ->where('session_id', $session->id)
            ->where('recipient_phone', 'غير صالح')
            ->exists()
    )->toBeFalse();

    Bus::assertDispatchedTimes(SendWhatsAppMessageJob::class, 1);
});

it('marks remaining students absent instead of reminding them when absent toggle is enabled', function () {
    Bus::fake();

    $user = User::factory()->create(['role' => 'admin']);
    $this->actingAs($user);
    createConnectedSessionFor($user);

    $group = Group::factory()->create([
        'name' => 'مجموعة الغياب',
        'whatsapp_group_jid' => 'group-absent@g.us',
        'message_submission_type' => MessageSubmissionType::Media,
    ]);

    $matchedStudent = Student::factory()->create([
        'group_id' => $group->id,
        'phone' => '0655555555',
        'order_no' => 1,
    ]);

    $remainingStudent = Student::factory()->create([
        'group_id' => $group->id,
        'phone' => '0666666666',
        'order_no' => 2,
    ]);

    mockWhatsAppAttendanceApi([
        'group-absent@g.us' => ['212655555555'],
    ]);

    $service = app(WhatsAppAttendanceService::class);
    $preview = $service->buildBulkPreview(
        Group::query()
            ->with([
                'students.progresses' => fn ($query) => $query
                    ->where('date', today()->format('Y-m-d'))
                    ->select(['id', 'student_id', 'date', 'status', 'with_reason', 'comment']),
            ])
            ->get(),
        today()->format('Y-m-d'),
        true,
        true,
    );

    expect($preview['totals']['planned_absent_students'])->toBe(1)
        ->and($preview['totals']['planned_reminders'])->toBe(0);

    $result = $service->applyBulkPreview($preview, true, true);

    expect($result['students_marked_present'])->toBe(1)
        ->and($result['students_marked_absent'])->toBe(1)
        ->and($result['reminders_queued'])->toBe(0);

    expect(
        Progress::query()
            ->where('student_id', $remainingStudent->id)
            ->where('date', today()->format('Y-m-d'))
            ->where('status', 'absent')
            ->exists()
    )->toBeTrue();

    Bus::assertNotDispatched(SendWhatsAppMessageJob::class);
});
