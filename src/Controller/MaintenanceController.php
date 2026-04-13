<?php declare(strict_types=1);

namespace Cws\DevelopmentTools\Controller;

use Cws\DevelopmentTools\Exception\DevelopmentToolsUnavailableException;
use Cws\DevelopmentTools\Service\DevelopmentToolsInfoService;
use Cws\DevelopmentTools\Service\DevelopmentMaintenanceService;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID], PlatformRequest::ATTRIBUTE_ACL => ['system.plugin_maintain']])]
final class MaintenanceController extends AbstractController
{
    public function __construct(
        private readonly DevelopmentMaintenanceService $developmentMaintenanceService,
        private readonly DevelopmentToolsInfoService $developmentToolsInfoService,
    ) {
    }

    #[Route(
        path: '/api/_action/cws-development-tools/state',
        name: 'api.action.cws_development_tools.state',
        methods: [Request::METHOD_GET]
    )]
    public function state(): JsonResponse
    {
        return new JsonResponse([
            'success' => true,
            'data' => $this->developmentToolsInfoService->getState(),
        ]);
    }

    #[Route(
        path: '/api/_action/cws-development-tools/refresh',
        name: 'api.action.cws_development_tools.refresh',
        methods: [Request::METHOD_POST]
    )]
    public function refresh(Request $request): JsonResponse
    {
        $payload = $this->parsePayload($request);

        try {
            $result = $this->developmentMaintenanceService->refresh(
                $this->resolveBoolean($payload, 'activeOnly', false),
                $this->resolveBoolean($payload, 'keepAssets', false),
                $this->resolveBoolean($payload, 'resetOpcache', true)
            );
        } catch (DevelopmentToolsUnavailableException $exception) {
            throw new AccessDeniedHttpException($exception->getMessage(), $exception);
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'Development maintenance completed.',
            'data' => $result,
        ]);
    }

    #[Route(
        path: '/api/_action/cws-development-tools/clear-cache',
        name: 'api.action.cws_development_tools.clear_cache',
        methods: [Request::METHOD_POST]
    )]
    public function clearCache(): JsonResponse
    {
        try {
            $result = $this->developmentMaintenanceService->clearCache();
        } catch (DevelopmentToolsUnavailableException $exception) {
            throw new AccessDeniedHttpException($exception->getMessage(), $exception);
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'Cache was successfully cleared.',
            'data' => $result,
        ]);
    }

    #[Route(
        path: '/api/_action/cws-development-tools/compile-themes',
        name: 'api.action.cws_development_tools.compile_themes',
        methods: [Request::METHOD_POST]
    )]
    public function compileThemes(Request $request): JsonResponse
    {
        $payload = $this->parsePayload($request);

        try {
            $result = $this->developmentMaintenanceService->compileThemes(
                $this->resolveBoolean($payload, 'activeOnly', false),
                $this->resolveBoolean($payload, 'keepAssets', false)
            );
        } catch (DevelopmentToolsUnavailableException $exception) {
            throw new AccessDeniedHttpException($exception->getMessage(), $exception);
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'Themes were successfully compiled.',
            'data' => $result,
        ]);
    }

    #[Route(
        path: '/api/_action/cws-development-tools/clear-opcache',
        name: 'api.action.cws_development_tools.clear_opcache',
        methods: [Request::METHOD_POST]
    )]
    public function clearOpcache(): JsonResponse
    {
        try {
            $result = $this->developmentMaintenanceService->clearOpcache();
        } catch (DevelopmentToolsUnavailableException $exception) {
            throw new AccessDeniedHttpException($exception->getMessage(), $exception);
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'OPcache reset completed.',
            'data' => $result,
        ]);
    }

    #[Route(
        path: '/api/_action/cws-development-tools/media-fallback',
        name: 'api.action.cws_development_tools.media_fallback',
        methods: [Request::METHOD_POST]
    )]
    public function saveMediaFallback(Request $request): JsonResponse
    {
        $payload = $this->parsePayload($request);

        $host = $payload['host'] ?? null;
        if ($host !== null && !\is_string($host)) {
            throw new BadRequestHttpException('The "host" field must be a string or null.');
        }

        $enabled = array_key_exists('enabled', $payload)
            ? $this->resolveBoolean($payload, 'enabled', false)
            : ($this->developmentToolsInfoService->getState()['mediaFallback']['enabled'] ?? false);

        return new JsonResponse([
            'success' => true,
            'message' => 'Media fallback setting saved.',
            'data' => $this->developmentToolsInfoService->saveMediaFallback($host, $enabled),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function parsePayload(Request $request): array
    {
        $content = trim($request->getContent());
        if ($content === '') {
            return [];
        }

        try {
            $payload = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new BadRequestHttpException('Invalid JSON payload.', $exception);
        }

        if (!\is_array($payload)) {
            throw new BadRequestHttpException('The request payload must be a JSON object.');
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function resolveBoolean(array $payload, string $key, bool $default): bool
    {
        if (!array_key_exists($key, $payload)) {
            return $default;
        }

        return filter_var($payload[$key], \FILTER_VALIDATE_BOOLEAN, \FILTER_NULL_ON_FAILURE) ?? $default;
    }
}
