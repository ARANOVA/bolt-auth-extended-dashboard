<?php

namespace Bolt\Extension\ARANOVA\AuthExtendedDashboard\Controller;

use Bolt\Controller\Base;
use Bolt\Controller\Zone;
use Silex\Application;
use Silex\ControllerCollection;
use Bolt\Asset\File\JavaScript;
use Bolt\Asset\File\Stylesheet;
use Bolt\Filesystem\Handler\DirectoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Pagerfanta\Exception\OutOfRangeCurrentPageException;
use Bolt\Extension\ARANOVA\AuthExtendedDashboard\Extension;
use Bolt\Version;

class BackendController extends Base
{
    
    /** @var Application */
    protected $container;

    /** @var DirectoryInterface */
    private $webPath;
    
    /**
     * Constructor.
     *
     * @param DirectoryInterface $webPath
     */
    public function __construct(DirectoryInterface $webPath)
    {
        $this->webPath = $webPath;
        $this->extensionBaseUrl = Version::compare('3.2.999', '<')
            ? '/extensions/authdashboard'
            : '/extend/authdashboard'
        ;
    }
    
    protected function addRoutes(ControllerCollection $c)
    {
        $c->value(Zone::KEY, Zone::BACKEND);

        $c->get('/usersQuery', [$this, 'getStatistics'])
            ->bind('getStatistics');
        
        $c->get('/list', [$this, 'usersList'])
            ->bind('usersList')
            ->before([$this, 'before']);

        $c->get('/exportList', [$this, 'usersExport'])
            ->bind('usersExport')
            ->before([$this, 'before']);

        $c->get('/dashboard', [$this, 'usersDashboard'])
            ->bind('usersDashboard')
            ->before([$this, 'before']);
        
        
        $c->before([$this, 'before']);
        
        return $c;
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
        $user = $app['users']->getCurrentUser();

        if ($user && $this->users()->isAllowed('dashboard')) {
            $this->addWebAssets($app, (strpos($request->getPathInfo(), '/dashboard') !== false));
            return null;
        }
        
        /** @var UrlGeneratorInterface $generator */
        $generator = $app['url_generator'];
        return new RedirectResponse($generator->generate('dashboard'), Response::HTTP_SEE_OTHER);
    }

