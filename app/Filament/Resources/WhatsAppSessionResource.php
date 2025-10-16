<?php

namespace App\Filament\Resources;

use App\Classes\BaseResource;
use App\Enums\WhatsAppConnectionStatus;
use App\Filament\Resources\WhatsAppSessionResource\Actions\SendMessageAction;
use App\Filament\Resources\WhatsAppSessionResource\Pages\ListWhatsAppSessions;
use App\Models\WhatsAppSession;
use App\Services\WhatsAppService;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Cache;
use Log;

class WhatsAppSessionResource extends BaseResource
{
    protected static ?string $model = WhatsAppSession::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static string|\UnitEnum|null $navigationGroup = 'التواصل';

    protected static ?int $navigationSort = 10;

    public static function getModelLabel(): string
    {
        return 'جلسة واتساب';
    }

    public static function getPluralModelLabel(): string
    {
        return 'جلسات واتساب';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->forUser(auth()->id()))
            ->columns([
                ViewColumn::make('details')
                    ->label('جلسة واتساب')
                    ->searchable(false)
                    ->sortable(false)
                    ->view('filament.resources.whatsapp-session-resource.session-details'),
            ])

            ->recordActions([
                ActionGroup::make([
                    self::getStartSessionAction(),
                    self::getCheckStatusAction(),
                    self::getReloadQrAction(),
                    self::getShowQrCodeAction(),
                    self::getLogoutAction(),
                    self::getDeleteSessionAction(),
                    SendMessageAction::make(),
                ])
                    ->button()->color('primary')->size('xl'),
            ], position: RecordActionsPosition::AfterColumns);
    }

    public static function getReloadQrAction(): Action
    {
        return Action::make('reload_qr')
            ->label('إعادة تحميل رمز QR')
            ->icon('heroicon-o-arrow-path')
            ->color('info')
            ->visible(fn (WhatsAppSession $record) => $record->status->canReloadQr() || $record->status->shouldShowQrCode())
            ->action(function (WhatsAppSession $record) {
                try {
                    $whatsappService = app(WhatsAppService::class);
                    $result = $whatsappService->refreshQrCode($record);

                    // Check if we got a QR code
                    if (! empty($record->fresh()->qr_code)) {
                        Notification::make()
                            ->title('تم تحديث رمز QR بنجاح')
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('تم التحديث')
                            ->body('لا يوجد رمز QR متاح حالياً. الحالة: '.$record->fresh()->status->getLabel())
                            ->warning()
                            ->send();
                    }
                } catch (Exception $e) {
                    Notification::make()
                        ->title('فشل في تحديث رمز QR')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    public static function getStartSessionAction(): Action
    {
        return Action::make('start_session')
            ->label('بدء الجلسة')
            ->icon('heroicon-o-play')
            ->color('success')
            ->visible(fn (WhatsAppSession $record) => $record->status->canStartSession())
            ->action(function (WhatsAppSession $record) {
                $whatsappService = app(WhatsAppService::class);

                try {
                    // First check if session already exists
                    $result = $whatsappService->getSessionStatus($record->id);
                    $apiStatus = strtoupper($result['status'] ?? 'PENDING');

                    Log::info('Start session - existing session found', [
                        'session_id' => $record->id,
                        'api_status' => $apiStatus,
                        'has_qr' => ! empty($result['qr']),
                    ]);

                    if ($apiStatus === 'CONNECTED') {
                        $record->markAsConnected($result);

                        Notification::make()
                            ->title('تم بدء الجلسة بنجاح')
                            ->body('الجلسة متصلة بالفعل')
                            ->success()
                            ->send();
                    } else {
                        $modelStatus = WhatsAppConnectionStatus::fromApiStatus($apiStatus);
                        $record->update([
                            'status' => $modelStatus,
                            'session_data' => $result,
                            'last_activity_at' => now(),
                        ]);

                        if (! empty($result['qr'])) {
                            $record->updateQrCode($result['qr']);
                        }

                        Notification::make()
                            ->title('تم بدء الجلسة بنجاح')
                            ->body('يرجى مسح رمز QR')
                            ->success()
                            ->send();
                    }
                } catch (Exception $e) {
                    Log::info('Start session - creating new session', [
                        'session_id' => $record->id,
                        'error' => $e->getMessage(),
                    ]);

                    try {
                        $result = $whatsappService->startSession($record);

                        Log::info('New session created', [
                            'session_id' => $record->id,
                            'status' => $result['status'] ?? 'unknown',
                            'has_qr' => ! empty($result['qr']),
                        ]);

                        Notification::make()
                            ->title('تم بدء الجلسة بنجاح')
                            ->body('يرجى مسح رمز QR')
                            ->success()
                            ->send();
                    } catch (Exception $ex) {
                        Log::error('Failed to start session', [
                            'session_id' => $record->id,
                            'error' => $ex->getMessage(),
                        ]);

                        Notification::make()
                            ->title('فشل في بدء الجلسة')
                            ->body($ex->getMessage())
                            ->danger()
                            ->send();
                    }
                }
            });
    }

    public static function getCreateSessionAction(): Action
    {
        return Action::make('create_session')
            ->label('إنشاء جلسة')
            ->icon('heroicon-o-plus')
            ->color('success')
            ->visible(fn () => ! self::hasUserSession())
            ->schema([
                TextInput::make('name')
                    ->label('اسم الجلسة')
                    ->required()
                    ->default(fn () => 'جلسة واتساب - '.auth()->user()->name)
                    ->maxLength(255),
            ])
            ->action(function (array $data) {
                // Delete all existing sessions for this user with cascade
                WhatsAppSession::where('user_id', auth()->id())->delete();

                $session = WhatsAppSession::create([
                    'user_id' => auth()->id(),
                    'name' => $data['name'],
                    'status' => WhatsAppConnectionStatus::DISCONNECTED,
                ]);

                Notification::make()
                    ->title('تم إنشاء الجلسة بنجاح')
                    ->body('يمكنك الآن بدء جلسة واتساب الخاصة بك')
                    ->success()
                    ->send();
            });
    }

    protected static function hasUserSession(): bool
    {
        return WhatsAppSession::getUserSession(auth()->id()) !== null;
    }

    public static function getCheckStatusAction(): Action
    {
        return Action::make('check_status')
            ->label('فحص الحالة')
            ->icon('heroicon-o-arrow-path')
            ->color('info')
            ->action(function (WhatsAppSession $record) {
                try {
                    $whatsappService = app(WhatsAppService::class);
                    $result = $whatsappService->getSessionStatus($record->id);

                    $apiStatus = strtoupper($result['status'] ?? 'DISCONNECTED');
                    $oldStatus = $record->status;

                    // Map API status to model status using the enum
                    $modelStatus = WhatsAppConnectionStatus::fromApiStatus($apiStatus);

                    // Update record based on status
                    if ($apiStatus === 'CONNECTED') {
                        $record->markAsConnected($result);
                    } elseif ($apiStatus === 'DISCONNECTED') {
                        $record->markAsDisconnected();

                        if (isset($result['detail']) && str_contains($result['detail'], 'not found')) {
                            Notification::make()
                                ->title('الجلسة غير موجودة')
                                ->body('الجلسة غير موجودة على خادم API')
                                ->warning()
                                ->send();

                            return;
                        }
                    } else {
                        // For CREATING, CONNECTING, PENDING, GENERATING_QR
                        $record->update([
                            'status' => $modelStatus,
                            'session_data' => $result,
                            'last_activity_at' => now(),
                        ]);

                        // Update QR code if provided
                        if (isset($result['qr']) && ! empty($result['qr'])) {
                            $record->updateQrCode($result['qr']);
                        }

                        // Update cached token if available
                        if (isset($result['token']) && ! empty($result['token'])) {
                            Cache::put("whatsapp_token_{$record->id}", $result['token'], now()->addHours(24));
                        }
                    }

                    $statusLabel = $modelStatus->getLabel();

                    Notification::make()
                        ->title('تم تحديث الحالة')
                        ->body("الحالة الحالية: {$statusLabel}")
                        ->success()
                        ->send();
                } catch (Exception $e) {
                    Notification::make()
                        ->title('فشل في فحص الحالة')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    public static function getShowQrCodeAction(): Action
    {
        return Action::make('show_qr')
            ->label('عرض رمز QR')
            ->icon('heroicon-o-qr-code')
            ->color('info')
            ->visible(fn (WhatsAppSession $record) => $record->status->canShowQrCode())
            ->modalContent(function (WhatsAppSession $record) {
                // Try to refresh QR code if needed
                if ($record->status->canShowQrCode() && ! $record->qr_code) {
                    try {
                        $whatsappService = app(WhatsAppService::class);
                        $whatsappService->refreshQrCode($record);
                        $record->refresh(); // Reload the model
                    } catch (Exception $e) {
                        // Silent fail, will show no QR available message
                    }
                }

                return view('filament.resources.whatsapp-session-resource.qr-code', [
                    'qrCode' => $record->getQrCodeData(),
                    'sessionName' => $record->name,
                    'sessionId' => $record->id,
                    'status' => $record->status,
                ]);
            })
            ->modalHeading('مسح رمز QR')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('إغلاق');
    }

    public static function getLogoutAction(): Action
    {
        return Action::make('logout')
            ->label('تسجيل الخروج')
            ->icon('heroicon-o-arrow-right-on-rectangle')
            ->color('danger')
            ->visible(fn (WhatsAppSession $record) => $record->status->canLogout())
            ->requiresConfirmation()
            ->action(function (WhatsAppSession $record) {
                try {
                    $whatsappService = app(WhatsAppService::class);
                    $result = $whatsappService->logout($record);

                    $record->markAsDisconnected();

                    Notification::make()
                        ->title('تم تسجيل الخروج بنجاح')
                        ->success()
                        ->send();
                } catch (Exception $e) {
                    Notification::make()
                        ->title('فشل في تسجيل الخروج')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    public static function getDeleteSessionAction(): Action
    {
        return Action::make('delete_session')
            ->label('حذف الجلسة')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('حذف جلسة واتساب')
            ->modalDescription('هل أنت متأكد من رغبتك في حذف هذه الجلسة؟ سيتم حذف الجلسة وجميع الرسائل المرتبطة بها.')
            ->modalSubmitActionLabel('نعم، احذف الجلسة')
            ->modalCancelActionLabel('إلغاء')
            ->action(function (WhatsAppSession $record) {
                try {
                    $success = $record->delete();

                    if ($success) {
                        Notification::make()
                            ->title('تم حذف الجلسة بنجاح')
                            ->body('تم حذف الجلسة وجميع الرسائل المرتبطة بها')
                            ->success()
                            ->send();
                    } else {
                        throw new Exception('فشل في حذف الجلسة');
                    }
                } catch (Exception $e) {
                    Notification::make()
                        ->title('فشل في حذف الجلسة')
                        ->body('حدث خطأ أثناء حذف الجلسة: '.$e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWhatsAppSessions::route('/'),
        ];
    }
}
