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
use Filament\View\PanelsRenderHook;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use App\Filament\Association\Widgets\AssociationStatsOverview;
use App\Filament\Association\Widgets\DetailedStatsOverview;
use App\Filament\Association\Widgets\AttendanceChart;
use App\Filament\Association\Widgets\GroupsStatsChart;
use App\Filament\Association\Widgets\PaymentsChart;
use App\Filament\Pages\CustomLogin;

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
            ->login(CustomLogin::class)
            ->viteTheme('resources/css/filament/association/theme.css')
            ->defaultThemeMode(ThemeMode::Light)
            ->databaseNotifications()
            ->databaseNotificationsPolling("10s")
            ->brandName('مدرسة إبن أبي زيد القيرواني')
            ->brandLogo(asset('logo.jpg'))
            ->brandLogoHeight('3.5rem')
            ->spa()
            ->favicon(asset('logo.jpg'))
            ->discoverResources(in: app_path('Filament/Association/Resources'), for: 'App\\Filament\\Association\\Resources')
            ->discoverPages(in: app_path('Filament/Association/Pages'), for: 'App\\Filament\\Association\\Pages')
            ->pages([
                Pages\Dashboard::class,
                ScanAttendance::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Association/Widgets'), for: 'App\\Filament\\Association\\Widgets')
            ->widgets([
                AssociationStatsOverview::class,
                DetailedStatsOverview::class,
                AttendanceChart::class,
                GroupsStatsChart::class,
                PaymentsChart::class,
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
            ])
            ->renderHook(PanelsRenderHook::BODY_START, fn() => view('components.attendance-export-scripts'));
    }
}
