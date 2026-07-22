<?php

namespace Integrabr\Core\Middleware;

use Closure;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Integrabr\Core\Exceptions\TooManyRequestsException;
use Integrabr\Core\Jobs\BaseJob;
use Illuminate\Support\Facades\Cache;

class IntegrationRateLimitMiddleware
{
    private const int TOOMANYREQUESTS_STATUSCODE = 429;

    public function handle(BaseJob $job, Closure $next)
    {
        if($delay = $this->shouldThrottle($job)){
            $job->release($delay);
            return;
        }

        if(!$this->reserveSlot($job)){
            return;
        }

        try{
            return $next($job);
        }catch(\Illuminate\Http\Client\RequestException $e){
            if($e->response->tooManyRequests()){
                $retryAfter = $this->fetchRetryAfterHeader($e->response);
                $job->release($retryAfter);
            }
        }catch(RequestException $e){
            $response = $e->getResponse();
            if($response?->getStatusCode() === self::TOOMANYREQUESTS_STATUSCODE){
                $retryAfter = $this->fetchRetryAfterHeader($response);
            }
        }catch(TooManyRequestsException $e){
            $job->release($e->retryAfter ?? 10);
            return;
        }finally{
            $this->releaseSlot($job);
        }
    }

    
    /**
     * Will reserve a slot for the job and will release the job for 1 second if it couldnt reserve it.
     *
     * @return bool
     */
    private function reserveSlot(BaseJob $job): bool
    {
        $cacheKey = 'lock:'.$this->generateCacheKey($job);
        $max = $job->maxRequests;

        $current = Cache::increment($cacheKey);

        if($current > $max){
            $this->releaseSlot($job);
            $job->release(1);
            return false;
        }
        return true;
    }

    /**
     * Will release the slot for the job. Should always be called at the end of the job
     *
     * @return void
     */
    private function releaseSlot(BaseJob $job): void
    {
        $cacheKey = 'lock:'.$this->generateCacheKey($job);
        $current = Cache::decrement($cacheKey);
        if($current < 0){
            Cache::put($cacheKey, 0, now()->addSeconds(60));
        }
    }

    /**
     * Returns how many seconds the job should be throttled or null if no throttle is needed.
     *
     * @return integer|null
     */
    private function shouldThrottle(BaseJob $job): ?int
    {
        $cacheKey = $this->generateCacheKey($job);
        $data = Cache::get($cacheKey);

        if(!$data || !is_array($data)) return null;

        if(
            !array_key_exists('remaining', $data) || 
            !array_key_exists('reset', $data) ||
            !is_numeric($data['remaining']) ||
            !is_numeric($data['reset'])
        ){
            Cache::forget($cacheKey);
            return null;
        }

        if($data['remaining'] <= 1){
            $wait = $data['reset'] - time();
            return (int)max($wait, 1);
        }

        return null;
    }

    /**
     * Generates the cache key based on the base jobs informations.
     *
     * @param BaseJob $job
     * @return string
     */
    private function generateCacheKey(BaseJob $job): string
    {
        $driver = $job->driver;
        $id = $job->uniqueId;
        $cachePrefix = 'integrabr.cache';
        return $cachePrefix.':'.$driver.'-'.$id;
    }

    /**
     * Reads out the retry header to fetch the retry-at value in seconds.
     *
     * @param Response|ClientResponse|Psr7Response $response
     * @return integer
     */
    protected function fetchRetryAfterHeader(Response|ClientResponse|Psr7Response $response): int
    {
        $retryAfter = null;
        if($response instanceof Response){
            $retryAfter = $response->headers->get('Retry-After');
        }elseif($response instanceof ClientResponse){
            $retryAfter = $response->header('Retry-After');
        }elseif($response instanceof Psr7Response){
            $retryAfter = $response->getHeader('Retry-After')[0] ?? null;
        }

        if(is_null($retryAfter)){
            return 10;
        }elseif(is_numeric($retryAfter)){
            return (int)$retryAfter;
        }
        
        try{
            $retryDatetime = Carbon::parse($retryAfter);
            $retryAfter = max(0, now()->diffInSeconds($retryDatetime));
            return $retryAfter;
        }catch(\Throwable $ex){
            return 10;
        }
    }
}