<?php

namespace App\Providers\Filament;

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
use App\Filament\Widgets\QuranProgramStatsOverview;
use App\Filament\Widgets\UserActivityStats;
use App\Filament\Widgets\GroupProgressChart;
use App\Filament\Widgets\StudentProgressTimeline;
use Filament\View\PanelsRenderHook;
use App\Filament\Pages\SubtitleCleaner;
use App\Filament\Pages\CustomLogin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('quran-program')
            ->path('quran-program')
            ->login()
            ->colors([
                'primary' => Color::Cyan,
                'secondary' => Color::Gray,
                'danger' => Color::Rose,
                'warning' => Color::Yellow,
                'success' => Color::Teal,
                'info' => Color::Sky,
            ])
            ->login(CustomLogin::class)
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
                SubtitleCleaner::class,
            ])
            ->spa()
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                QuranProgramStatsOverview::class,
                UserActivityStats::class,
                GroupProgressChart::class,
                StudentProgressTimeline::class,
            ])
            ->topNavigation()
            ->databaseNotifications()
            ->font('Cairo')
            ->defaultThemeMode(ThemeMode::Light)
            ->brandName('مشروع حفظ القرآن الكريم')
            ->brandLogo(asset('logo.jpg'))
            ->brandLogoHeight('3.5rem')
            ->favicon(asset('logo.jpg'))
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
            ->renderHook(PanelsRenderHook::BODY_START, fn() => view('components.table-export-scripts'))
            ->renderHook(PanelsRenderHook::BODY_START, fn() => view('components.vcf-download-scripts'));
    }
}
