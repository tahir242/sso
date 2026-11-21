<?php

namespace App\Providers\Filament;

use App\Http\Middleware\EnforceAbsoluteSessionTimeout;
use App\Http\Middleware\EnsureEmailAddress;
use App\Support\Brand;
use App\Support\Filament\InitialsAvatarProvider;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            // No Filament login page: guests are sent to the SSO's own /login.
            ->brandName(fn () => config('app.name'))
            // Logo from Settings → General (built-in mark when none is uploaded).
            ->brandLogo(fn () => view('filament.brand-logo'))
            ->brandLogoHeight('2.25rem')
            ->font('Inter')
            ->darkMode(true)
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->colors([
                'primary' => Brand::filamentPrimary(),
                'gray' => Color::Zinc,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->favicon(fn () => asset('favicon.svg'))
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            ->renderHook(PanelsRenderHook::HEAD_END, fn () => view('partials.favicons'))
            // App\Filament\Pages\Dashboard (KPIs) is discovered here.
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            // Requests = what staff ask for (access, new staff, suggestions); Helpdesk = problems.
            ->navigationGroups(['Identity', 'Requests', 'Helpdesk', 'Audit', 'Login screen', 'Settings'])
            ->navigationItems([
                NavigationItem::make('Integration guides')
                    ->url(fn () => route('docs.integration'), shouldOpenInNewTab: true)
                    ->icon(Heroicon::OutlinedBookOpen)
                    ->group('Identity')
                    ->sort(90)
                    ->visible(fn () => Gate::allows('viewApiDocs')),
                NavigationItem::make('API reference')
                    ->url(fn () => url('/docs/api'), shouldOpenInNewTab: true)
                    ->icon(Heroicon::OutlinedCodeBracket)
                    ->group('Identity')
                    ->sort(91)
                    ->visible(fn () => Gate::allows('viewApiDocs')),
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                EnforceAbsoluteSessionTimeout::class,
                EnsureEmailAddress::class,
            ])
            ->userMenuItems([
                Action::make('security')
                    ->label('My security settings')
                    ->icon(Heroicon::OutlinedShieldCheck)
                    ->url(fn () => route('account.security')),
            ]);
    }
}
