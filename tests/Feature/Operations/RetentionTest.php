<?php

use App\Domain\Identity\Models\User;
use App\Support\Operations\Retention;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('deletes only records past their retention period', function () {
    $user = User::factory()->create();

    DB::table('auth_events')->insert([
        ['user_id' => $user->id, 'event' => 'login.succeeded', 'created_at' => now()->subDays(Retention::AUTH_EVENT_DAYS + 1)],
        ['user_id' => $user->id, 'event' => 'login.succeeded', 'created_at' => now()->subDays(Retention::AUTH_EVENT_DAYS - 1)],
    ]);

    DB::table('trusted_devices')->insert([
        ['user_id' => $user->id, 'token_hash' => str_repeat('a', 64), 'expires_at' => now()->subMinute(), 'created_at' => now(), 'updated_at' => now()],
        ['user_id' => $user->id, 'token_hash' => str_repeat('b', 64), 'expires_at' => now()->addDay(), 'created_at' => now(), 'updated_at' => now()],
    ]);

    $notification = fn (?DateTimeInterface $readAt) => [
        'id' => (string) Str::uuid(), 'type' => 'test', 'notifiable_type' => User::class, 'notifiable_id' => $user->id,
        'data' => '{}', 'read_at' => $readAt, 'created_at' => now()->subYear(), 'updated_at' => now()->subYear(),
    ];
    DB::table('notifications')->insert([
        $notification(now()->subDays(Retention::READ_NOTIFICATION_DAYS + 1)),
        $notification(now()->subDays(Retention::READ_NOTIFICATION_DAYS - 1)),
        $notification(null), // unread: kept however old
    ]);

    $deleted = app(Retention::class)->prune();

    expect($deleted)->toMatchArray(['auth_events' => 1, 'trusted_devices' => 1, 'notifications' => 1])
        ->and(DB::table('auth_events')->count())->toBe(1)
        ->and(DB::table('trusted_devices')->count())->toBe(1)
        ->and(DB::table('notifications')->count())->toBe(2);
});

it('prunes application sessions 30 days after sign-out', function () {
    $app = makeApp('HR System');
    $user = User::factory()->create();
    $row = fn (string $sid, ?DateTimeInterface $loggedOut) => [
        'sid' => $sid, 'user_id' => $user->id, 'oauth_client_id' => $app->oauth_client_id, 'auth_time' => now(),
        'amr' => '["pwd"]', 'last_authorized_at' => now(), 'logged_out_at' => $loggedOut, 'created_at' => now(), 'updated_at' => now(),
    ];
    DB::table('client_sessions')->insert([
        $row('old', now()->subDays(Retention::CLIENT_SESSION_DAYS + 1)),
        $row('recent', now()->subDay()),
        $row('open', null),
    ]);

    $this->artisan('sso:prune')->assertSuccessful();

    expect(DB::table('client_sessions')->orderBy('sid')->pluck('sid')->all())->toBe(['open', 'recent']);
});
