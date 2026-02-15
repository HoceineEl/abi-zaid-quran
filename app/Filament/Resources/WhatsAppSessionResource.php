<?php

namespace App\Filament\Resources;

use App\Classes\BaseResource;
use App\Enums\WhatsAppConnectionStatus;
use App\Filament\Actions\CheckWhatsAppStatusAction;
use App\Filament\Resources\WhatsAppSessionResource\Actions\SendMessageAction;
use App\Filament\Resources\WhatsAppSessionResource\Pages;
use App\Models\WhatsAppSession;
use App\Services\WhatsAppService;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Cache;

class WhatsAppSessionResource extends BaseResource
{
    protected static ?string $model = WhatsAppSession::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationGroup = 'التواصل';

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

            ->actions([
                Tables\Actions\ActionGroup::make([
                    self::getStartSessionAction(),
                    self::getCheckStatusAction(),
                    self::getReloadQrAction(),
                    self::getShowQrCodeAction(),
                    self::getLogoutAction(),
                    self::getDeleteSessionAction(),
                    SendMessageAction::make(),
                ])
                    ->button()->color('primary')->size('xl'),
            ], position: ActionsPosition::AfterColumns);
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
                } catch (\Exception $e) {
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
                    // Use async start - returns immediately, polling handles QR retrieval
                    $whatsappService->startSessionAsync($record);

                    $record->refresh();

                    if ($record->status === WhatsAppConnectionStatus::CONNECTED) {
                        Notification::make()
                            ->title('تم بدء الجلسة بنجاح')
                            ->body('الجلسة متصلة بالفعل')
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('جاري إعداد الجلسة...')
                            ->body('سيظهر رمز QR خلال ثوانٍ قليلة')
                            ->info()
                            ->send();
                    }
                } catch (\Exception $e) {
                    \Log::error('Failed to start session', [
                        'session_id' => $record->id,
                        'error' => $e->getMessage(),
                    ]);

                    Notification::make()
                        ->title('فشل في بدء الجلسة')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    public static function getCreateSessionAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('create_session')
            ->label('إنشاء جلسة')
            ->icon('heroicon-o-plus')
            ->color('success')
            ->visible(fn () => ! self::hasUserSession())
            ->form([
                \Filament\Forms\Components\TextInput::make('name')
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
            ->action(fn (WhatsAppSession $record) => CheckWhatsAppStatusAction::checkAndUpdateStatus($record));
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
                    } catch (\Exception $e) {
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
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('فشل في تسجيل الخروج')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    public static function getDeleteSessionAction(): TableAction
    {
        return TableAction::make('delete_session')
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
                        throw new \Exception('فشل في حذف الجلسة');
                    }
                } catch (\Exception $e) {
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
            'index' => Pages\ListWhatsAppSessions::route('/'),
        ];
    }
}
