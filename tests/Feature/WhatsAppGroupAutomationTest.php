<?php

use App\Enums\WhatsAppConnectionStatus;
use App\Jobs\RunGroupWhatsAppAutomationJob;
use App\Jobs\SendWhatsAppMessageJob;
use App\Models\Group;
use App\Models\GroupAutomationRun;
use App\Models\Student;
use App\Models\User;
use App\Models\WhatsAppMessageHistory;
use App\Models\WhatsAppSession;
use App\Services\GroupWhatsAppAutomationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

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

    Schema::create('messages', function (Blueprint $table) {
        $table->id();
        $table->text('content')->nullable();
        $table->timestamps();
    });

    Schema::create('groups', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('type');
        $table->boolean('is_onsite')->default(false);
        $table->boolean('is_quran_group')->default(false);
        $table->foreignId('message_id')->nullable();
        $table->string('message_submission_type')->default('media');
        $table->json('ignored_names_phones')->nullable();
        $table->string('whatsapp_group_jid')->nullable();
        $table->foreignId('whatsapp_manager_id')->nullable();
        $table->time('auto_attendance_close_time')->default('23:00:00');
        $table->softDeletes();
        $table->timestamps();
    });

    Schema::create('group_manager', function (Blueprint $table) {
        $table->foreignId('group_id');
        $table->foreignId('manager_id');
        $table->primary(['group_id', 'manager_id']);
    });

    Schema::create('students', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('phone')->nullable();
        $table->string('sex')->nullable();
        $table->string('city')->nullable();
        $table->foreignId('group_id');
        $table->integer('order_no')->default(1);
        $table->timestamps();
    });

    Schema::create('progress', function (Blueprint $table) {
        $table->id();
        $table->foreignId('student_id');
        $table->foreignId('created_by')->nullable();
        $table->date('date');
        $table->text('comment')->nullable();
        $table->string('status')->nullable();
        $table->boolean('with_reason')->nullable();
        $table->foreignId('page_id')->nullable();
        $table->integer('lines_from')->nullable();
        $table->integer('lines_to')->nullable();
        $table->json('notes')->nullable();
        $table->timestamps();
    });

    Schema::create('group_message_templates', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->text('content');
        $table->timestamps();
    });

    Schema::create('group_message_template_pivot', function (Blueprint $table) {
        $table->id();
        $table->foreignId('group_id');
        $table->foreignId('group_message_template_id');
        $table->boolean('is_default')->default(false);
        $table->timestamps();
    });

    Schema::create('whatsapp_sessions', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id');
        $table->string('name')->nullable();
        $table->string('status')->default('disconnected');
        $table->longText('qr_code')->nullable();
        $table->json('session_data')->nullable();
        $table->json('cached_groups')->nullable();
        $table->timestamp('connected_at')->nullable();
        $table->timestamp('last_activity_at')->nullable();
        $table->timestamps();
    });

    Schema::create('whatsapp_message_histories', function (Blueprint $table) {
        $table->id();
        $table->foreignId('session_id');
        $table->foreignId('sender_user_id')->nullable();
        $table->string('recipient_phone');
        $table->string('recipient_name')->nullable();
        $table->string('message_type')->default('text');
        $table->text('message_content');
        $table->json('media_data')->nullable();
        $table->string('status')->default('queued');
        $table->string('whatsapp_message_id')->nullable();
        $table->timestamp('sent_at')->nullable();
        $table->timestamp('failed_at')->nullable();
        $table->integer('retry_count')->default(0);
        $table->text('error_message')->nullable();
        $table->json('metadata')->nullable();
        $table->softDeletes();
        $table->timestamps();
    });

    Schema::create('group_automation_runs', function (Blueprint $table) {
        $table->id();
        $table->foreignId('group_id');
        $table->date('run_date');
        $table->string('phase');
        $table->string('status')->default('running');
        $table->json('details')->nullable();
        $table->text('error_message')->nullable();
        $table->timestamp('started_at')->nullable();
        $table->timestamp('completed_at')->nullable();
        $table->timestamps();

        $table->unique(['group_id', 'run_date', 'phase'], 'group_automation_runs_unique');
    });
});

