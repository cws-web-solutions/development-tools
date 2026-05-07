<?php

declare(strict_types=1);

namespace Cws\DevelopmentTools\Service;

use Cws\DevelopmentTools\Exception\DevelopmentToolsUnavailableException;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Context;
use Shopware\Storefront\Theme\ConfigLoader\AbstractAvailableThemeProvider;
use Shopware\Storefront\Theme\ThemeService;

final class DevelopmentMaintenanceService
{
    private string $environment;

    private CacheClearer $cacheClearer;

    private ThemeService $themeService;

    private AbstractAvailableThemeProvider $themeProvider;

    public function __construct(
        string $environment,
        CacheClearer $cacheClearer,
        ThemeService $themeService,
        AbstractAvailableThemeProvider $themeProvider
    ) {
        $this->environment = $environment;
        $this->cacheClearer = $cacheClearer;
        $this->themeService = $themeService;
        $this->themeProvider = $themeProvider;
    }

    /**
     * @return array{
     *     environment: string,
     *     cacheCleared: true
     * }
     */
    public function clearCache(): array
    {
        $this->assertAvailable();

        $this->cacheClearer->clear();

        return [
            'environment' => $this->environment,
            'cacheCleared' => true,
        ];
    }

    /**
     * @return array{
     *     environment: string,
     *     compiledSalesChannelIds: list<string>,
     *     compiledThemeIds: list<string>
     * }
     */
    public function compileThemes(bool $activeOnly = false, bool $keepAssets = false): array
    {
        $compiledSalesChannelIds = [];
        $compiledThemeIds = [];

        $context = Context::createCLIContext();
        $context->addState(ThemeService::STATE_NO_QUEUE);

        foreach ($this->themeProvider->load($context, $activeOnly) as $salesChannelId => $themeId) {
            $this->themeService->compileTheme($salesChannelId, $themeId, $context, null, !$keepAssets);
            $compiledSalesChannelIds[] = $salesChannelId;
            $compiledThemeIds[] = $themeId;
        }

        return [
            'environment' => $this->environment,
            'compiledSalesChannelIds' => $compiledSalesChannelIds,
            'compiledThemeIds' => array_values(array_unique($compiledThemeIds)),
        ];
    }

    /**
     * @return array{
     *     environment: string,
     *     opcache: array{
     *         available: bool,
     *         enabled: bool,
     *         reset: bool,
     *         status: string,
     *         sapi: string,
     *         message: string
     *     }
     * }
     */
    public function clearOpcache(): array
    {
        return [
            'environment' => $this->environment,
            'opcache' => $this->resetOpcache(),
        ];
    }

    /**
     * @return array{
     *     environment: string,
     *     cacheCleared: true,
     *     compiledSalesChannelIds: list<string>,
     *     compiledThemeIds: list<string>,
     *     opcache: array{
     *         available: bool,
     *         enabled: bool,
     *         reset: bool,
     *         status: string,
     *         sapi: string,
     *         message: string
     *     }
     * }
     */
    public function refresh(bool $activeOnly = false, bool $keepAssets = false, bool $resetOpcache = true): array
    {
        $cacheResult = $this->clearCache();
        $themeResult = $this->compileThemes($activeOnly, $keepAssets);
        $opcacheResult = $resetOpcache
            ? $this->clearOpcache()['opcache']
            : $this->buildOpcacheResult('skipped', 'OPcache reset was skipped.', false, false);

        return [
            'environment' => $this->environment,
            'cacheCleared' => $cacheResult['cacheCleared'],
            'compiledSalesChannelIds' => $themeResult['compiledSalesChannelIds'],
            'compiledThemeIds' => $themeResult['compiledThemeIds'],
            'opcache' => $opcacheResult,
        ];
    }

    public function assertAvailable(): void
    {
        if ($this->environment !== 'dev') {
            throw DevelopmentToolsUnavailableException::becauseEnvironmentIsNotDev($this->environment);
        }
    }

    /**
     * @return array{
     *     available: bool,
     *     enabled: bool,
     *     reset: bool,
     *     status: string,
     *     sapi: string,
     *     message: string
     * }
     */
    private function resetOpcache(): array
    {
        if (!\function_exists('opcache_reset')) {
            return $this->buildOpcacheResult(
                'unavailable',
                'OPcache is not available in the current PHP runtime.',
                false,
                false
            );
        }

        $enabledDirective = PHP_SAPI === 'cli' ? 'opcache.enable_cli' : 'opcache.enable';
        $enabled = filter_var(ini_get($enabledDirective), \FILTER_VALIDATE_BOOLEAN);

        if ($enabled !== true) {
            return $this->buildOpcacheResult(
                'disabled',
                \sprintf('OPcache is available but disabled for %s (%s=0).', PHP_SAPI, $enabledDirective),
                true,
                false
            );
        }

        try {
            $reset = opcache_reset();
        } catch (\Throwable $exception) {
            return $this->buildOpcacheResult(
                'failed',
                'OPcache reset failed: ' . $exception->getMessage(),
                true,
                true
            );
        }

        if ($reset !== true) {
            return $this->buildOpcacheResult(
                'failed',
                'OPcache reset returned false.',
                true,
                true
            );
        }

        return $this->buildOpcacheResult(
            'reset',
            \sprintf('OPcache was reset for %s.', PHP_SAPI),
            true,
            true
        );
    }

    /**
     * @return array{
     *     available: bool,
     *     enabled: bool,
     *     reset: bool,
     *     status: string,
     *     sapi: string,
     *     message: string
     * }
     */
    private function buildOpcacheResult(string $status, string $message, bool $available, bool $enabled): array
    {
        return [
            'available' => $available,
            'enabled' => $enabled,
            'reset' => $status === 'reset',
            'status' => $status,
            'sapi' => PHP_SAPI,
            'message' => $message,
        ];
    }
}
