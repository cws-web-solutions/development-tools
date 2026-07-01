<?php

declare(strict_types=1);

namespace Cws\DevelopmentTools\Service;

use Doctrine\DBAL\Connection;

final class SystemHealthService
{
    private const STATUS_OK = 'ok';
    private const STATUS_WARNING = 'warning';
    private const STATUS_ERROR = 'error';
    private const STATUS_INFO = 'info';

    public function __construct(
        private readonly Connection $connection,
        private readonly string $environment
    ) {
    }

    /**
     * @return array<int, array{id: string, status: string, name: string, current: string, recommended: string}>
     */
    public function collect(): array
    {
        $checks = [
            $this->checkEnvironment(),
            $this->checkDebugMode(),
            $this->checkPhpVersion(),
            $this->checkMemoryLimit(),
            $this->checkOpcache(),
            $this->checkPcreJit(),
            $this->checkExtension('intl', 'PHP Intl extension'),
            $this->checkExtension('sodium', 'PHP Sodium extension'),
            $this->checkExtension('curl', 'PHP cURL extension'),
            $this->checkMysqlVersion(),
            $this->checkMysqlTimezone(),
            $this->checkQueue(),
            $this->checkScheduledTasks(),
        ];

        usort($checks, static function (array $left, array $right): int {
            $priority = [
                self::STATUS_ERROR => 0,
                self::STATUS_WARNING => 1,
                self::STATUS_INFO => 2,
                self::STATUS_OK => 3,
            ];

            return ($priority[$left['status']] ?? 4) <=> ($priority[$right['status']] ?? 4);
        });

        return $checks;
    }

    /**
     * @return array{id: string, status: string, name: string, current: string, recommended: string}
     */
    private function checkEnvironment(): array
    {
        return $this->result(
            'environment',
            self::STATUS_OK,
            'Application environment',
            $this->environment,
            'dev for development, prod for production'
        );
    }

    /**
     * @return array{id: string, status: string, name: string, current: string, recommended: string}
     */
    private function checkDebugMode(): array
    {
        $enabled = filter_var($_SERVER['APP_DEBUG'] ?? false, \FILTER_VALIDATE_BOOLEAN);
        $expected = $this->environment === 'dev';

        return $this->result(
            'debug-mode',
            $enabled === $expected ? self::STATUS_OK : self::STATUS_WARNING,
            'Debug mode',
            $enabled ? 'enabled' : 'disabled',
            $expected ? 'enabled in dev' : 'disabled in prod'
        );
    }

    /**
     * @return array{id: string, status: string, name: string, current: string, recommended: string}
     */
    private function checkPhpVersion(): array
    {
        $minimum = '8.2.0';

        return $this->result(
            'php-version',
            version_compare(\PHP_VERSION, $minimum, '>=') ? self::STATUS_OK : self::STATUS_ERROR,
            'PHP version',
            \PHP_VERSION,
            sprintf('%s or newer', $minimum)
        );
    }

    /**
     * @return array{id: string, status: string, name: string, current: string, recommended: string}
     */
    private function checkMemoryLimit(): array
    {
        $current = (string) ini_get('memory_limit');
        $bytes = $this->convertToBytes($current);
        $recommended = 512 * 1024 * 1024;

        return $this->result(
            'memory-limit',
            $bytes === -1 || $bytes >= $recommended ? self::STATUS_OK : self::STATUS_WARNING,
            'PHP memory limit',
            $current,
            '512M or more'
        );
    }

    /**
     * @return array{id: string, status: string, name: string, current: string, recommended: string}
     */
    private function checkOpcache(): array
    {
        $loaded = \extension_loaded('Zend OPcache');
        $enabled = $loaded && filter_var(ini_get('opcache.enable'), \FILTER_VALIDATE_BOOLEAN);

        return $this->result(
            'opcache',
            $enabled ? self::STATUS_OK : self::STATUS_WARNING,
            'PHP OPcache',
            $enabled ? 'enabled' : ($loaded ? 'loaded, but disabled' : 'not loaded'),
            'enabled'
        );
    }

