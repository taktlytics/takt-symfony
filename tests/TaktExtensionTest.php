<?php

namespace Vskstudio\Takt\Symfony\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Vskstudio\Takt\Symfony\DependencyInjection\TaktExtension;
use Vskstudio\Takt\Symfony\TaktFactory;
use Vskstudio\Takt\SnippetRenderer;
use Vskstudio\Takt\Takt;

final class TaktExtensionTest extends TestCase
{
    private function compile(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->registerExtension($ext = new TaktExtension());
        $ext->load([$config], $container);
        $container->register('request_stack', \Symfony\Component\HttpFoundation\RequestStack::class);
        $container->compile();

        return $container;
    }

    public function test_registers_services_from_config(): void
    {
        $c = $this->compile(['domain' => 'example.com', 'endpoint' => 'https://takt.example.com', 'api_key' => 'k_test', 'mode' => 'cdn']);
        $this->assertInstanceOf(SnippetRenderer::class, $c->get(SnippetRenderer::class));
        $this->assertInstanceOf(Takt::class, $c->get(Takt::class));
    }

    public function test_defaults_apply_with_empty_config(): void
    {
        $c = $this->compile([]);
        $html = $c->get(SnippetRenderer::class)->render();
        $this->assertInstanceOf(SnippetRenderer::class, $c->get(SnippetRenderer::class));
        $this->assertInstanceOf(Takt::class, $c->get(Takt::class));
        $this->assertStringContainsString("(function(){'use strict';", $html);
    }

    public function test_snippet_renderer_uses_domain(): void
    {
        $c = $this->compile(['domain' => 'example.com', 'mode' => 'cdn']);
        $this->assertStringContainsString('data-domain="example.com"', $c->get(SnippetRenderer::class)->render());
    }

    public function test_inline_mode_embeds_bundle(): void
    {
        $c = $this->compile(['domain' => 'example.com', 'mode' => 'inline']);
        $html = $c->get(SnippetRenderer::class)->render();
        $this->assertStringContainsString("(function(){'use strict';", $html);
        $this->assertStringNotContainsString(' src=', $html);
    }

    public function test_inline_bundle_does_not_reassign_global_takt(): void
    {
        $c = $this->compile(['domain' => 'example.com', 'mode' => 'inline']);
        $html = $c->get(SnippetRenderer::class)->render();
        $this->assertStringNotContainsString('var takt', $html);
        $this->assertStringNotContainsString('})({})', $html);
        $this->assertStringContainsString("(function(){'use strict';", $html);
    }

    public function test_cdn_mode_uses_jsdelivr(): void
    {
        $c = $this->compile(['domain' => 'example.com', 'mode' => 'cdn']);
        $html = $c->get(SnippetRenderer::class)->render();
        $this->assertStringContainsString('src="https://cdn.jsdelivr.net', $html);
    }

    public function test_asset_mode_uses_self_hosted_path(): void
    {
        $c = $this->compile(['domain' => 'example.com', 'mode' => 'asset']);
        $this->assertStringContainsString('src="/takt/takt.auto.js"', $c->get(SnippetRenderer::class)->render());
    }

    public function test_autocapture_attrs_collapse_into_data_auto(): void
    {
        $c = $this->compile([
            'domain' => 'example.com',
            'mode' => 'cdn',
            'outbound' => true,
            'files' => true,
            'tagged' => true,
            'not_found' => true,
        ]);
        $html = $c->get(SnippetRenderer::class)->render();
        $this->assertStringContainsString('data-auto="outbound,downloads,tagged,404"', $html);
    }

    public function test_file_extensions_emit_downloads_ext(): void
    {
        $c = $this->compile(['domain' => 'example.com', 'mode' => 'cdn', 'files' => true, 'file_extensions' => ['pdf', 'zip']]);
        $this->assertStringContainsString('data-downloads-ext="pdf,zip"', $c->get(SnippetRenderer::class)->render());
    }

    public function test_nonce_flows_into_snippet(): void
    {
        $c = $this->compile(['domain' => 'example.com', 'mode' => 'cdn', 'nonce' => 'abc123']);
        $this->assertStringContainsString('nonce="abc123"', $c->get(SnippetRenderer::class)->render());
    }

    public function test_advanced_options_flow_into_data_attrs(): void
    {
        $c = $this->compile([
            'domain' => 'example.com',
            'mode' => 'cdn',
            'sample_rate' => 0.5,
            'track_query' => true,
            'query_params' => ['utm_source', 'utm_medium'],
            'respect_dnt' => false,
            'enabled' => false,
        ]);
        $html = $c->get(SnippetRenderer::class)->render();
        $this->assertStringContainsString('data-sample-rate="0.5"', $html);
        $this->assertStringContainsString('data-track-query="true"', $html);
        $this->assertStringContainsString('data-query-params="utm_source,utm_medium"', $html);
        $this->assertStringContainsString('data-respect-dnt="false"', $html);
        $this->assertStringContainsString('data-enabled="false"', $html);
    }

