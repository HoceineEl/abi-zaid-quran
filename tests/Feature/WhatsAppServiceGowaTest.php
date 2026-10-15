<?php

use App\Enums\MessageSubmissionType;
use App\Enums\WhatsAppConnectionStatus;
use App\Models\User;
use App\Models\WhatsAppSession;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'whatsapp.api_url' => 'https://gowa.test',
        'whatsapp.username' => 'admin',
        'whatsapp.password' => 'secret',
        'whatsapp.typing.enabled' => false,
    ]);
});

function gowaOk(mixed $results): array
{
    return ['code' => 'SUCCESS', 'message' => 'ok', 'results' => $results];
}

it('sends a text message with basic auth and the session device header', function () {
    Http::fake([
        'gowa.test/send/message' => Http::response(gowaOk(['message_id' => 'ABC123'])),
    ]);

    $service = new WhatsAppService;
    $result = $service->sendTextMessage('My Session', '0612345678', 'السلام عليكم');

    expect($result['message_id'])->toBe('ABC123');

    Http::assertSent(fn (Request $request) => $request->url() === 'https://gowa.test/send/message'
        && $request->hasHeader('Authorization', 'Basic '.base64_encode('admin:secret'))
        && $request->hasHeader('X-Device-Id', $service->deviceId('My Session'))
        && $request['phone'] === '212612345678@s.whatsapp.net'
        && $request['message'] === 'السلام عليكم');
});

it('throws when the API does not return success', function () {
    Http::fake([
        'gowa.test/send/message' => Http::response(['code' => 'INTERNAL_SERVER_ERROR', 'message' => 'not logged in'], 500),
    ]);

    (new WhatsAppService)->sendTextMessage('My Session', '0612345678', 'hi');
})->throws(RuntimeException::class);

it('maps device states to the connection status enum', function (string $gowaState, WhatsAppConnectionStatus $expected) {
    $service = new WhatsAppService;

    Http::fake([
        'gowa.test/devices/'.$service->deviceId('My Session') => Http::response(gowaOk([
            'id' => $service->deviceId('My Session'),
            'state' => $gowaState,
            'jid' => '212600000000@s.whatsapp.net',
        ])),
    ]);

    $status = $service->getInstanceStatus('My Session');

    expect(WhatsAppConnectionStatus::fromApiStatus($status['instance']['state']))->toBe($expected)
        ->and($status['instance']['ownerJid'])->toBe('212600000000@s.whatsapp.net');
})->with([
    ['logged_in', WhatsAppConnectionStatus::CONNECTED],
    ['connected', WhatsAppConnectionStatus::CONNECTING],
    ['connecting', WhatsAppConnectionStatus::CONNECTING],
    ['disconnected', WhatsAppConnectionStatus::DISCONNECTED],
]);

it('creates a device and stores the QR image when starting a new session', function () {
    $session = WhatsAppSession::create([
        'user_id' => User::factory()->create()->id,
        'name' => 'جلسة واتساب - تجربة',
        'status' => WhatsAppConnectionStatus::DISCONNECTED,
    ]);

    $service = new WhatsAppService;
    $deviceId = $service->deviceId($session->name);

    Http::fake([
        "gowa.test/devices/{$deviceId}/login" => Http::response(gowaOk([
            'device_id' => $deviceId,
            'qr_link' => 'http://localhost:3777/statics/images/qrcode/scan-qr-1.png',
            'qr_duration' => 30,
        ])),
        "gowa.test/devices/{$deviceId}" => Http::response(['code' => 'NOT_FOUND', 'message' => 'device not found'], 404),
        'gowa.test/devices' => Http::response(gowaOk(['id' => $deviceId, 'state' => 'disconnected'])),
        'gowa.test/statics/images/qrcode/scan-qr-1.png' => Http::response('PNGDATA', 200, ['Content-Type' => 'image/png']),
    ]);

    $service->startSessionAsync($session);

    expect($session->fresh())
        ->status->toBe(WhatsAppConnectionStatus::PENDING)
        ->qr_code->toBe('data:image/png;base64,'.base64_encode('PNGDATA'));

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->url() === 'https://gowa.test/devices'
        && $request['device_id'] === $deviceId);
});

