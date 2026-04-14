<?php

namespace App\Providers;

use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Enums\Alignment;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\Columns\Column;
use Filament\View\PanelsRenderHook;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use LaravelPWA\Services\ManifestService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn() => view('laravelpwa::meta', ['config' => (new ManifestService)->generate()])
        );


        Page::formActionsAlignment(Alignment::Right);
        Action::configureUsing(function (Action $action) {
            $action->modalFooterActionsAlignment(Alignment::Right)
                ->modalSubmitActionLabel(__('حفظ'))
            ;
        });

        Column::configureUsing(function (Column $column) {
            $column->toggleable();
        });

        DB::prohibitDestructiveCommands(app()->environment('production'));

        Model::unguard();
    }
}
