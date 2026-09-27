<?php

namespace Vskstudio\Takt\Symfony\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Vskstudio\Takt\SnippetRenderer;
use Vskstudio\Takt\Symfony\RouteTemplate;

final class TaktTwigExtension extends AbstractExtension
{
    public function __construct(
        private readonly SnippetRenderer $renderer,
        private readonly ?RouteTemplate $routeTemplate = null,
    ) {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('takt', [$this, 'render'], ['is_safe' => ['html']])];
    }

    public function render(): string
    {
        $template = $this->routeTemplate?->current();

        return ($template === null ? $this->renderer : $this->renderer->withRouteTemplate($template))->render();
    }
}
