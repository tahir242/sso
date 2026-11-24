<?php

namespace App\Filament\Pages\Settings;

use App\Domain\Settings\PerformanceSettings;
use App\Support\Redis\RedisRuntime;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;

class ManagePerformance extends BaseSettingsPage
{
    protected static string $settings = PerformanceSettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static ?string $navigationLabel = 'Cache, queue & Redis';

    protected static ?string $slug = 'settings/performance';

    protected static ?string $title = 'Cache, queue & Redis';

    protected static ?int $navigationSort = 40;

    protected array $secretFields = ['redis_password'];

    /** Last connection report shown in “Redis server”. */
    public ?array $probe = null;

    /** The recommended configuration for this SSO. */
    public const RECOMMENDED = [
        'redis_enabled' => true,
        'redis_client' => 'phpredis',
        'redis_scheme' => 'tcp',
        'redis_host' => '127.0.0.1',
        'redis_port' => 6379,
        'redis_database' => 0,
        'redis_cache_database' => 1,
        'redis_prefix' => 'sso:',
        'redis_timeout' => 1.0,
        'cache_store' => 'redis',
        'queue_connection' => 'redis',
        'session_store' => 'database',
        'redis_fallback' => true,
    ];

    public function mount(): void
    {
        parent::mount();

        $settings = app(PerformanceSettings::class);
        if ($settings->redis_enabled) {
            $this->probe = RedisRuntime::probe($settings->toArray());
        }
    }

