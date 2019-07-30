<?php

namespace Bolt\Extension\ARANOVA\AuthExtendedDashboard;

use Bolt\Asset\File\JavaScript;
use Bolt\Asset\File\Stylesheet;
use Bolt\Asset\Widget\Widget;
use Bolt\Controller\Zone;
use Bolt\Extension\SimpleExtension;
use Silex\Application;

class SeoExtension extends SimpleExtension
{
    CONST APP_EXTENSION_VERSION = '1.0.0';
    CONST APP_EXTENSION_KEY = "aranova.authdashboard";

    /**
     * {@inheritdoc}
     *
     * Extending the backend menu:
     *
     * You can provide new Backend sites with their own menu option and template.
     *
     * Here we will add a new route to the system and register the menu option in the backend.
     *
     * You'll find the new menu option under "Extras".
     */
    
    protected function registerMenuEntries()
    {
        /*
         * Define a menu entry object and register it:
         *   - Route http://example.com/bolt/extensions/my-custom-backend-page-route
         *   - Menu label 'MyExtension Admin'
         *   - Menu icon a Font Awesome small child
         *   - Required Bolt permissions 'settings'
         */
        /* @var \Bolt\Application $app */
        
        $menu = new MenuEntry('authdashboard', 'authdashboard');
        $menu->setLabel("User Dashboard")
            ->setIcon('fa:group')
            ->setPermission('settings');

        $tableMenu = MenuEntry::create('authdashboard-table', 'table');
        $tableMenu->setLabel('Table view')
            ->setIcon('fa:table')
            ->setPermission('settings');

        
        $dashboardMenu = MenuEntry::create('authdashboard-dashboard', 'dashboard');
        $dashboardMenu->setLabel('Dashboard view')
            ->setIcon('fa:dashboard')
            ->setPermission('settings');
        
        $menu->add($tableMenu);
        $menu->add($dashboardMenu);
        
        return [ $menu ];
    }

    /**
     * {@inheritdoc}
     */
    protected function registerAssets()
    {
        $css = new Stylesheet();
        $css->setFileName('dashboard.css')->setZone(Zone::BACKEND);

        /* Ejemplo */
        $underscoreJs = new JavaScript();
        $underscoreJs->setFileName('underscore-min.js')->setZone(Zone::BACKEND)->setPriority(10);

        $js = new JavaScript();
        $js->setFileName('dashboard.js')->setZone(Zone::BACKEND)->setPriority(15);

        $assets = [
            $css,
            $underscoreJs,
            $js
        ];

        return $assets;
    }

    /**
     * {@inheritdoc}
     */
    protected function registerServices(Application $app)
    {
        $app['twig'] = $app->extend(
            'twig',
            function (\Twig_Environment $twig) use ($app) {
                $config = $this->getConfig();
                $twig->addGlobal(self::APP_EXTENSION_KEY . '.config', $config);

                return $twig;
            }
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function registerTwigPaths()
    {
        return [
            'templates' => ['position' => 'prepend', 'namespace' => 'bolt'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function registerTwigFunctions()
    {
        return [
            'getUsers' => 'getUsers',
        ];
    }

    /**
     * Returns an array with users
     * or not for the current user.
     *
     * @return array
     */
    public function getUsers()
    {
        $config = $this->getConfig();

        /** @var \Bolt\Users $users */
        $users = $this->getContainer()['auth_account'];

        return $users;
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultConfig()
    {
        return [
            'templates' => [
                'table'     => '@bolt/_table.twig',
                'dashboard' => '@bolt/_dashboard.twig'
            ]
        ];
    }
}