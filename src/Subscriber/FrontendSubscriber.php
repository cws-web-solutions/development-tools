<?php

declare(strict_types=1);


namespace Cws\DevelopmentTools\Subscriber;

use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEvents;
use Symfony\Component\Filesystem\Filesystem;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Cws\DevelopmentTools\Service\DevelopmentToolsInfoService;

class FrontendSubscriber implements EventSubscriberInterface
{
    private SystemConfigService $systemConfigService;

    private string $environment;

    private Filesystem $filesystem;

    private RequestStack $requestStack;

    private string $publicDir;

    public function __construct(
        SystemConfigService $systemConfigService,
        string $environment,
        Filesystem $filesystem,
        RequestStack $requestStack,
        string $projectDir
    ) {
        $this->systemConfigService = $systemConfigService;
        $this->environment = $environment;
        $this->filesystem = $filesystem;
        $this->requestStack = $requestStack;
        $this->publicDir = rtrim($projectDir, '/') . '/public';
    }

    public static function getSubscribedEvents(): array
    {
        return [
            MediaEvents::MEDIA_LOADED_EVENT => 'onMediaLoadedEvent',
        ];
    }

    public function onMediaLoadedEvent(EntityLoadedEvent $event): void
    {
        if ($this->environment !== 'dev') {
            return;
        }

        $mediaUrlResolverHostFind = $this->resolveCurrentSalesChannelUrl();
        if (!$mediaUrlResolverHostFind) {
            return;
        }

        $mediaUrlResolverHostReplace = $this->systemConfigService->get(DevelopmentToolsInfoService::MEDIA_FALLBACK_CONFIG);
        if (!\is_string($mediaUrlResolverHostReplace) || trim($mediaUrlResolverHostReplace) === '') {
            $mediaUrlResolverHostReplace = $this->systemConfigService->get('DisMediaUrlResolverLocalDevelopment.config.MediaUrlResolverHostReplace');
        }

        if (!\is_string($mediaUrlResolverHostReplace) || trim($mediaUrlResolverHostReplace) === '') {
            return;
        }

        $mediaFallbackEnabled = $this->normalizeBoolean(
            $this->systemConfigService->get(DevelopmentToolsInfoService::MEDIA_FALLBACK_ENABLED_CONFIG)
        );
        if ($mediaFallbackEnabled === false) {
            return;
        }

        $mediaUrlResolverHostReplace = rtrim($mediaUrlResolverHostReplace, '/');

        /** @var MediaCollection $medias */
        $medias = $event->getEntities();

        foreach ($medias as $media) {
            $mediaUrl = $media->getUrl();
            if ($mediaUrl && !$this->fileExists($mediaUrl)) {
                $media->setUrl($this->buildFallbackUrl($mediaUrl, $mediaUrlResolverHostFind, $mediaUrlResolverHostReplace));
            }

            /** @var MediaCollection $thumbnails */
            $thumbnails = $media->getThumbnails();

            foreach ($thumbnails as $thumbnail) {
                $thumbnailUrl = $thumbnail->getUrl();
                if ($thumbnailUrl && !$this->fileExists($thumbnailUrl)) {
                    $thumbnail->setUrl(
                        $this->buildFallbackUrl($thumbnailUrl, $mediaUrlResolverHostFind, $mediaUrlResolverHostReplace)
                    );
                }
            }
        }
    }

    private function resolveCurrentSalesChannelUrl(): ?string
    {
        $request = $this->requestStack->getMainRequest();
        if ($request === null) {
            return null;
        }

        $salesChannelContext = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);
        $domainId = $request->attributes->get(SalesChannelRequest::ATTRIBUTE_DOMAIN_ID);

        if ($salesChannelContext instanceof SalesChannelContext && \is_string($domainId)) {
            $domain = $salesChannelContext->getSalesChannel()->getDomains()?->get($domainId);
            if ($domain !== null && $domain->getUrl() !== '') {
                return rtrim($domain->getUrl(), '/');
            }
        }

        return rtrim($request->getSchemeAndHttpHost(), '/');
    }

    private function fileExists(string $filePath): bool
    {
        $path = parse_url($filePath, PHP_URL_PATH);
        if (!\is_string($path) || $path === '') {
            return false;
        }

        $physicalPath = $this->publicDir . '/' . ltrim(rawurldecode($path), '/');

        return $this->filesystem->exists($physicalPath);
    }

    private function buildFallbackUrl(string $url, string $currentHost, string $fallbackHost): string
    {
        if (str_starts_with($url, $currentHost)) {
            return $fallbackHost . substr($url, \strlen($currentHost));
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (!\is_string($path) || $path === '') {
            return $url;
        }

        $query = parse_url($url, PHP_URL_QUERY);
        $fragment = parse_url($url, PHP_URL_FRAGMENT);

        return $fallbackHost
            . '/' . ltrim($path, '/')
            . (\is_string($query) && $query !== '' ? '?' . $query : '')
            . (\is_string($fragment) && $fragment !== '' ? '#' . $fragment : '');
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
