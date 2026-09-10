<?php

declare(strict_types=1);

namespace rafalmasiarek\DashboardKitCompress;

use Psr\Container\ContainerInterface;
use Slim\App;

/**
 * Registers HTML output compression for a dashboard-kit application.
 *
 * Adds HtmlCompressMiddleware to the Slim application. Configuration is read
 * from the 'compress' key of app.config in the container:
 *
 *   Dashboard::create(__DIR__ . '/../', [
 *       'compress' => [
 *           'enabled' => true,
 *           'exclude' => ['/api/', '~^/debug/~'],
 *       ],
 *   ]);
 *
 * @package rafalmasiarek\DashboardKitCompress
 */
final class CompressAddon
{
    /**
     * Add the HTML compression middleware to the Slim application.
     *
     * @param App                $app       Slim application instance.
     * @param ContainerInterface $container PHP-DI container.
     * @return void
     */
    public static function register(App $app, ContainerInterface $container): void
    {
        if (!\class_exists(\rafalmasiarek\DashboardKit\Dashboard::class)) {
            throw new \LogicException(
                static::class . ' is a dashboard-kit addon and requires rafalmasiarek/dashboard-kit. '
                . 'Run: composer require rafalmasiarek/dashboard-kit'
            );
        }

        $config = (array) ($container->get('app.config')['compress'] ?? []);
        $app->add(new HtmlCompressMiddleware($config));
    }
}
