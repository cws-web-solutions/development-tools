<?php

declare(strict_types=1);


namespace Cws\DevelopmentTools\Subscriber;

use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEvents;
use Symfony\Component\Filesystem\Filesystem;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Cws\DevelopmentTools\Service\DevelopmentToolsInfoService;

class FrontendSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly string $environment,
        private readonly Filesystem $filesystem,
        private readonly RequestStack $requestStack,
    ) {}

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

        $appUrl = EnvironmentHelper::getVariable('APP_URL');

        $allowedHosts = ['ddev.site', 'ngrok-free.app'];
        $isAllowedHost = false;

        foreach ($allowedHosts as $allowedHost) {
            if (\is_string($appUrl) && str_contains($appUrl, $allowedHost)) {
                $isAllowedHost = true;
                break;
            }
        }

        if (!$isAllowedHost) {
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

        $mediaUrlResolverHostReplace = rtrim($mediaUrlResolverHostReplace, '/');

        /** @var MediaCollection $medias */
        $medias = $event->getEntities();

        foreach ($medias as $media) {
            $mediaUrl = $media->getUrl();
            if ($mediaUrl && !$this->fileExists($mediaUrl)) {
                $media->setUrl(
                    str_replace($mediaUrlResolverHostFind, $mediaUrlResolverHostReplace, $mediaUrl)
                );
            }

            /** @var MediaCollection $thumbnails */
            $thumbnails = $media->getThumbnails();

            foreach ($thumbnails as $thumbnail) {
                $thumbnailUrl = $thumbnail->getUrl();
                if ($thumbnailUrl && !$this->fileExists($thumbnailUrl)) {
                    $thumbnail->setUrl(
                        str_replace($mediaUrlResolverHostFind, $mediaUrlResolverHostReplace, $thumbnailUrl)
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
        if ($path === null) {
            return false;
        }

        $path = str_replace('/media/', '', $path);
        $physicalPath = \sprintf('%s/media/%s', getcwd(), $path);

        return $this->filesystem->exists($physicalPath);
    }
}
