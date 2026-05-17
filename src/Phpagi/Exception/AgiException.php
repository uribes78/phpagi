<?php

namespace Phpagi\Exception;

class AgiException extends \RuntimeException
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        public readonly ?string $command = null,
        public readonly ?array $response = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