    /**
     * @return array{id: string, status: string, name: string, current: string, recommended: string}
     */
    private function checkPcreJit(): array
    {
        $enabled = filter_var(ini_get('pcre.jit'), \FILTER_VALIDATE_BOOLEAN);

        return $this->result(
            'pcre-jit',
            $enabled ? self::STATUS_OK : self::STATUS_WARNING,
            'PCRE JIT',
            $enabled ? 'enabled' : 'disabled',
            'enabled'
        );
    }

    /**
     * @return array{id: string, status: string, name: string, current: string, recommended: string}
     */
    private function checkExtension(string $extension, string $name): array
    {
        $loaded = \extension_loaded($extension);

        return $this->result(
            'php-extension-' . $extension,
            $loaded ? self::STATUS_OK : self::STATUS_ERROR,
            $name,
            $loaded ? 'loaded' : 'missing',
            'loaded'
        );
    }

    /**
     * @return array{id: string, status: string, name: string, current: string, recommended: string}
     */
    private function checkMysqlVersion(): array
    {
        try {
            $version = (string) $this->connection->fetchOne('SELECT VERSION()');
            $normalized = preg_replace('/[^0-9.].*$/', '', $version) ?: $version;
            $supported = version_compare($normalized, '8.0.17', '>=')
                || (stripos($version, 'mariadb') !== false && version_compare($normalized, '10.11.0', '>='));

            return $this->result(
                'mysql-version',
                $supported ? self::STATUS_OK : self::STATUS_WARNING,
                'Database version',
                $version,
                'MySQL 8.0.17+ or MariaDB 10.11+'
            );
        } catch (\Throwable $exception) {
            return $this->failedResult('mysql-version', 'Database version', $exception);
        }
    }

    /**
     * @return array{id: string, status: string, name: string, current: string, recommended: string}
     */
    private function checkMysqlTimezone(): array
    {
        try {
            $timezone = (string) $this->connection->fetchOne('SELECT @@session.time_zone');

            return $this->result(
                'mysql-timezone',
                self::STATUS_OK,
                'Database timezone',
                $timezone,
                'Configured and consistent across workers'
            );
        } catch (\Throwable $exception) {
            return $this->failedResult('mysql-timezone', 'Database timezone', $exception);
        }
    }

    /**
     * @return array{id: string, status: string, name: string, current: string, recommended: string}
     */
    private function checkQueue(): array
    {
        try {
            $count = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM messenger_messages');
            $status = $count > 1000 ? self::STATUS_WARNING : self::STATUS_OK;

            return $this->result(
                'message-queue',
                $status,
                'Message queue',
                sprintf('%d queued message(s)', $count),
                'Below 1000 queued messages'
            );
        } catch (\Throwable $exception) {
            return $this->failedResult('message-queue', 'Message queue', $exception);
        }
    }

    /**
     * @return array{id: string, status: string, name: string, current: string, recommended: string}
     */
    private function checkScheduledTasks(): array
    {
        try {
            $count = (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM scheduled_task
                 WHERE status = :status AND next_execution_time < :threshold',
                [
                    'status' => 'scheduled',
                    'threshold' => (new \DateTimeImmutable('-10 minutes'))->format('Y-m-d H:i:s.v'),
                ]
            );

            return $this->result(
                'scheduled-tasks',
                $count === 0 ? self::STATUS_OK : self::STATUS_WARNING,
                'Scheduled tasks',
                sprintf('%d overdue task(s)', $count),
                'No task overdue by more than 10 minutes'
            );
        } catch (\Throwable $exception) {
            return $this->failedResult('scheduled-tasks', 'Scheduled tasks', $exception);
        }
    }

    /**
     * @return array{id: string, status: string, name: string, current: string, recommended: string}
     */
    private function failedResult(string $id, string $name, \Throwable $exception): array
    {
        return $this->result(
            $id,
            self::STATUS_INFO,
            $name,
            'Check unavailable',
            sprintf('Verify database access (%s)', $exception::class)
        );
    }

    /**
     * @return array{id: string, status: string, name: string, current: string, recommended: string}
     */
    private function result(string $id, string $status, string $name, string $current, string $recommended): array
    {
        return compact('id', 'status', 'name', 'current', 'recommended');
    }

    private function convertToBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '-1') {
            return -1;
        }

        $number = (int) $value;
        $unit = strtolower(substr($value, -1));

        return match ($unit) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
