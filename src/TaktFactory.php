<?php

namespace Vskstudio\Takt\Symfony;

use Symfony\Component\HttpFoundation\RequestStack;
use Vskstudio\Takt\Takt;

final class TaktFactory
{
    /** @param list<string> $redactRoutes */
    public static function create(string $endpoint, string $domain, ?string $apiKey, RequestStack $stack, array $redactRoutes = [], ?RouteTemplate $routeTemplate = null): Takt
    {
        $takt = new Takt(Endpoint::origin($endpoint), $domain, $apiKey, redactRoutes: $redactRoutes);
        $request = $stack->getCurrentRequest();
        if ($request !== null) {
            $takt = $takt->withVisitor($request->getClientIp(), $request->headers->get('User-Agent'));
        }
        if ($routeTemplate !== null) {
            $takt = $takt->withRoute($routeTemplate->current(...));
        }

        return $takt;
    }
}
