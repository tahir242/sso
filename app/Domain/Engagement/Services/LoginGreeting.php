<?php

namespace App\Domain\Engagement\Services;

use App\Domain\Engagement\Models\Occasion;
use App\Domain\Engagement\Models\Quote;
use App\Domain\Settings\GeneralSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Decides what the login screen's brand panel shows today:
 * an occasion greeting (Eid, 14 August, ...) for the whole day, otherwise a
 * quote that changes on every visit.
 */
final readonly class LoginGreeting
{
    private const string LAST_QUOTE = 'login.last_quote';

    public function __construct(private GeneralSettings $settings) {}

    /**
     * @return array{type: 'occasion', occasion: Occasion}|array{type: 'quote', quote: Quote}|null
     */
    public function forDate(?CarbonImmutable $date = null): ?array
    {
        if (! $this->settings->login_greetings_enabled) {
            return null;
        }

        $date ??= CarbonImmutable::now($this->settings->timezone);

        // An occasion (Eid, 14 August, ...) is decided once per day. Only its id
        // is cached (Laravel refuses to unserialize cached objects); saving or
        // deleting an Occasion/Quote calls forget().
        $occasionId = Cache::remember(self::cacheKey($date), $date->endOfDay(), fn () => $this->occasionFor($date)?->id ?? 0);

        if ($occasionId && ($occasion = Occasion::find($occasionId))) {
            return ['type' => 'occasion', 'occasion' => $occasion];
        }

        return ($quote = $this->randomQuote()) ? ['type' => 'quote', 'quote' => $quote] : null;
    }

    public function occasionFor(CarbonImmutable $date): ?Occasion
    {
        return Occasion::query()
            ->active()
            ->orderByDesc('priority')
            ->get()
            ->first(fn (Occasion $occasion) => $occasion->isCelebratedOn($date, $this->settings->hijri_offset_days));
    }

    /**
     * A different quote on every visit: picked at random, never the one this
     * browser was just shown (when there is more than one).
     */
    public function randomQuote(): ?Quote
    {
        $ids = Quote::query()->active()->pluck('id');
        $session = request()->hasSession() ? request()->session() : null;

        if ($ids->count() > 1 && ($last = $session?->get(self::LAST_QUOTE))) {
            $ids = $ids->reject(fn (int $id) => $id === $last)->values();
        }

        if ($ids->isEmpty()) {
            return null;
        }

        $quote = Quote::find($ids->random());
        $session?->put(self::LAST_QUOTE, $quote?->id);

        return $quote;
    }

    public static function forget(): void
    {
        Cache::forget(self::cacheKey(CarbonImmutable::now(config('app.timezone'))));
    }

    private static function cacheKey(CarbonImmutable $date): string
    {
        return 'login-occasion:'.$date->toDateString();
    }
}
