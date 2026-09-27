<?php

namespace Vskstudio\Takt\Symfony\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tb = new TreeBuilder('takt');
        $tb->getRootNode()
            ->children()
                ->scalarNode('domain')->defaultValue('')->end()
                ->scalarNode('endpoint')
                    ->info('Where events are collected. The service origin ("https://taktlytics.com") and the full collect URL ("https://taktlytics.com/api/event") are both accepted and equivalent: the browser snippet and the server-side sender each receive the form they need. A value carrying any other path (a same-origin first-party proxy such as "/collect") is used verbatim.')
                    ->defaultValue('https://taktlytics.com')
                ->end()
                ->scalarNode('script_origin')
                    ->info('First-party origin to serve the tracker + derive the endpoint from ({origin}/api/event) — your Takt domain or a custom domain you proxy through to dodge ad-blockers (endpoint wins over it).')
                    ->defaultNull()
                ->end()
                ->scalarNode('api_key')->defaultNull()->end()
                ->scalarNode('mode')->defaultValue('inline')->end()
                ->booleanNode('outbound')->defaultFalse()->end()
                ->booleanNode('files')->defaultFalse()->end()
                ->booleanNode('tagged')->defaultFalse()->end()
                ->booleanNode('not_found')->defaultFalse()->end()
                ->arrayNode('file_extensions')
                    ->info('Restrict download tracking to these extensions, e.g. ["pdf", "zip"]. Empty keeps the tracker built-in list.')
                    ->scalarPrototype()->end()
                ->end()
                ->booleanNode('exclude_localhost')->defaultTrue()->end()
                ->scalarNode('nonce')
                    ->info('CSP nonce for the inline <script>. A CSP nonce is request-scoped; set a static one only if your policy uses one.')
                    ->defaultNull()
                ->end()
                ->floatNode('sample_rate')
                    ->info('Sample a fraction of hits, e.g. 0.5 keeps ~50%. Null tracks everything.')
                    ->defaultNull()
                ->end()
                ->booleanNode('track_query')
                    ->info('Keep the raw query string + hash in tracked URLs (default strips them).')
                    ->defaultNull()
                ->end()
                ->arrayNode('query_params')
                    ->info('Query params to keep when track_query is off, e.g. ["utm_source", "utm_medium"].')
                    ->scalarPrototype()->end()
                ->end()
                ->arrayNode('exclude')
                    ->info('Path prefixes never tracked, e.g. ["/app", "/account"]. Requires mode: sdk.')
                    ->scalarPrototype()->end()
                ->end()
                ->booleanNode('respect_dnt')
                    ->info('Set to false to stop honoring the browser Do-Not-Track header.')
                    ->defaultNull()
                ->end()
                ->booleanNode('enabled')
                    ->info('Kill-switch: set to false to disable tracking entirely.')
                    ->defaultNull()
                ->end()
                ->arrayNode('redact_routes')
                    ->info('Sensitive routes sent as their pattern instead of the real path, e.g. ["/verify/{token}", "/reset/{code}"]. The snippet requires mode: sdk.')
                    ->scalarPrototype()->end()
                ->end()
                ->booleanNode('route_templates')
                    ->info('Send every page as its Symfony route path ("/users/{id}"), read from the current request. The snippet requires mode: sdk; server-side events of the request use it as their default route.')
                    ->defaultFalse()
                ->end()
                ->scalarNode('scrub_url')
                    ->info('Raw JS function to rewrite URLs before sending, e.g. "(u) => u.split(\'#\')[0]". Requires mode=sdk and is dev-controlled only — injected verbatim into the page; never build it from user input.')
                    ->defaultNull()
                ->end()
            ->end();

        return $tb;
    }
}
