<?php

declare(strict_types=1);

namespace App\Jobs\Middleware;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WhatsAppRateLimited
{
    public function __construct(
        protected int $maxPerMinute = 5,
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

        $this->hit($key);

        $next($job);
    }

    protected function tooManyAttempts(string $key): bool
    {
        return Cache::get($key, 0) >= $this->maxPerMinute;
    }

    protected function hit(string $key): void
    {
        if (! Cache::has($key)) {
            Cache::put($key, 0, $this->decaySeconds);
        }

        Cache::increment($key);
    }
}
