<?php 

namespace Integrabr\Core\Jobs\Shared;

use Integrabr\Core\Middleware\IntegrationRateLimitMiddleware;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Basic Job class all jobs inside this library build up on.
 */
abstract class BaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    /**
     * Maximum requests for this job at the same time.
     *
     * @var integer
     */
    public int $maxRequests = 3;

    /**
     * The driver the job is running with.
     *
     * @var string
     */
    public string $driver;

    /**
     * The unique Id the job can be tracked with.
     *
     * @var string
     */
    public string $uniqueId;

    public function middleware(): array
    {
        return [
            new IntegrationRateLimitMiddleware(),
        ];
    }
}