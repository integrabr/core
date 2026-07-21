<?php 

namespace Integrabr\Core\Exceptions;

use Exception;
use Throwable;

class TooManyRequestsException extends Exception
{
    public int $retryAfter;

    public function __construct(int $retryAfter, string $message = "", int $code = 0, Throwable|null $previous = null)
    {
        $this->retryAfter = $retryAfter;
        parent::__construct($message, $code, $previous);
    }
}