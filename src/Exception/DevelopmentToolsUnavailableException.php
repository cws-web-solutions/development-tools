<?php declare(strict_types=1);

namespace Cws\DevelopmentTools\Exception;

final class DevelopmentToolsUnavailableException extends \RuntimeException
{
    public static function becauseEnvironmentIsNotDev(string $environment): self
    {
        return new self(\sprintf(
            'CwsDevelopmentTools maintenance actions are only available in the "dev" environment. Current environment: "%s".',
            $environment
        ));
    }
}