    public function form(Schema $schema): Schema
    {
        $phpredis = extension_loaded('redis');

        return $schema->components([
            Section::make('Redis server')
                ->description('Redis keeps the cache and queued jobs in memory: faster sign-ins and no polling of SQL Server. If it stops, the SSO keeps working on the database (see “Fail-safe” below).')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    Toggle::make('redis_enabled')->label('Use Redis')->live()->columnSpanFull(),
                    View::make('filament.settings.redis-status')
                        ->viewData(fn () => [
                            'probe' => $this->probe,
                            'effective' => RedisRuntime::$effective,
                            'fallback' => RedisRuntime::fallbackActive(),
                        ])
                        ->columnSpanFull(),
                    TextInput::make('redis_host')->label('Host')->required()->maxLength(255)
                        ->helperText('127.0.0.1 when Redis runs on the SSO server (recommended).'),
                    TextInput::make('redis_port')->label('Port')->numeric()->minValue(1)->maxValue(65535)->required(),
                    Select::make('redis_scheme')->label('Connection')->options(['tcp' => 'Plain (same server)', 'tls' => 'TLS (remote server)'])->required()->native(false),
                    TextInput::make('redis_username')->label('Username')->maxLength(100)
                        ->helperText('Only for Redis ACL users; empty = default user.'),
                    TextInput::make('redis_password')->label('Password')->password()->revealable()
                        ->placeholder('Leave empty to keep the current password')->autocomplete('new-password'),
                    Select::make('redis_client')->label('PHP client')->native(false)->required()
                        ->options([
                            'phpredis' => 'phpredis extension'.($phpredis ? '' : ' (not installed)'),
                            'predis' => 'Predis (built in)',
                        ])
                        ->helperText($phpredis
                            ? 'phpredis is installed: it is the faster choice.'
                            : 'The phpredis extension is not loaded, so Predis is used even if phpredis is chosen. Install php_redis.dll for PHP 8.4 NTS x64 to switch.'),
                ]),
            Section::make('Where data is kept')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    Radio::make('cache_store')->label('Cache')->required()
                        ->options(['redis' => 'Redis', 'database' => 'Database', 'file' => 'Files on the server'])
                        ->descriptions([
                            'redis' => 'Recommended. Rate limits, lockouts, token and discovery caches, scheduler locks.',
                            'database' => 'Works everywhere; every cache read is a SQL query.',
                            'file' => 'Only for a single server; no shared locks.',
                        ])
                        ->disableOptionWhen(fn (string $value, Get $get) => $value === 'redis' && ! $get('redis_enabled')),
                    Radio::make('queue_connection')->label('Queued jobs')->required()
                        ->options(['redis' => 'Redis', 'database' => 'Database'])
                        ->descriptions([
                            'redis' => 'Recommended. Emails, WhatsApp, push and back-channel logouts are picked up without polling SQL Server. Needs Redis persistence (appendonly yes).',
                            'database' => 'jobs table in SQL Server.',
                        ])
                        ->disableOptionWhen(fn (string $value, Get $get) => $value === 'redis' && ! $get('redis_enabled')),
                    Radio::make('session_store')->label('Sign-in sessions')->required()
                        ->options(['database' => 'Database', 'redis' => 'Redis'])
                        ->descriptions([
                            'database' => 'Required. “Your devices” on the account page, “Sign out everywhere” and sign-out after a password change read the sessions table.',
                            'redis' => 'Not available: those security features cannot find a user’s sessions in Redis.',
                        ])
                        ->disableOptionWhen(fn (string $value) => $value === 'redis'),
                    Toggle::make('redis_fallback')->label('Fail-safe: use the database while Redis is unreachable')
                        ->helperText('Checked on every request (a sub-millisecond PING); after a failure Redis is retried every '.RedisRuntime::RETRY_SECONDS.' seconds and the error is logged. Leave on.'),
                ]),
            Section::make('Redis database numbers and key prefix')
                ->collapsed()
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('redis_database')->label('Queue database')->numeric()->minValue(0)->maxValue(15)->required(),
                    TextInput::make('redis_cache_database')->label('Cache database')->numeric()->minValue(0)->maxValue(15)->required()
                        ->different('redis_database')
                        ->helperText('Separate from the queue, so clearing the cache never drops queued jobs.'),
                    TextInput::make('redis_prefix')->label('Key prefix')->required()->maxLength(30)->regex('/^[A-Za-z0-9_:.-]+$/')
                        ->helperText('Lets other applications share this Redis server.'),
                    TextInput::make('redis_timeout')->label('Connect timeout (seconds)')->numeric()->minValue(0.1)->maxValue(10)->step(0.1)->required(),
                ]),
        ]);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = parent::mutateFormDataBeforeSave($data);

        if (! $data['redis_enabled']) {
            $data['cache_store'] = $data['cache_store'] === 'redis' ? 'database' : $data['cache_store'];
            $data['queue_connection'] = 'database';
        }

        $data['session_store'] = 'database';

        $usesRedis = $data['redis_enabled'] && ($data['cache_store'] === 'redis' || $data['queue_connection'] === 'redis');

        if ($data['redis_enabled']) {
            $this->probe = RedisRuntime::probe($data);

            if ($usesRedis && ! $this->probe['ok']) {
                Notification::make()
                    ->title('Not saved: Redis cannot be reached with these values')
                    ->body($this->probe['error'])
                    ->danger()->persistent()->send();

                throw new Halt;
            }
        }

        return $data;
    }

    protected function afterSave(): void
    {
        // Workers pick up the new connection on their next run (within a minute).
        cache()->forever('illuminate:queue:restart', now()->getTimestamp());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('testRedis')
                ->label('Test connection')
                ->icon(Heroicon::OutlinedSignal)
                ->color('gray')
                ->action(function (): void {
                    $data = $this->form->getState();
                    if (blank($data['redis_password'] ?? null)) {
                        $data['redis_password'] = app(PerformanceSettings::class)->redis_password;
                    }

                    $this->probe = RedisRuntime::probe($data);

                    $this->probe['ok']
                        ? Notification::make()->title('Connected to Redis '.$this->probe['version'])->body('Round trip '.$this->probe['latency_ms'].' ms via '.$this->probe['client'].'.')->success()->send()
                        : Notification::make()->title('Cannot reach Redis')->body($this->probe['error'])->danger()->persistent()->send();
                }),
            Action::make('recommended')
                ->label('Fill in recommended settings')
                ->icon(Heroicon::OutlinedSparkles)
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Redis on this server for the cache and queued jobs, sessions stay in the database, fail-safe on. Nothing is saved until you press Save.')
                ->action(function (): void {
                    $this->form->fill([
                        ...$this->form->getRawState(),
                        ...self::RECOMMENDED,
                        'redis_client' => RedisRuntime::client('phpredis'),
                        'redis_password' => null,
                    ]);
                    Notification::make()->title('Recommended values filled in — review and press Save.')->info()->send();
                }),
        ];
    }
}
