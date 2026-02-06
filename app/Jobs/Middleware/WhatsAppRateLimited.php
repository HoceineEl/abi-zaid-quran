<?php

declare(strict_types=1);

namespace App\Jobs\Middleware;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WhatsAppRateLimited
{
    public function __construct(
        protected int $maxPerMinute = 3,
        protected int $decaySeconds = 60
    ) {}

    public function handle(object $job, Closure $next): void
    {
        $key = "whatsapp_rate_limit:{$job->sessionId}";

        if ($this->tooManyAttempts($key)) {
            Log::info('WhatsApp message rate limited, releasing back to queue', [
                'session_id' => $job->sessionId,
                'to' => $job->to,
            ]);

            $job->release($this->decaySeconds);

            return;
        }

        $randomDelay = rand(
            config('whatsapp.delay_min', 10),
            config('whatsapp.delay_max', 30),
        );

        Log::debug('WhatsApp adding random delay before send', [
            'session_id' => $job->sessionId,
            'delay_seconds' => $randomDelay,
        ]);

        sleep($randomDelay);

        $this->hit($key);

        $next($job);
    }

    protected function tooManyAttempts(string $key): bool
    {
        return Cache::get($key, 0) >= $this->maxPerMinute;
    }

    protected function hit(string $key): int
    {
        Cache::add($key, 0, $this->decaySeconds);

        return Cache::increment($key);
    }
}
