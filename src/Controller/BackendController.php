<?php

namespace Bolt\Extension\ARANOVA\AuthExtendedDashboard\Controller;

use Bolt\Controller\Zone;
use Silex\Application;
use Silex\ControllerCollection;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Pagerfanta\Exception\OutOfRangeCurrentPageException;
use Bolt\Version;
use Bolt\Extension\ARANOVA\AuthExtendedDashboard\Extension;

class BackendController implements ControllerProviderInterface
{
    
    /** @var Application */
    protected $container;

    /**
     * Returns routes to connect to the given application.
     *
     * @param Application $app An Application instance
     *
     * @return ControllerCollection A ControllerCollection instance
     */
    public function connect(Application $app)
    {
        /** @var $ctr ControllerCollection */
        $this->container = $app;
        $ctr = $app['controllers_factory'];
        $ctr->value(Zone::KEY, Zone::BACKEND);

        $this->extensionBaseUrl = Version::compare('3.2.999', '<')
            ? '/extensions'
            : '/extend'
        ;

        $ctr->match($this->extensionBaseUrl . '/authdashboard/table', [$this, 'usersList'])
            ->bind('usersList')
            ->method(Request::METHOD_GET);

        $ctr->match($this->extensionBaseUrl . '/authdashboard/dashboard', [$this, 'usersDashboard'])
            ->bind('usersDashboard')
            ->method(Request::METHOD_GET);
        
        
        $ctr->before([$this, 'before']);
        
        return $ctr;
    }


    /**
     * Simple check if user is logged in
     *
     * @param Request $request
     * @param Application $app
     *
     * @return null|RedirectResponse
     */
    public function before(Request $request, Application $app)
    {
        $this->prefix = $app['config']->get('general/database/prefix', "to_");
        if ($this->prefix[strlen($this->prefix) - 1] != "_") {
            $this->prefix .= "_";
        }

        $user = $app['users']->getCurrentUser();

        if ($user) {
            return null;
        }
        
        /** @var UrlGeneratorInterface $generator */
        $generator = $app['url_generator'];
        return new RedirectResponse($generator->generate('dashboard'), Response::HTTP_SEE_OTHER);
    }

    /**
     * @param Application $app
     * @param \Exception  $e
     *
     * @throws \Exception
     */
    protected function handleException(Application $app, \Exception $e)
    {
        if ($e instanceof TableNotFoundException) {
            $msg = sprintf('User orders database tables have not been created! Please <a href="%s">update your database</a>.', $app['url_generator']->generate('dbcheck'));
            $app['logger.flash']->error($msg);
        } elseif ($e instanceof OutOfRangeCurrentPageException) {
            $app['logger.flash']->error('Page does not exist');
        } else {
            throw $e;
        }
    }

    private function getUsers(Request $request, Application $app) {
        try {
            $queries = [
                'orderBy' => $request->query->get('orderby', 'guid'),
                'order'   => $request->query->get('order'),
                'search'  => $request->query->get('search'),
            ];
            $repo = $app['storage']->getRepository('auth_account');
            $qb = $repo->createQueryBuilder('a');
            $qb->innerJoin('a', $this->prefix . 'auth_account_meta', 'am', 'a.guid = am.guid');
            $qb->select(['a.*', "am.meta as meta", "am.value as value", "am.id as metaid"]);
            $qb->orderBy($queries['orderBy'], $queries['order']);
            $aux = $qb->execute()->fetchAll();
            $users = [];
            $guids = [];
            foreach ($aux as $data) {
                if (!array_key_exists($data['guid'], $guids)) {
                    $guids[$data['guid']] = [
                        'guid' => $data['guid'],
                        'email' => $data['email'],
                        'displayname' => $data['displayname'],
                        'enabled' => $data['enabled'],
                        'verified' => $data['verified'],
                        'roles' => $data['roles'],
                    ];
                }
                $guids[$data['guid']][$data['meta']] = $data['value'];
            }
            foreach ($guids as $guid) {
                array_push($users, $guid);
            }
        } catch (\Exception $e) {
            $this->handleException($app, $e);
            $users = [];
        }
        return $users;
    }
    /**
     * Render users list page
     *
     * @param Request $request
     * @param Application $app
     *
     * @return mixed
     */
    public function usersList(Request $request, Application $app)
    {
        $config = $app[Extension::APP_EXTENSION_KEY . '.config'];
        $users = $this->getUsers($request, $app);
        //$pager = new PagerEntity();
        
        $html = $app['twig']->render($config['templates']['table'], [
            'title' => 'List',
            'users' => $users
        ]);
        return new Response(new \Twig_Markup($html, 'UTF-8'));
    }

    /**
     * Render users dashboard page
     *
     * @param Request $request
     * @param Application $app
     *
     * @return mixed
     */
    public function usersDashboard(Request $request, Application $app)
    {
        $config = $app[Extension::APP_EXTENSION_KEY . '.config'];
        // Obtener datos más estadísticos (COUNT, etc)
        $users = $this->getUsers($request, $app);
        $html = $app['twig']->render($config['templates']['dashboard'], [
            'title' => 'Dashboard',
            'users' => $users
        ]);
        return new Response(new \Twig_Markup($html, 'UTF-8'));
    }
    
}