it('fails loudly instead of returning no attendees when messages cannot be fetched', function () {
    Http::fake([
        'gowa.test/group/participants*' => Http::response(gowaOk(['participants' => []])),
        'gowa.test/devices/*' => Http::response(gowaOk(['state' => 'logged_in', 'jid' => '212600000000@s.whatsapp.net'])),
        'gowa.test/chat/*' => Http::response(['code' => 'INTERNAL_SERVER_ERROR', 'message' => 'device not logged in'], 500),
    ]);

    (new WhatsAppService)->getGroupAttendeesForDate('S', '120363000000000001@g.us', '2026-03-25');
})->throws(RuntimeException::class);

it('maps joined groups to id and subject', function () {
    Http::fake([
        'gowa.test/user/my/groups' => Http::response(gowaOk(['data' => [
            ['JID' => '120363000000000001@g.us', 'Name' => 'حلقة الحفظ'],
            ['JID' => '', 'Name' => 'broken'],
        ]])),
    ]);

    expect((new WhatsAppService)->getSessionGroups('Groups Session'))->toBe([
        ['id' => '120363000000000001@g.us', 'subject' => 'حلقة الحفظ'],
    ]);
});

it('extracts attendee phones from group messages by submission type', function () {
    $service = new WhatsAppService;
    $groupJid = '120363000000000001@g.us';

    Http::fake([
        'gowa.test/group/participants*' => Http::response(gowaOk([
            'group_id' => $groupJid,
            'participants' => [
                ['jid' => '111@lid', 'phone_number' => '212611111111@s.whatsapp.net', 'lid' => '111@lid'],
            ],
        ])),
        'gowa.test/devices/*' => Http::response(gowaOk([
            'state' => 'logged_in',
            'jid' => '212600000000:12@s.whatsapp.net',
        ])),
        'gowa.test/chat/*' => Http::response(gowaOk([
            'data' => [
                ['chat_jid' => $groupJid, 'sender_jid' => '111:3@lid', 'media_type' => 'audio', 'content' => '', 'is_from_me' => false],
                ['chat_jid' => $groupJid, 'sender_jid' => '212622222222:7@s.whatsapp.net', 'media_type' => '', 'content' => 'تم بحمد الله مراجعة الحزب 1', 'is_from_me' => false],
                ['chat_jid' => $groupJid, 'sender_jid' => '212633333333@s.whatsapp.net', 'media_type' => null, 'content' => 'السلام عليكم', 'is_from_me' => false],
                ['chat_jid' => $groupJid, 'sender_jid' => '', 'media_type' => 'audio', 'content' => '', 'is_from_me' => true],
                ['chat_jid' => $groupJid, 'sender_jid' => '212644444444@s.whatsapp.net', 'media_type' => 'image', 'content' => '', 'is_from_me' => false],
            ],
            'pagination' => ['limit' => 100, 'offset' => 0, 'total' => 5],
        ])),
    ]);

    expect($service->getGroupAttendeesForDate('S', $groupJid, '2026-03-25', MessageSubmissionType::Media->whatsappMessageTypes()))
        ->toBe(['212611111111', '212600000000'])
        ->and($service->getGroupAttendeesForDate('S', $groupJid, '2026-03-25', MessageSubmissionType::Text->whatsappMessageTypes()))
        ->toBe(['212622222222'])
        ->and($service->getGroupAttendeesForDate('S', $groupJid, '2026-03-25', MessageSubmissionType::Both->whatsappMessageTypes()))
        ->toBe(['212611111111', '212622222222', '212600000000']);
});
