<?php

declare(strict_types=1);

namespace Cws\DevelopmentTools\Service;

use Shopware\Core\System\SystemConfig\SystemConfigService;

final class DevelopmentToolsInfoService
{
    public const MEDIA_FALLBACK_CONFIG = 'CwsDevelopmentTools.config.MediaUrlResolverHostReplace';
    public const MEDIA_FALLBACK_ENABLED_CONFIG = 'CwsDevelopmentTools.config.MediaUrlResolverEnabled';
    public const STOREFRONT_TOOLBAR_VISIBLE_CONFIG = 'CwsDevelopmentTools.config.StorefrontToolbarVisible';
    private const LEGACY_MEDIA_FALLBACK_CONFIG = 'DisMediaUrlResolverLocalDevelopment.config.MediaUrlResolverHostReplace';

    private string $environment;

    private string $projectDir;

    private SystemConfigService $systemConfigService;

    public function __construct(
        string $environment,
        string $projectDir,
        SystemConfigService $systemConfigService,
        private readonly SystemHealthService $systemHealthService
    ) {
        $this->environment = $environment;
        $this->projectDir = $projectDir;
        $this->systemConfigService = $systemConfigService;
    }

    /**
     * @return array{
     *     environment: string,
     *     maintenance: array{available: bool},
     *     mediaFallback: array{
     *         enabled: bool,
     *         configured: bool,
     *         active: bool,
     *         host: ?string,
     *         source: string,
     *         configKey: string,
     *         enabledConfigKey: string,
     *         legacyConfigKey: string,
     *         hostScope: string
     *     },
     *     storefrontToolbar: array{visible: bool, configKey: string, scope: string},
     *     systemStatus: array<string, mixed>,
     *     healthChecks: array<int, array{id: string, status: string, name: string, current: string, recommended: string}>,
     *     documentation: array<string, mixed>
     * }
     */
    public function getState(): array
    {
        $configuredHost = $this->normalizeString($this->systemConfigService->get(self::MEDIA_FALLBACK_CONFIG));
        $legacyHost = $this->normalizeString($this->systemConfigService->get(self::LEGACY_MEDIA_FALLBACK_CONFIG));
        $mediaFallbackHost = $configuredHost ?? $legacyHost;
        $mediaFallbackConfigured = $mediaFallbackHost !== null;
        $mediaFallbackEnabled = $this->resolveMediaFallbackEnabled($mediaFallbackConfigured);
        $mediaFallbackSource = $configuredHost !== null
            ? 'system-config'
            : ($legacyHost !== null ? 'legacy-system-config' : 'missing');

        return [
            'environment' => $this->environment,
            'maintenance' => [
                'available' => $this->environment === 'dev',
                'themeCompileAvailable' => true,
                'opcacheAvailable' => true,
            ],
            'mediaFallback' => [
                'enabled' => $mediaFallbackEnabled,
                'configured' => $mediaFallbackConfigured,
                'active' => $mediaFallbackConfigured && $mediaFallbackEnabled && $this->environment === 'dev',
                'host' => $mediaFallbackHost,
                'source' => $mediaFallbackSource,
                'configKey' => self::MEDIA_FALLBACK_CONFIG,
                'enabledConfigKey' => self::MEDIA_FALLBACK_ENABLED_CONFIG,
                'legacyConfigKey' => self::LEGACY_MEDIA_FALLBACK_CONFIG,
                'hostScope' => 'APP_ENV=dev',
            ],
            'storefrontToolbar' => [
                'visible' => $this->resolveStorefrontToolbarVisible(),
                'configKey' => self::STOREFRONT_TOOLBAR_VISIBLE_CONFIG,
                'scope' => 'APP_ENV=dev',
            ],
            'systemStatus' => $this->getSystemStatus(),
            'healthChecks' => $this->systemHealthService->collect(),
            'documentation' => $this->loadDocumentation(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function saveMediaFallback(?string $host, bool $enabled): array
    {
        $normalizedHost = $this->normalizeString($host);

        $this->systemConfigService->set(self::MEDIA_FALLBACK_CONFIG, $normalizedHost);
        $this->systemConfigService->set(self::LEGACY_MEDIA_FALLBACK_CONFIG, $normalizedHost);
        $this->systemConfigService->set(self::MEDIA_FALLBACK_ENABLED_CONFIG, $enabled);

        return $this->getState();
    }

    /**
     * @return array<string, mixed>
     */
    public function saveStorefrontToolbar(bool $visible): array
    {
        $this->systemConfigService->set(self::STOREFRONT_TOOLBAR_VISIBLE_CONFIG, $visible);

        return $this->getState();
    }

    /**
     * @return array<string, mixed>
     */
    private function getSystemStatus(): array
    {
        $opcacheEnabled = \function_exists('opcache_get_status') && \is_array(@opcache_get_status(false));

        return [
            'pluginVersion' => $this->getPluginVersion(),
            'environment' => $this->environment,
            'debug' => filter_var($_SERVER['APP_DEBUG'] ?? false, \FILTER_VALIDATE_BOOLEAN),
            'phpVersion' => \PHP_VERSION,
            'phpSapi' => \PHP_SAPI,
            'operatingSystem' => \PHP_OS_FAMILY,
            'timezone' => date_default_timezone_get(),
            'memoryLimit' => (string) ini_get('memory_limit'),
            'maxExecutionTime' => (string) ini_get('max_execution_time'),
            'opcacheLoaded' => \extension_loaded('Zend OPcache'),
            'opcacheEnabled' => $opcacheEnabled,
            'apcuLoaded' => \extension_loaded('apcu'),
            'xdebugLoaded' => \extension_loaded('xdebug'),
            'projectDir' => $this->projectDir,
        ];
    }

    private function getPluginVersion(): string
    {
        $composerPath = dirname(__DIR__) . '/../composer.json';
        if (!is_file($composerPath) || !is_readable($composerPath)) {
            return '-';
        }

        $contents = file_get_contents($composerPath);
        if ($contents === false) {
            return '-';
        }

        try {
            $decoded = json_decode($contents, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return '-';
        }

        return \is_string($decoded['version'] ?? null) ? $decoded['version'] : '-';
    }

    /**
     * @return array<string, mixed>
     */
    private function loadDocumentation(): array
    {
        $documentationPath = dirname(__DIR__) . '/Resources/config/documentation.json';
        if (!is_file($documentationPath) || !is_readable($documentationPath)) {
            return [
                'title' => 'CWS Development Tools',
                'features' => [],
            ];
        }

        $contents = file_get_contents($documentationPath);
        if ($contents === false) {
            return [
                'title' => 'CWS Development Tools',
                'features' => [],
            ];
        }

        try {
            $decoded = json_decode($contents, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [
                'title' => 'CWS Development Tools',
                'features' => [],
            ];
        }

        return \is_array($decoded) ? $decoded : [
            'title' => 'CWS Development Tools',
            'features' => [],
        ];
    }

    private function normalizeString(mixed $value): ?string
    {
        if (!\is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : rtrim($value, '/');
    }

    private function resolveMediaFallbackEnabled(bool $mediaFallbackConfigured): bool
    {
        $configuredValue = $this->normalizeBoolean($this->systemConfigService->get(self::MEDIA_FALLBACK_ENABLED_CONFIG));
        if ($configuredValue !== null) {
            return $configuredValue;
        }

        return $mediaFallbackConfigured;
    }

    private function resolveStorefrontToolbarVisible(): bool
    {
        return $this->normalizeBoolean($this->systemConfigService->get(self::STOREFRONT_TOOLBAR_VISIBLE_CONFIG)) ?? true;
    }

    private function normalizeBoolean(mixed $value): ?bool
    {
        if (\is_bool($value)) {
            return $value;
        }

        if (\is_scalar($value)) {
            return filter_var($value, \FILTER_VALIDATE_BOOLEAN, \FILTER_NULL_ON_FAILURE);
        }

        return null;
    }
}