    /**
     * Inject web assets for our route.
     *
     * @param Application $app
     */
    private function addWebAssets(Application $app, $isDashboard)
    {
        /** @var AuthExtension $extension */
        $extension = $app['extensions']->get('ARANOVA/AuthExtendedDashboard');
        $dir = '/' . $extension->getWebDirectory()->getPath();
        $assets = [];

        if ($isDashboard) {
          $assets = [
              (new Stylesheet($dir . '/dashboard.css'))->setZone(Zone::BACKEND)->setLate(false),
              (new Javascript($dir . '/dashboard.js'))->setZone(Zone::BACKEND)->setLate(false),
              //(new Javascript($dir . '/es6-promise.min.js'))->setZone(Zone::BACKEND)->setLate(false),
          ];
        }

        foreach ($assets as $asset) {
            $app['asset.queue.file']->add($asset);
        }
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

    /**
     * Returns JSON data
     *
     * @param Request $request
     * @param Application $app
     *
     * @return mixed
     */
    public function getStatistics(Request $request, Application $app)
    {
        $config = $app[Extension::APP_EXTENSION_KEY . '.config'];
        $service = $app[Extension::APP_EXTENSION_KEY . '.service'];
        
        $data = $service->getAccountStats($request);
        
        return new JsonResponse($data, Response::HTTP_OK);
        
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
        try {
            $queries = [
                'order' => $request->query->get('order', 'guid'),
                'search'  => $request->query->get('search'),
            ];
            
            try {
                $approved = $this->app['config']->get('general/tests/approved');
                /** @var Pager $userdata */
                if ($queries['search'] === null) {
                    $accounts = $app[Extension::APP_EXTENSION_KEY . '.service']->getAccounts($queries['order'], $approved);
                } else {
                    $accounts = $app[Extension::APP_EXTENSION_KEY . '.service']->searchAccounts($queries['search'], $queries['order'], $approved);
                }

                $accounts
                    ->setMaxPerPage(10)
                    ->setCurrentPage($request->query->getInt('page_alumnos', 1))
                    ->getCurrentPageResults()
                ;
            } catch (\Exception $e) {
                $this->handleException($app, $e);
                $orders = [];
            }
            $users = $accounts;
            
            if (!empty($users)) {
              $manager = $app['pager'];
              $manager->createPager('alumnos')
                  ->setCount($users->getNbResults())
                  ->setTotalPages($users->getNbPages())
                  ->setCurrent($users->getCurrentPage())
                  ->setShowingFrom($users->getCurrentPageOffsetStart())
                  ->setShowingTo($users->getCurrentPageOffsetEnd())
                  ->setFor('Alumnos');
            }
            
            /*
            $repo = $app['storage']->getRepository('auth_account');
            $repo->setPagerEnabled(true);
            $qb = $repo->createQueryBuilder('a');
            $qb->innerJoin('a', $this->prefix . 'auth_account_meta', 'am', 'a.guid = am.guid');
            $qb->innerJoin('a', $this->prefix . 'auth_provider', 'ap', 'a.guid = ap.guid');
            $qb->select(['a.*', "am.meta as meta", "am.value as value", "am.id as metaid", "ap.lastseen", "ap.created", "ap.createdBy as createdby"]);
            $qb->orderBy($queries['orderBy'], $queries['order']);
            $qb->setMaxResults(100);
            //echo nl2br(htmlentities($qb));
            $aux = $qb->execute()->fetchAll();
            $users = [];
            $guids = [];
            $approved = $this->app['config']->get('general/tests/approved');
            foreach ($aux as $data) {
                if (!array_key_exists($data['guid'], $guids)) {
                    $guids[$data['guid']] = [
                        'guid' => $data['guid'],
                        'email' => $data['email'],
                        'displayname' => $data['displayname'],
                        'enabled' => $data['enabled'],
                        'verified' => $data['verified'],
                        'roles' => $data['roles'],
                        'lastseen' => $data['lastseen'],
                        'created' => $data['created'],
                        'createdby' => $data['createdby'],
                        'tests' => $this->testResume($data['guid'], $approved, 'tests')
                    ];
                }
                $guids[$data['guid']][$data['meta']] = $data['value'];
            }
            foreach ($guids as $guid) {
                array_push($users, $guid);
            }
            */
        } catch (\Exception $e) {
            $this->handleException($app, $e);
            $users = [];
        }
       
        $html = $app['twig']->render($config['templates']['table'], [
            'title'       => 'Lista de alumnos',
            'users' => $users,
            'extensionBaseUrl' => $this->extensionBaseUrl,
            'webpath'     => $this->webPath->getPath(),
            'queries' => $queries,
            'pager'   => [
                'pager' => $manager->getPager('alumnos'),
                'surr'  => 4,
            ],
            'context' => [
                'contenttype' => [
                    'slug' => 'pager',
                ],
            ],
        ]);
        return new Response(new \Twig_Markup($html, 'UTF-8'));
    }

    private function plainkey($key, &$data)
    {
      if (!is_array($data)) {
        return [$key];
      }
      
        foreach (array_keys($data) as $nkey => $ndata) {
          $key .= implode('_', $this->plainkey($nkey, $ndata));
        }
      return [$key];
    }

    /**
     * Export user list to excel
     *
     * @param Request $request
     * @param Application $app
     *
     * @return mixed
     */
    public function usersExport(Request $request, Application $app)
    {
        ini_set('memory_limit', '-1');
        $filename = "usersdata_" . date('Y-m-d_H-i') . ".xls";
        //$response = new StreamedResponse();
        $response = new Response();
        $response->headers->set('Content-Type', 'application/vnd.ms-excel');
        $response->headers->set('Content-Disposition', "attachment; filename=\"$filename\"");
        $response->setCharset('UTF-8');
        try {
            $approved = $this->app['config']->get('general/tests/approved');
            /** @var Pager $userdata */
            $accounts = $app[Extension::APP_EXTENSION_KEY . '.service']->getAccounts('created', $approved);
            $usernames = $app[Extension::APP_EXTENSION_KEY . '.service']->getUsers();
            $users = $accounts
                ->setMaxPerPage(10000)
                ->setCurrentPage($request->query->getInt('page_alumnos', 1))
                ->getCurrentPageResults();
            $handle = fopen('php://memory', 'r+');
            // Add CSV headers
            $user = $users[0];
            $headers = [];
            $columns = array_keys($user);
            foreach ($user as $key => $data) {
              if (!is_array($data)) {
                array_push($headers, $key);
              } else {
                foreach ($data as $n2key => $n2data) {
                  array_push($headers, $key . "_" . $n2key);
                }
              }
            }
            fputcsv($handle, $headers);
            foreach ($users as $user) {
              $row = [];
              foreach ($columns as $key) {
                if (!array_key_exists($key, $user)) {
                  array_push($row, '');
                  continue;
                }
                if (!is_array($user[$key])) {
                  if ($key == 'createdby' && array_key_exists($user[$key], $usernames)) {
                    array_push($row, $usernames[$user[$key]]);
                  } else {
                    array_push($row, $user[$key]);
                  }
                } else {
                  foreach ($user[$key] as $n2key => $n2data) {
                    array_push($row, $n2data);
                  }
                }
              }
              fputcsv($handle, $row);
            }
            rewind($handle);
            $content = stream_get_contents($handle);
            // Close the output stream
            fclose($handle);
            $response->setStatusCode(Response::HTTP_OK);
            $response->setContent($content);
        } catch (\Exception $e) {
            print_r($e);die();
            $this->handleException($app, $e);
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);
        } finally {
            return $response;
        }
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
        $html = $app['twig']->render($config['templates']['dashboard'], [
            'title'       => 'Dashboard',
            'webpath'     => $this->webPath->getPath(),
        ]);
        return new Response(new \Twig_Markup($html, 'UTF-8'));
    }
    
}
