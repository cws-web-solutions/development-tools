<?php declare(strict_types=1);

namespace Cws\DevelopmentTools\Service;

use Shopware\Core\System\SystemConfig\SystemConfigService;

final class DevelopmentToolsInfoService
{
    public const MEDIA_FALLBACK_CONFIG = 'CwsDevelopmentTools.config.MediaUrlResolverHostReplace';
    private const LEGACY_MEDIA_FALLBACK_CONFIG = 'DisMediaUrlResolverLocalDevelopment.config.MediaUrlResolverHostReplace';

    public function __construct(
        private readonly string $environment,
        private readonly SystemConfigService $systemConfigService,
    ) {
    }

    /**
     * @return array{
     *     environment: string,
     *     maintenance: array{available: bool},
     *     mediaFallback: array{
     *         enabled: bool,
     *         host: ?string,
     *         source: string,
     *         configKey: string,
     *         legacyConfigKey: string,
     *         allowedHosts: list<string>
     *     },
     *     documentation: array<string, mixed>
     * }
     */
    public function getState(): array
    {
        $configuredHost = $this->normalizeString($this->systemConfigService->get(self::MEDIA_FALLBACK_CONFIG));
        $legacyHost = $this->normalizeString($this->systemConfigService->get(self::LEGACY_MEDIA_FALLBACK_CONFIG));
        $mediaFallbackHost = $configuredHost ?? $legacyHost;
        $mediaFallbackSource = $configuredHost !== null
            ? 'system-config'
            : ($legacyHost !== null ? 'legacy-system-config' : 'missing');

        return [
            'environment' => $this->environment,
            'maintenance' => [
                'available' => $this->environment === 'dev',
            ],
            'mediaFallback' => [
                'enabled' => $mediaFallbackHost !== null,
                'host' => $mediaFallbackHost,
                'source' => $mediaFallbackSource,
                'configKey' => self::MEDIA_FALLBACK_CONFIG,
                'legacyConfigKey' => self::LEGACY_MEDIA_FALLBACK_CONFIG,
                'allowedHosts' => ['ddev.site', 'ngrok-free.app'],
            ],
            'documentation' => $this->loadDocumentation(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function saveMediaFallbackHost(?string $host): array
    {
        $normalizedHost = $this->normalizeString($host);

        $this->systemConfigService->set(self::MEDIA_FALLBACK_CONFIG, $normalizedHost);
        $this->systemConfigService->set(self::LEGACY_MEDIA_FALLBACK_CONFIG, $normalizedHost);

        return $this->getState();
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
}