function fakeAttendanceApi(string $groupJid, string $senderPhone): void
{
    $baseUrl = rtrim(config('whatsapp.api_url', 'http://localhost:8080'), '/');

    Http::fake([
        "{$baseUrl}/group/participants/*" => Http::response([], 200),
        "{$baseUrl}/chat/findMessages/*" => Http::response([
            'messages' => [
                'records' => [
                    [
                        'key' => [
                            'remoteJid' => $groupJid,
                            'participantAlt' => "{$senderPhone}@s.whatsapp.net",
                            'fromMe' => false,
                        ],
                        'messageType' => 'audioMessage',
                    ],
                ],
            ],
        ], 200),
        "{$baseUrl}/instance/connectionState/*" => Http::response([
            'instance' => [
                'owner' => '212699999999',
                'state' => 'open',
            ],
        ], 200),
    ]);
}

function createSessionForUser(User $user, string $name, WhatsAppConnectionStatus $status): WhatsAppSession
{
    return WhatsAppSession::create([
        'user_id' => $user->id,
        'name' => $name,
        'status' => $status,
        'connected_at' => now(),
        'last_activity_at' => now(),
    ]);
}

it('uses the selected whatsapp manager session before admin fallback', function () {
    Queue::fake();

    $admin = User::factory()->create(['role' => 'admin']);
    $manager = User::factory()->create(['role' => 'follower']);

    $managerSession = createSessionForUser($manager, 'manager-session', WhatsAppConnectionStatus::CONNECTED);
    createSessionForUser($admin, 'admin-session', WhatsAppConnectionStatus::CONNECTED);

    $group = Group::factory()->create([
        'whatsapp_group_jid' => 'group-1@g.us',
        'whatsapp_manager_id' => $manager->id,
        'auto_attendance_close_time' => '23:00:00',
    ]);
    $group->managers()->attach([$admin->id, $manager->id]);

    $matchedStudent = Student::factory()->create([
        'group_id' => $group->id,
        'phone' => '0600000001',
        'order_no' => 1,
    ]);
    $unmarkedStudent = Student::factory()->create([
        'group_id' => $group->id,
        'phone' => '0600000002',
        'order_no' => 2,
    ]);

    fakeAttendanceApi($group->whatsapp_group_jid, '212600000001');

    $result = app(GroupWhatsAppAutomationService::class)
        ->runEveningPass($group, now()->toDateString());

    expect($result['session_id'])->toBe($managerSession->id)
        ->and($result['marked_count'])->toBe(1)
        ->and($result['reminder_count'])->toBe(1);

    expect($matchedStudent->fresh()->today_progress->status)->toBe('memorized');

    expect(WhatsAppMessageHistory::query()->where('recipient_phone', '212600000002')->value('session_id'))
        ->toBe($managerSession->id);

    Queue::assertPushed(SendWhatsAppMessageJob::class, fn (SendWhatsAppMessageJob $job) => $job->sessionId === (string) $managerSession->id
        && $job->studentId === $unmarkedStudent->id);
});

it('falls back to the admin session when the selected manager has no connected session', function () {
    Queue::fake();

    $admin = User::factory()->create(['role' => 'admin']);
    $manager = User::factory()->create(['role' => 'follower']);

    $adminSession = createSessionForUser($admin, 'admin-session', WhatsAppConnectionStatus::CONNECTED);
    createSessionForUser($manager, 'manager-session', WhatsAppConnectionStatus::DISCONNECTED);

    $group = Group::factory()->create([
        'whatsapp_group_jid' => 'group-2@g.us',
        'whatsapp_manager_id' => $manager->id,
        'auto_attendance_close_time' => '23:00:00',
    ]);
    $group->managers()->attach([$admin->id, $manager->id]);

    Student::factory()->create([
        'group_id' => $group->id,
        'phone' => '0600000001',
        'order_no' => 1,
    ]);
    Student::factory()->create([
        'group_id' => $group->id,
        'phone' => '0600000002',
        'order_no' => 2,
    ]);

    fakeAttendanceApi($group->whatsapp_group_jid, '212600000001');

    $result = app(GroupWhatsAppAutomationService::class)
        ->runEveningPass($group, now()->toDateString());

    expect($result['session_id'])->toBe($adminSession->id);
    expect(WhatsAppMessageHistory::query()->first()?->session_id)->toBe($adminSession->id);
});

