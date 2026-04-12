<?php

declare(strict_types=1);

namespace Cws\DevelopmentTools\Controller;

use Cws\DevelopmentTools\Exception\DevelopmentToolsUnavailableException;
use Cws\DevelopmentTools\Service\DevelopmentMaintenanceService;
use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]])]
class StorefrontMaintenanceController extends AbstractController
{
    public function __construct(
        private readonly DevelopmentMaintenanceService $developmentMaintenanceService,
    ) {}

    #[Route(
        path: '/cws-devtools/maintenance/{action}',
        name: 'frontend.cws_development_tools.maintenance',
        defaults: ['XmlHttpRequest' => true],
        methods: [Request::METHOD_POST]
    )]
    public function runAction(string $action): JsonResponse
    {
        try {
            if ($action === 'cache-clear') {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'Cache cleared.',
                    'data' => $this->developmentMaintenanceService->clearCache(),
                ]);
            }

            if ($action === 'theme-compile') {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'Themes compiled.',
                    'data' => $this->developmentMaintenanceService->compileThemes(),
                ]);
            }
        } catch (DevelopmentToolsUnavailableException $exception) {
            throw new AccessDeniedHttpException($exception->getMessage(), $exception);
        }

        return new JsonResponse([
            'success' => false,
            'message' => 'Unknown maintenance action.',
        ], 400);
    }
}
