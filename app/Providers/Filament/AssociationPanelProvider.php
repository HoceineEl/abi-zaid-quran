<?php

namespace App\Providers\Filament;

use App\Filament\Pages\ScanAttendance;
use Filament\Enums\ThemeMode;
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

class AssociationPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('association')
            ->path('association')
            ->colors([
                'primary' => Color::Emerald,
                'indigo' => Color::Indigo,
            ])
            ->topNavigation()
            ->font('Cairo')
            ->login()
            ->viteTheme('resources/css/filament/association/theme.css')
            ->defaultThemeMode(ThemeMode::Light)
            ->databaseNotifications()
            ->databaseNotificationsPolling("10s")
            ->brandName('مدرسة إبن أبي زيد القيرواني')
            ->brandLogo(asset('logo.jpg'))
            ->brandLogoHeight('3.5rem')
            ->favicon(asset('logo.jpg'))
            ->discoverResources(in: app_path('Filament/Association/Resources'), for: 'App\\Filament\\Association\\Resources')
            ->discoverPages(in: app_path('Filament/Association/Pages'), for: 'App\\Filament\\Association\\Pages')
            ->pages([
                Pages\Dashboard::class,
                ScanAttendance::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Association/Widgets'), for: 'App\\Filament\\Association\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
            ])
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
            ]);
    }
}