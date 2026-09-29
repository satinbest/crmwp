<?php

namespace App\Middleware;

use App\Support\Config;
use App\Support\Request;
use App\Support\Response;

class RateLimitMiddleware implements MiddlewareInterface
{
    private string $storageDir;
    private int $maxAttempts;
    private int $decaySeconds;

    public function __construct(int $maxAttempts = 5, int $decaySeconds = 60)
    {
        $this->maxAttempts = $maxAttempts ?: (int)Config::get('auth.rate_limit.max_attempts', 5);
        $this->decaySeconds = $decaySeconds ?: (int)Config::get('auth.rate_limit.decay_seconds', 60);
        $this->storageDir = dirname(__DIR__, 2) . '/storage/framework/rate_limits';

        if (!is_dir($this->storageDir)) {
            @mkdir($this->storageDir, 0775, true);
        }
    }

    public static function with(int $maxAttempts, int $decaySeconds): callable
    {
        return function (Request $req, callable $next) use ($maxAttempts, $decaySeconds): Response {
            $middleware = new self($maxAttempts, $decaySeconds);
            return $middleware->handle($req, $next);
        };
    }

    public function handle(Request $request, callable $next): Response
    {
        $ip = $request->getIp();
        $action = md5($request->getPath() . '|' . $ip);
        $cacheFile = "{$this->storageDir}/rl_{$action}.json";

        $now = time();
        $data = ['count' => 0, 'first_attempt' => $now];

        if (file_exists($cacheFile)) {
            $content = @file_get_contents($cacheFile);
            if ($content) {
                $decoded = json_decode($content, true);
                if (is_array($decoded) && isset($decoded['first_attempt'])) {
                    if (($now - $decoded['first_attempt']) < $this->decaySeconds) {
                        $data = $decoded;
                    }
                }
            }
        }

        if ($data['count'] >= $this->maxAttempts) {
            $retryAfter = $this->decaySeconds - ($now - $data['first_attempt']);
            return Response::error(
                'TOO_MANY_REQUESTS',
                'تعداد تلاش‌های ناموفق بیش از حد مجاز است. لطفاً کمی بعد دوباره امتحان کنید.',
                ['retry_after_seconds' => max(1, $retryAfter)],
                429
            )->header('Retry-After', (string)max(1, $retryAfter));
        }

        $data['count']++;
        @file_put_contents($cacheFile, json_encode($data), LOCK_EX);

        return $next($request);
    }
}
