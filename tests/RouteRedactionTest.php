<?php

namespace Vskstudio\Takt\Symfony\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;
use Vskstudio\Takt\SnippetRenderer;
use Vskstudio\Takt\Symfony\DependencyInjection\TaktExtension;
use Vskstudio\Takt\Symfony\RouteTemplate;
use Vskstudio\Takt\Symfony\TaktFactory;
use Vskstudio\Takt\Symfony\Twig\TaktTwigExtension;
use Vskstudio\Takt\Takt;

final class RouteRedactionTest extends TestCase
{
    private static function router(): RouterInterface
    {
        $routes = new RouteCollection();
        $routes->add('verify', new Route('/verify/{token}'));
        $routes->add('blog', new Route('/blog/{page<\d+>?1}'));

        return new class ($routes) implements RouterInterface {
            private RequestContext $context;

            public function __construct(private readonly RouteCollection $routes)
            {
                $this->context = new RequestContext();
            }

            public function getRouteCollection(): RouteCollection
            {
                return $this->routes;
            }

            public function setContext(RequestContext $context): void
            {
                $this->context = $context;
            }

            public function getContext(): RequestContext
            {
                return $this->context;
            }

            public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
            {
                return (new UrlGenerator($this->routes, $this->context))->generate($name, $parameters, $referenceType);
            }

            public function match(string $pathinfo): array
            {
                return (new UrlMatcher($this->routes, $this->context))->match($pathinfo);
            }
        };
    }

    private static function stackFor(?string $routeName, string $path = '/verify/abc123'): RequestStack
    {
        $request = Request::create($path);
        if ($routeName !== null) {
            $request->attributes->set('_route', $routeName);
        }
        $stack = new RequestStack();
        $stack->push($request);

        return $stack;
    }

    /** @param array<string,mixed> $config */
    private static function compile(array $config, ?RequestStack $stack = null): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->registerExtension($ext = new TaktExtension());
        $ext->load([$config], $container);
        $container->register('request_stack', RequestStack::class)->setSynthetic(true)->setPublic(true);
        $container->register('router', RouterInterface::class)->setSynthetic(true)->setPublic(true);
        $container->compile();
        $container->set('request_stack', $stack ?? self::stackFor('verify'));
        $container->set('router', self::router());

        return $container;
    }

    private static function defaultRouteOf(Takt $takt): ?string
    {
        $route = (new \ReflectionMethod($takt, 'defaultRoute'))->invoke($takt);

        return is_string($route) ? $route : null;
    }

    public function test_route_template_resolves_the_current_route_path(): void
    {
        $this->assertSame('/verify/{token}', (new RouteTemplate(self::stackFor('verify'), self::router()))->current());
        $this->assertSame('/blog/{page}', (new RouteTemplate(self::stackFor('blog', '/blog/2'), self::router()))->current());
    }

    public function test_route_template_is_null_without_a_known_route(): void
    {
        $this->assertNull((new RouteTemplate(self::stackFor(null), self::router()))->current());
        $this->assertNull((new RouteTemplate(self::stackFor('missing'), self::router()))->current());
        $this->assertNull((new RouteTemplate(new RequestStack(), self::router()))->current());
        $this->assertNull((new RouteTemplate(self::stackFor('verify'), null))->current());
    }

    public function test_sdk_snippet_carries_redact_routes(): void
    {
        $c = self::compile(['domain' => 'example.com', 'mode' => 'sdk', 'redact_routes' => ['/verify/{token}']]);
        $html = $c->get(SnippetRenderer::class)->render();

        $this->assertStringContainsString('"redactRoutes":["\/verify\/[token]"]', $html);
    }

    public function test_redact_routes_outside_sdk_mode_throws(): void
    {
        $c = self::compile(['domain' => 'example.com', 'mode' => 'cdn', 'redact_routes' => ['/verify/{token}']]);

        $this->expectException(\InvalidArgumentException::class);
        $c->get(SnippetRenderer::class);
    }

    public function test_twig_function_renders_the_current_route_template(): void
    {
        $c = self::compile(['domain' => 'example.com', 'mode' => 'sdk', 'route_templates' => true]);
        $extension = $c->get(TaktTwigExtension::class);
        $this->assertInstanceOf(TaktTwigExtension::class, $extension);
        $html = $extension->render();

        $this->assertStringContainsString('"routeTemplates":true', $html);
        $this->assertStringContainsString('routeTemplate:()=>"\/verify\/{token}"', $html);
    }

    public function test_twig_function_ignores_routes_when_route_templates_is_off(): void
    {
        $c = self::compile(['domain' => 'example.com', 'mode' => 'sdk']);
        $extension = $c->get(TaktTwigExtension::class);
        $this->assertInstanceOf(TaktTwigExtension::class, $extension);

        $this->assertStringNotContainsString('routeTemplate', $extension->render());
        $this->assertFalse($c->has(RouteTemplate::class));
    }

    public function test_server_client_receives_redact_routes(): void
    {
        $c = self::compile(['domain' => 'example.com', 'redact_routes' => ['/verify/{token}']]);
        $takt = $c->get(Takt::class);
        $this->assertInstanceOf(Takt::class, $takt);

        $this->assertSame(['/verify/{token}'], (new \ReflectionProperty($takt, 'redactRoutes'))->getValue($takt));
        $this->assertNull(self::defaultRouteOf($takt));
    }

    public function test_server_client_defaults_to_the_current_route_when_route_templates_is_on(): void
    {
        $c = self::compile(['domain' => 'example.com', 'route_templates' => true]);
        $takt = $c->get(Takt::class);
        $this->assertInstanceOf(Takt::class, $takt);

        $this->assertSame('/verify/{token}', self::defaultRouteOf($takt));
    }

    public function test_takt_factory_stays_compatible_with_four_arguments(): void
    {
        $takt = TaktFactory::create('https://takt.example.com', 'example.com', null, new RequestStack());

        $this->assertNull(self::defaultRouteOf($takt));
    }
}
