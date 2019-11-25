<?php

namespace Bolt\Extension\ARANOVA\AuthExtendedDashboard;

use Bolt\Asset\File\JavaScript;
use Bolt\Asset\File\Stylesheet;
use Bolt\Asset\Widget\Widget;
use Bolt\Controller\Zone;
use Bolt\Extension\SimpleExtension;
use Silex\Application;
use Bolt\Menu\MenuEntry;
use Bolt\Extension\ARANOVA\AuthExtendedDashboard\Controller\BackendController;
use Bolt\Extension\ARANOVA\AuthExtendedDashboard\Service\AuthService;
use Bolt\Version;
use Bolt\Extension\BoltAuth\Auth\Storage\Repository;

class Extension extends SimpleExtension
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
        $menu->setLabel("Alumnos")
            ->setIcon('fa:group')
            ->setPermission('settings');

        $tableMenu = MenuEntry::create('authdashboard-table', 'list');
        $tableMenu->setLabel('Listado')
            ->setIcon('fa:table')
            ->setPermission('settings');

        
        $dashboardMenu = MenuEntry::create('authdashboard-dashboard', 'dashboard');
        $dashboardMenu->setLabel('Dashboard')
            ->setIcon('fa:dashboard')
            ->setPermission('settings');
        
        $menu->add($tableMenu);
        $menu->add($dashboardMenu);
        
        return [ $menu ];
    }

    /**
     * {@inheritdoc}
     */
    protected function registerServices(Application $app)
    {
      
        $this->prefix = $app['config']->get('general/database/prefix', "to_");
        if ($this->prefix[strlen($this->prefix) - 1] != "_") {
            $this->prefix .= "_";
        }
      
        $app['twig'] = $app->extend(
            'twig',
            function (\Twig_Environment $twig) use ($app) {
                $config = $this->getConfig();
                $twig->addGlobal(self::APP_EXTENSION_KEY . '.config', $config);

                return $twig;
            }
        );
        $app[self::APP_EXTENSION_KEY . '.config'] = $app->share(function ($app) {
            return $this->getConfig();
        });

        $app[self::APP_EXTENSION_KEY . '.controller.backend'] = $app->share(
            function ($app) {
                return new BackendController($this->getWebDirectory());
            }
        );
        
        $app[self::APP_EXTENSION_KEY . '.service'] = $app->share(
            function () use ($app) {
              //dump($app['storage.content_repository']);die();
                //$cls = $app['storage.repositories']['Bolt\Extension\BoltAuth\Auth\Storage\Entity\Account'];
                $aux = new AuthService($app['storage']->getRepository('auth_account'));
                $aux->init($app['storage'], $app['logger.flash'], $app['config'], $app['url_generator']);
                return $aux;
            }
        );
    }


    /**
     * Fetches Provider entries by GUID.
     *
     * @param string $guid
     *
     * @return Entity\Provider[]
     */
    private function getProvisionsByGuid($guid)
    {
        $app = $this->getContainer();
        return $app['storage']->getRepository($this->prefix . 'auth_provider')->getProvisionsByGuid($guid);
    }

    /**
     * Return last seen date from all OAuth providers for an account
     *
     * @param string $guid
     *
     * @return \DateTime
     */
    public function getProviders($guid = null)
    {
        if ($guid === null) {
            $auth = $this->session->getAuthorisation();

            if ($auth === null) {
                return null;
            }
            $guid = $auth->getGuid();
        }
        $providerEntities = $this->getProvisionsByGuid($guid);
        if ($providerEntities === false) {
            return null;
        }

        /** @var Storage\Entity\Provider $providerEntity */
        $lastupdate = null;
        $result = null;
        $provider = [];
        foreach ($providerEntities as $providerEntity) {
            $provider['lastupdate'] = $providerEntity->getLastUpdate();
            if (!$result) {
              $result = $providerEntity;
            }
            
            if ($lastupdate <= $provider['lastupdate']) {
                $lastupdate = $provider['lastupdate'];
                $result = $providerEntity;
            }
        }

        return $result;
    }
    
    /**
     * {@inheritdoc}
     */
    protected function registerBackendControllers()
    {
        $app = $this->getContainer();
        $config = $this->getConfig();

        $baseUrl = Version::compare('3.2.999', '<')
            ? '/extensions/authdashboard'
            : '/extend/authdashboard'
        ;

        return [
            $baseUrl => $app[self::APP_EXTENSION_KEY . '.controller.backend']
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function registerTwigPaths()
    {
        return [
            'templates'       => ['position' => 'prepend', 'namespace' => 'bolt']
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function registerTwigFunctions()
    {
        return [
            'getUsers' => 'getUsers',
            'auth_provider' => 'getProviders'
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
        $app = $this->getContainer();
        /** @var \Bolt\Users $users */
        $users = $app[self::APP_EXTENSION_KEY . '.service']->getUsers();

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