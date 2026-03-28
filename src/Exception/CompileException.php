<?php declare(strict_types=1);

namespace Sharp\Exception;

class CompileException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $template = '',
        public readonly int $templateLine = 0,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        $location = $template !== ''
            ? ($templateLine > 0 ? "[{$template}:{$templateLine}]" : "[{$template}]")
            : '';

        parent::__construct(
            $location !== '' ? "{$location} {$message}" : $message,
            $code,
            $previous,
        );
    }
}
