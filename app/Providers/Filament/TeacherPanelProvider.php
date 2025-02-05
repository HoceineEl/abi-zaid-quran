<?php

namespace App\Providers\Filament;

use App\Filament\Association\Resources\GroupResource;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use App\Filament\Pages\CustomLogin;
use Filament\Enums\ThemeMode;
use Filament\View\PanelsRenderHook;

class TeacherPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('teacher')
            ->path('teacher')
            ->colors([
                'primary' => Color::Purple,
            ])
            ->discoverResources(in: app_path('Filament/Teacher/Resources'), for: 'App\\Filament\\Teacher\\Resources')
            ->discoverPages(in: app_path('Filament/Teacher/Pages'), for: 'App\\Filament\\Teacher\\Pages')
            ->colors([
                'primary' => Color::Emerald,
                'indigo' => Color::Indigo,
            ])
            ->font('Cairo')
            ->viteTheme('resources/css/filament/association/theme.css')
            ->defaultThemeMode(ThemeMode::Light)
            ->databaseNotifications()
            ->databaseNotificationsPolling("10s")
            ->brandName('مدرسة إبن أبي زيد القيرواني')
            ->brandLogo(asset('logo.jpg'))
            ->brandLogoHeight('3.5rem')
            ->favicon(asset('logo.jpg'))
            ->login(CustomLogin::class)
            ->spa()
            ->resources([
                GroupResource::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Teacher/Widgets'), for: 'App\\Filament\\Teacher\\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->renderHook(
                PanelsRenderHook::BODY_START,

                fn() => view('components.attendance-export-scripts')
            );
    }
}