    public function test_sdk_mode_renders_module_with_scrub_url(): void
    {
        $c = $this->compile([
            'domain' => 'example.com',
            'mode' => 'sdk',
            'sample_rate' => 0.25,
            'scrub_url' => '(u)=>u.split("#")[0]',
        ]);
        $html = $c->get(SnippetRenderer::class)->render();
        $this->assertStringContainsString('<script type="module"', $html);
        $this->assertStringContainsString('import{init}from', $html);
        $this->assertStringContainsString('"sampleRate":0.25', $html);
        $this->assertStringContainsString('scrubUrl:(u)=>u.split("#")[0]', $html);
    }

    public function test_sdk_mode_renders_exclude(): void
    {
        $c = $this->compile([
            'domain' => 'example.com',
            'mode' => 'sdk',
            'exclude' => ['/app', '/account'],
        ]);
        $html = $c->get(SnippetRenderer::class)->render();
        $this->assertStringContainsString('<script type="module"', $html);
        $this->assertStringContainsString('"exclude":["\/app","\/account"]', $html);
    }

    public function test_exclude_localhost_false_emits_attr(): void
    {
        $c = $this->compile(['domain' => 'example.com', 'mode' => 'cdn', 'exclude_localhost' => false]);
        $this->assertStringContainsString('data-exclude-localhost="false"', $c->get(SnippetRenderer::class)->render());
    }

    public function test_exclude_localhost_default_true_omits_attr(): void
    {
        $c = $this->compile(['domain' => 'example.com', 'mode' => 'cdn']);
        $this->assertStringNotContainsString('data-exclude-localhost', $c->get(SnippetRenderer::class)->render());
    }

    public function test_endpoint_flows_into_snippet(): void
    {
        $c = $this->compile(['domain' => 'example.com', 'endpoint' => '/collect', 'mode' => 'cdn']);
        $this->assertStringContainsString('data-endpoint="/collect"', $c->get(SnippetRenderer::class)->render());
    }

    public function test_script_origin_flows_into_snippet(): void
    {
        $c = $this->compile(['domain' => 'example.com', 'script_origin' => 'https://m.example.com', 'mode' => 'cdn']);
        $this->assertStringContainsString('data-script-origin="https://m.example.com"', $c->get(SnippetRenderer::class)->render());
    }

    public function test_script_origin_default_omits_attr(): void
    {
        $c = $this->compile(['domain' => 'example.com', 'mode' => 'cdn']);
        $this->assertStringNotContainsString('data-script-origin', $c->get(SnippetRenderer::class)->render());
    }

    public function test_services_are_public(): void
    {
        $c = $this->compile(['domain' => 'example.com']);
        $this->assertTrue($c->getDefinition(SnippetRenderer::class)->isPublic());
        $this->assertTrue($c->getDefinition(Takt::class)->isPublic());
    }

    public function test_takt_service_is_not_shared(): void
    {
        // L'attribution visiteur est lue sur la requête courante : une instance
        // partagée figerait l'IP/User-Agent de la première requête sous un
        // exécuteur longue durée (FrankenPHP worker, RoadRunner).
        $c = $this->compile(['domain' => 'example.com']);
        $this->assertFalse($c->getDefinition(Takt::class)->isShared());
        $this->assertNotSame($c->get(Takt::class), $c->get(Takt::class));
    }

    public function test_snippet_renderer_is_shared(): void
    {
        $c = $this->compile(['domain' => 'example.com']);
        $this->assertSame($c->get(SnippetRenderer::class), $c->get(SnippetRenderer::class));
    }

    public function test_twig_extension_registered_and_tagged(): void
    {
        $c = $this->compile(['domain' => 'example.com']);
        $def = $c->getDefinition(\Vskstudio\Takt\Symfony\Twig\TaktTwigExtension::class);
        $this->assertTrue($def->hasTag('twig.extension'));
    }

    public function test_takt_factory_forwards_request_visitor(): void
    {
        $request = Request::create('/', 'GET', [], [], [], [
            'REMOTE_ADDR' => '203.0.113.7',
            'HTTP_USER_AGENT' => 'Mozilla/5.0',
        ]);
        $stack = new RequestStack();
        $stack->push($request);

        $takt = TaktFactory::create('https://takt.example.com', 'example.com', 'k_test', $stack);
        $this->assertInstanceOf(\Vskstudio\Takt\Takt::class, $takt);
    }

    public function test_takt_factory_without_request_still_builds(): void
    {
        $takt = TaktFactory::create('https://takt.example.com', 'example.com', null, new RequestStack());
        $this->assertInstanceOf(\Vskstudio\Takt\Takt::class, $takt);
    }
}
