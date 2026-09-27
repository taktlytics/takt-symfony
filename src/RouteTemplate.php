<?php

namespace Vskstudio\Takt\Symfony;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;

final class RouteTemplate
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ?RouterInterface $router = null,
    ) {
    }

    public function current(): ?string
    {
        $name = $this->requestStack->getMainRequest()?->attributes->get('_route');
        if (!is_string($name) || $name === '' || $this->router === null) {
            return null;
        }

        return $this->router->getRouteCollection()->get($name)?->getPath();
    }
}
