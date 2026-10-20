<?php

namespace Vskstudio\Takt\Symfony\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Vskstudio\Takt\Options;
use Vskstudio\Takt\SnippetRenderer;
use Vskstudio\Takt\Symfony\DependencyInjection\TaktExtension;
use Vskstudio\Takt\Takt;

final class EndpointTest extends TestCase
{
    /** @param array<string,mixed> $config */
    private function compile(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->registerExtension($ext = new TaktExtension());
        $ext->load([$config], $container);
        $container->register('request_stack', \Symfony\Component\HttpFoundation\RequestStack::class);
        $container->compile();

        return $container;
    }

    private function renderedEndpoint(string $endpoint): string
    {
        $c = $this->compile(['domain' => 'example.com', 'endpoint' => $endpoint, 'mode' => 'cdn']);
        preg_match('/data-endpoint="([^"]*)"/', $c->get(SnippetRenderer::class)->render(), $m);

        return $m[1] ?? '';
    }

    private function serverEndpoint(string $endpoint): string
    {
        $c = $this->compile(['domain' => 'example.com', 'endpoint' => $endpoint]);

        return (string) (new \ReflectionProperty(Takt::class, 'endpoint'))->getValue($c->get(Takt::class));
    }

    public function test_bare_origin_gains_the_collect_path_in_the_snippet(): void
    {
        $this->assertSame('https://taktlytics.com/api/event', $this->renderedEndpoint('https://taktlytics.com'));
        $this->assertSame('https://ingest.example.com/api/event', $this->renderedEndpoint('https://ingest.example.com'));
    }

    public function test_bare_origin_stays_an_origin_server_side(): void
    {
        $this->assertSame('https://taktlytics.com', $this->serverEndpoint('https://taktlytics.com'));
        $this->assertSame('https://ingest.example.com', $this->serverEndpoint('https://ingest.example.com'));
    }

    public function test_full_collect_url_is_kept_verbatim_in_the_snippet(): void
    {
        $this->assertSame('https://taktlytics.com/api/event', $this->renderedEndpoint('https://taktlytics.com/api/event'));
        $this->assertSame('https://ingest.example.com/api/event', $this->renderedEndpoint('https://ingest.example.com/api/event'));
    }

    public function test_full_collect_url_is_reduced_to_an_origin_server_side(): void
    {
        $this->assertSame('https://taktlytics.com', $this->serverEndpoint('https://taktlytics.com/api/event'));
        $this->assertSame('https://ingest.example.com', $this->serverEndpoint('https://ingest.example.com/api/event'));
    }

    public function test_default_config_collects_on_the_hosted_service(): void
    {
        $c = $this->compile(['domain' => 'example.com', 'mode' => 'cdn']);
        $this->assertStringContainsString('data-endpoint="'.Options::HOSTED_ENDPOINT.'"', $c->get(SnippetRenderer::class)->render());
    }

    public function test_same_origin_proxy_path_is_left_untouched(): void
    {
        $this->assertSame('/collect', $this->renderedEndpoint('/collect'));
        $this->assertSame('/collect', $this->serverEndpoint('/collect'));
        $this->assertSame('/api/event', $this->renderedEndpoint('/api/event'));
    }

    public function test_trailing_slash_is_ignored(): void
    {
        $this->assertSame('https://taktlytics.com/api/event', $this->renderedEndpoint('https://taktlytics.com/'));
        $this->assertSame('https://taktlytics.com', $this->serverEndpoint('https://taktlytics.com/'));
    }

    public function test_hosted_default_with_script_origin_lets_the_tracker_derive_first_party(): void
    {
        $c = $this->compile(['domain' => 'example.com', 'mode' => 'cdn', 'script_origin' => 'https://m.example.com']);
        $this->assertStringNotContainsString('data-endpoint', $c->get(SnippetRenderer::class)->render());
    }

    public function test_plain_http_endpoint_is_refused_by_the_server_client(): void
    {
        $c = $this->compile(['domain' => 'example.com', 'endpoint' => 'http://localhost:8000']);

        $this->expectException(\InvalidArgumentException::class);
        $c->get(Takt::class);
    }

    public function test_plain_http_endpoint_is_refused_by_the_snippet(): void
    {
        $c = $this->compile(['domain' => 'example.com', 'endpoint' => 'http://localhost:8000', 'mode' => 'cdn']);

        $this->expectException(\InvalidArgumentException::class);
        $c->get(SnippetRenderer::class);
    }

    public function test_endpoint_from_an_env_placeholder_is_normalised_at_runtime(): void
    {
        // Le réglage peut être un %env()% : la normalisation doit donc avoir lieu
        // à l'instanciation, pas à la compilation du conteneur.
        $container = new ContainerBuilder();
        $container->registerExtension($ext = new TaktExtension());
        $container->setParameter('env(TAKT_ENDPOINT_TEST)', 'https://ingest.example.com');
        $ext->load([['domain' => 'example.com', 'mode' => 'cdn', 'endpoint' => '%env(TAKT_ENDPOINT_TEST)%']], $container);
        $container->register('request_stack', \Symfony\Component\HttpFoundation\RequestStack::class);
        $container->compile(true);

        $this->assertStringContainsString(
            'data-endpoint="https://ingest.example.com/api/event"',
            $container->get(SnippetRenderer::class)->render()
        );
        $this->assertSame(
            'https://ingest.example.com',
            (new \ReflectionProperty(Takt::class, 'endpoint'))->getValue($container->get(Takt::class))
        );
    }
}
