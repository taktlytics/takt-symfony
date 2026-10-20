<?php

namespace Vskstudio\Takt\Symfony;

use Vskstudio\Takt\Options;

final class OptionsFactory
{
    /**
     * Construit les options du snippet. La normalisation de `endpoint` a lieu
     * ici, à l'instanciation, pour que les valeurs issues d'un `%env()%`, non
     * résolues à la compilation du conteneur, soient traitées elles aussi.
     *
     * @param array<string,mixed> $config
     */
    public static function create(array $config): Options
    {
        $config['endpoint'] = Endpoint::collect($config['endpoint'] ?? null);

        return Options::fromArray($config);
    }
}