it('uses the lowest-id admin as the fallback when multiple admins exist', function () {
    Queue::fake();

    $primaryAdmin = User::factory()->create(['role' => 'admin']);
    $secondaryAdmin = User::factory()->create(['role' => 'admin']);
    $manager = User::factory()->create(['role' => 'follower']);

    $primaryAdminSession = createSessionForUser($primaryAdmin, 'primary-admin-session', WhatsAppConnectionStatus::CONNECTED);
    createSessionForUser($secondaryAdmin, 'secondary-admin-session', WhatsAppConnectionStatus::CONNECTED);
    createSessionForUser($manager, 'manager-session', WhatsAppConnectionStatus::DISCONNECTED);

    $group = Group::factory()->create([
        'whatsapp_group_jid' => 'group-2b@g.us',
        'whatsapp_manager_id' => $manager->id,
        'auto_attendance_close_time' => '23:00:00',
    ]);
    $group->managers()->attach([$primaryAdmin->id, $secondaryAdmin->id, $manager->id]);

    Student::factory()->create([
        'group_id' => $group->id,
        'phone' => '0600000001',
        'order_no' => 1,
    ]);
    Student::factory()->create([
        'group_id' => $group->id,
        'phone' => '0600000002',
        'order_no' => 2,
    ]);

    fakeAttendanceApi($group->whatsapp_group_jid, '212600000001');

    $result = app(GroupWhatsAppAutomationService::class)
        ->runEveningPass($group, now()->toDateString());

    expect($result['session_id'])->toBe($primaryAdminSession->id);
});

it('does not queue reminders during the close pass', function () {
    Queue::fake();

    $admin = User::factory()->create(['role' => 'admin']);
    createSessionForUser($admin, 'admin-session', WhatsAppConnectionStatus::CONNECTED);

    $group = Group::factory()->create([
        'whatsapp_group_jid' => 'group-3@g.us',
        'whatsapp_manager_id' => $admin->id,
        'auto_attendance_close_time' => '23:00:00',
    ]);
    $group->managers()->attach($admin->id);

    $matchedStudent = Student::factory()->create([
        'group_id' => $group->id,
        'phone' => '0600000001',
        'order_no' => 1,
    ]);
    Student::factory()->create([
        'group_id' => $group->id,
        'phone' => '0600000002',
        'order_no' => 2,
    ]);

    fakeAttendanceApi($group->whatsapp_group_jid, '212600000001');

    $result = app(GroupWhatsAppAutomationService::class)
        ->runClosePass($group, now()->toDateString());

    expect($result['marked_count'])->toBe(1)
        ->and($result['reminder_count'])->toBe(0)
        ->and($matchedStudent->fresh()->today_progress->status)->toBe('memorized')
        ->and(WhatsAppMessageHistory::count())->toBe(0);

    Queue::assertNotPushed(SendWhatsAppMessageJob::class);
});

it('dispatches evening and close automation jobs when both phases are due', function () {
    Bus::fake();

    $admin = User::factory()->create(['role' => 'admin']);

    $group = Group::factory()->create([
        'whatsapp_group_jid' => 'group-4@g.us',
        'whatsapp_manager_id' => $admin->id,
        'auto_attendance_close_time' => '23:00:00',
    ]);
    $group->managers()->attach($admin->id);

    $this->artisan('groups:run-whatsapp-automation', [
        '--date' => '2026-03-11',
        '--time' => '23:15',
    ])->assertSuccessful();

    Bus::assertDispatchedTimes(RunGroupWhatsAppAutomationJob::class, 2);
    Bus::assertDispatched(RunGroupWhatsAppAutomationJob::class, fn (RunGroupWhatsAppAutomationJob $job) => $job->groupId === $group->id
        && $job->phase === GroupWhatsAppAutomationService::EVENING_REMINDER_PASS);
    Bus::assertDispatched(RunGroupWhatsAppAutomationJob::class, fn (RunGroupWhatsAppAutomationJob $job) => $job->groupId === $group->id
        && $job->phase === GroupWhatsAppAutomationService::CLOSE_PASS);
});

it('does not redispatch a phase that already has an automation run record', function () {
    Bus::fake();

    $admin = User::factory()->create(['role' => 'admin']);

    $group = Group::factory()->create([
        'whatsapp_group_jid' => 'group-5@g.us',
        'whatsapp_manager_id' => $admin->id,
        'auto_attendance_close_time' => '23:00:00',
    ]);
    $group->managers()->attach($admin->id);

    GroupAutomationRun::create([
        'group_id' => $group->id,
        'run_date' => '2026-03-11',
        'phase' => GroupWhatsAppAutomationService::EVENING_REMINDER_PASS,
        'status' => 'completed',
        'started_at' => now(),
        'completed_at' => now(),
    ]);

    $this->artisan('groups:run-whatsapp-automation', [
        '--date' => '2026-03-11',
        '--time' => '23:15',
    ])->assertSuccessful();

    Bus::assertDispatchedTimes(RunGroupWhatsAppAutomationJob::class, 1);
    Bus::assertDispatched(RunGroupWhatsAppAutomationJob::class, fn (RunGroupWhatsAppAutomationJob $job) => $job->phase === GroupWhatsAppAutomationService::CLOSE_PASS);
});

