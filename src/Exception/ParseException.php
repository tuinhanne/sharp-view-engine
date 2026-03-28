<?php declare(strict_types=1);

namespace Sharp\Exception;

class ParseException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $template = '',
        public readonly int $templateLine = 0,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            $template !== ''
                ? "[{$template}:{$templateLine}] {$message}"
                : $message,
            $code,
            $previous,
        );
    }
}
