<?php

namespace Vskstudio\Takt\Symfony\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Vskstudio\Takt\Symfony\OptionsFactory;
use Vskstudio\Takt\Symfony\RouteTemplate;
use Vskstudio\Takt\Symfony\TaktFactory;
use Vskstudio\Takt\Options;
use Vskstudio\Takt\SnippetRenderer;
use Vskstudio\Takt\Takt;

final class TaktExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $optionsDef = new Definition(Options::class);
        $optionsDef->setFactory([OptionsFactory::class, 'create']);
        $optionsDef->setArguments([[
            'domain' => $config['domain'],
            'endpoint' => $config['endpoint'],
            'scriptOrigin' => $config['script_origin'],
            'mode' => $config['mode'],
            'outbound' => $config['outbound'],
            'files' => $config['files'],
            'tagged' => $config['tagged'],
            'notFound' => $config['not_found'],
            'fileExtensions' => $config['file_extensions'],
            'excludeLocalhost' => $config['exclude_localhost'],
            'nonce' => $config['nonce'],
            'sampleRate' => $config['sample_rate'],
            'trackQuery' => $config['track_query'],
            'queryParams' => $config['query_params'],
            'exclude' => $config['exclude'],
            'respectDnt' => $config['respect_dnt'],
            'enabled' => $config['enabled'],
            'scrubUrl' => $config['scrub_url'],
            'redactRoutes' => $config['redact_routes'],
            'routeTemplates' => $config['route_templates'],
        ]]);
        $container->setDefinition(Options::class, $optionsDef);

        $rendererDef = new Definition(SnippetRenderer::class, [new Reference(Options::class)]);
        $rendererDef->setPublic(true);
        $container->setDefinition(SnippetRenderer::class, $rendererDef);

        $routeTemplate = null;
        if ($config['route_templates']) {
            $container->setDefinition(RouteTemplate::class, new Definition(RouteTemplate::class, [
                new Reference('request_stack'),
                new Reference('router', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ]));
            $routeTemplate = new Reference(RouteTemplate::class);
        }

        $taktDef = new Definition(Takt::class);
        $taktDef->setFactory([TaktFactory::class, 'create']);
        $taktDef->setArguments([
            $config['endpoint'],
            $config['domain'],
            $config['api_key'],
            new Reference('request_stack'),
            $config['redact_routes'],
            $routeTemplate,
        ]);
        $taktDef->setPublic(true);
        // Non partagé : l'instance capte l'IP/User-Agent de la requête courante,
        // elle ne doit pas survivre à la requête sous un exécuteur longue durée.
        $taktDef->setShared(false);
        $container->setDefinition(Takt::class, $taktDef);

        (new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../config')))->load('services.php');
    }
}