it('redispatches a skipped phase later the same evening', function () {
    Bus::fake();

    $admin = User::factory()->create(['role' => 'admin']);

    $group = Group::factory()->create([
        'whatsapp_group_jid' => 'group-6@g.us',
        'whatsapp_manager_id' => $admin->id,
        'auto_attendance_close_time' => '23:00:00',
    ]);
    $group->managers()->attach($admin->id);

    GroupAutomationRun::create([
        'group_id' => $group->id,
        'run_date' => '2026-03-11',
        'phase' => GroupWhatsAppAutomationService::EVENING_REMINDER_PASS,
        'status' => 'skipped',
        'started_at' => now(),
        'completed_at' => now(),
    ]);

    $this->artisan('groups:run-whatsapp-automation', [
        '--date' => '2026-03-11',
        '--time' => '23:15',
    ])->assertSuccessful();

    Bus::assertDispatchedTimes(RunGroupWhatsAppAutomationJob::class, 2);
    Bus::assertDispatched(RunGroupWhatsAppAutomationJob::class, fn (RunGroupWhatsAppAutomationJob $job) => $job->phase === GroupWhatsAppAutomationService::EVENING_REMINDER_PASS);
    Bus::assertDispatched(RunGroupWhatsAppAutomationJob::class, fn (RunGroupWhatsAppAutomationJob $job) => $job->phase === GroupWhatsAppAutomationService::CLOSE_PASS);
});

it('redispatches a failed phase later the same evening', function () {
    Bus::fake();

    $admin = User::factory()->create(['role' => 'admin']);

    $group = Group::factory()->create([
        'whatsapp_group_jid' => 'group-7@g.us',
        'whatsapp_manager_id' => $admin->id,
        'auto_attendance_close_time' => '23:00:00',
    ]);
    $group->managers()->attach($admin->id);

    GroupAutomationRun::create([
        'group_id' => $group->id,
        'run_date' => '2026-03-11',
        'phase' => GroupWhatsAppAutomationService::EVENING_REMINDER_PASS,
        'status' => 'failed',
        'started_at' => now(),
        'completed_at' => now(),
        'error_message' => 'temporary outage',
    ]);

    $this->artisan('groups:run-whatsapp-automation', [
        '--date' => '2026-03-11',
        '--time' => '23:15',
    ])->assertSuccessful();

    Bus::assertDispatchedTimes(RunGroupWhatsAppAutomationJob::class, 2);
    Bus::assertDispatched(RunGroupWhatsAppAutomationJob::class, fn (RunGroupWhatsAppAutomationJob $job) => $job->phase === GroupWhatsAppAutomationService::EVENING_REMINDER_PASS);
    Bus::assertDispatched(RunGroupWhatsAppAutomationJob::class, fn (RunGroupWhatsAppAutomationJob $job) => $job->phase === GroupWhatsAppAutomationService::CLOSE_PASS);
});

it('does not redispatch a running phase', function () {
    Bus::fake();

    $admin = User::factory()->create(['role' => 'admin']);

    $group = Group::factory()->create([
        'whatsapp_group_jid' => 'group-8@g.us',
        'whatsapp_manager_id' => $admin->id,
        'auto_attendance_close_time' => '23:00:00',
    ]);
    $group->managers()->attach($admin->id);

    GroupAutomationRun::create([
        'group_id' => $group->id,
        'run_date' => '2026-03-11',
        'phase' => GroupWhatsAppAutomationService::EVENING_REMINDER_PASS,
        'status' => 'running',
        'started_at' => now(),
    ]);

    $this->artisan('groups:run-whatsapp-automation', [
        '--date' => '2026-03-11',
        '--time' => '23:15',
    ])->assertSuccessful();

    Bus::assertDispatchedTimes(RunGroupWhatsAppAutomationJob::class, 1);
    Bus::assertDispatched(RunGroupWhatsAppAutomationJob::class, fn (RunGroupWhatsAppAutomationJob $job) => $job->phase === GroupWhatsAppAutomationService::CLOSE_PASS);
});
