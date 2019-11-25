<?php

namespace Bolt\Extension\ARANOVA\AuthExtendedDashboard\Service;

use Carbon\Carbon;
use Pimple as Container;
use Bolt\Storage\Repository;
use Symfony\Component\HttpFoundation\Request;
use Bolt\Extension\ARANOVA\AuthExtendedDashboard\Pager;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Pagerfanta\Exception\OutOfRangeCurrentPageException;
use Doctrine\DBAL\Query\QueryBuilder;
use Pagerfanta\Adapter\DoctrineDbalAdapter;
use Bolt\Extension\ARANOVA\AuthExtendedDashboard\Storage\Repository\AbstractRepository;

/**
 * Auth records.
 *
 * Copyright (C) 2019 Pablo Sánchez
 * Copyright (C) 2019 ARANOVA
 *
 * @author    Pablo Sánchez <pablo.sanchez@aranova.es>
 * @copyright Copyright (c) 2019 Pablo Sánchez
 *            Copyright (C) 2019 ARANOVA
 * @license   https://opensource.org/licenses/MIT MIT
 */


class AuthService extends AbstractRepository
{
  
    const ALIAS = 'a';
  
    /** @var Container */
    private $storage;
    
    private $logger;
    
    private $prefix;
    
    private $url_generator;

    /**
     * Init
     */
    public function init($storage, $logger, $config)
    {
        $this->storage = $storage;
        $this->logger = $logger;
        $this->config = $config;
        $this->prefix = $this->config->get('general/database/prefix', "to_");
        if ($this->prefix[strlen($this->prefix) - 1] != "_") {
            $this->prefix .= "_";
        }
        
    }

    /**
     * @param \Exception  $e
     *
     * @throws \Exception
     */
    protected function handleException(\Exception $e)
    {
        if ($e instanceof TableNotFoundException) {
            $msg = sprintf('User orders database tables have not been created! Please <a href="%s">update your database</a>.', $this->url_generator->generate('dbcheck'));
            $this->logger->error($msg);
        } elseif ($e instanceof OutOfRangeCurrentPageException) {
            $this->logger->error('Page does not exist');
        } else {
            throw $e;
        }
    }

    private function dateDifference($date_1 , $date_2 , $differenceFormat = '%a' )
    {
        $datetime1 = date_create($date_1);
        $datetime2 = date_create($date_2);
   
        $interval = date_diff($datetime1, $datetime2);
   
        return $interval->format($differenceFormat);
   
    }
    
    private function dateGreater($date_1 , $date_2 , $differenceFormat = '%a' )
    {
        $datetime1 = date_create($date_1);
        $datetime2 = date_create($date_2);
   
        $interval = date_diff($datetime1, $datetime2);
   
        return $interval->invert;
   
    }
    
    public function getUsers() {
      $repo = $this->getRepository('users');
      $qb = $repo->createQueryBuilder();
      $qb->select('id, username');
      $users = [];
      foreach ($qb->execute()->fetchAll() as $reg) {
        $users[$reg['id']] = $reg['username'];
      };
      return $users;
    }

    public function getAccountStats(Request $request)
    {
      $current = $request->query->get('current');
      $compare = $request->query->get('compare');
      $dimension = $request->query->get('dimension');
      $unit = $request->query->get('unit');
      
      $data = [[]];
      if ($compare) {
        array_push($data, []);
      }

      if ($dimension == 'newUsers') {
        $data[0] = $this->getUsersStats($request, $current, $unit, 'created');
        $data[1] = $this->getUsersStats($request, $compare, $unit, 'created');
      } elseif ($dimension == 'activeUsers') {
        $data[0] = $this->getUsersStats($request, $current, $unit, 'lastseen');
        $data[1] = $this->getUsersStats($request, $compare, $unit, 'lastseen');
      } elseif ($dimension == 'payedUsers') {
        $data = $this->getUsersMetaStats($request);
      } elseif ($dimension == 'roleUsers') {
        $data = $this->getUsersRoleStats($request);
      } elseif ($dimension == 'createdByUsers') {
        $data = $this->getUsersCreatedStats($request);
        $data = ['data' => $data[0], 'labels' => $data[1]]; 
      }
      return $data;
    }
    
    private function getUsersStats($request, $range, $differenceFormat = '%a', $field = 'created')
    {
        try {
            $days = (1+$this->dateDifference($range['end-date'], $range['start-date'], $differenceFormat));
            $compare = $request->query->get('compare');
            $repo = $this->getRepository();
            $qb = $repo->createQueryBuilder('a');
            $qb->innerJoin('a', $this->prefix . 'auth_provider', 'ap', 'a.guid = ap.guid');
            $qb->select([$field, 'COUNT(DISTINCT a.guid) AS total_users']);
            $qb->orderBy($field);
            //$qb->setMaxResults(1);
            $qb->where('ap.' . $field . ' >= :from');
            $qb->setParameter('from', $range['start-date']);
            $qb->andWhere('ap. ' . $field . ' <= :to');
            $qb->setParameter('to', $range['end-date']);
            $qb->groupBy('ap.' . $field);
            $aux = $qb->execute()->fetchAll();
            //echo nl2br(htmlentities($qb));
            $result = [];
            $days = (1+$this->dateDifference($range['end-date'], $range['start-date'], $differenceFormat));
            for ($i = 0; $i<$days; $i++) {
              $result[$i] = 0;
            }
            foreach ($aux as $reg) {
              $d = date_create($reg[$field]);
              $day = $this->dateDifference($reg[$field], $range['start-date'], $differenceFormat);
              $result[$day] += $reg['total_users'];
            }
          } catch (\Exception $e) {
            $this->handleException($e);
            $result = [];
            $days = (1+$this->dateDifference($range['end-date'], $range['start-date'], $differenceFormat));
            for ($i = 0; $i<$days; $i++) {
              $result[$i] = null;
            }
        }
        return $result;
    }
    
    private function getUsersMetaStats(Request $request)
    {
        try {
            $repo = $this->getRepository();
            $qb = $repo->createQueryBuilder('a');
            $qb->innerJoin('a', $this->prefix . 'auth_account_meta', 'am', 'a.guid = am.guid');
            $qb->select(['am.value', 'COUNT(a.guid) AS total_users']);
            $qb->orderBy('am.value');
            $qb->where('am.meta = :meta');
            $qb->setParameter('meta', 'limitdate');
            //$qb->setMaxResults(1);
            $qb->groupBy('am.value');
            $aux = $qb->execute()->fetchAll();
            //echo nl2br(htmlentities($qb));
            $result = [0, 0, 0];
            $d = date('Y-m-d');
            foreach ($aux as $reg) {
              $key = 0;
              if ($reg['value'] != null) {
                $key = 2;
                $diff = $this->dateGreater($d, $reg['value'], '%a');
                if ($diff > 0) {
                  $key = 1;
                }
              }
              $result[$key] += $reg['total_users'];
            }
          } catch (\Exception $e) {
            $this->handleException($e);
            $result = [null, null, null];
        }
        return $result;
    }
    
    private function getUsersRoleStats(Request $request)
    {
        try {
            $repo = $this->getRepository();
            $qb = $repo->createQueryBuilder('a');
            $qb->select(['a.roles', 'COUNT(a.roles) AS total_users']);
            $qb->groupBy('a.roles');
            $aux = $qb->execute()->fetchAll();
            //print_r($aux);
            //echo nl2br(htmlentities($qb));
            $result = [0, 0, 0, 0];
            foreach ($aux as $reg) {
              $key = 0;
              if (gettype($reg['roles']) == 'string') {
                $reg['roles'] = json_decode($reg['roles'], true);
              } elseif (!$reg['roles']) {
                $reg['roles'] = [];
              }
              if ($reg['roles'] != null) {
                if (in_array('alumnok', $reg['roles'])) {
                  $key = 1;  
                } elseif (in_array('alumno', $reg['roles'])) {
                  $key = 2;  
                } elseif (in_array('demo', $reg['roles'])) {
                  $key = 3;  
                }
              }
              $result[$key] += $reg['total_users'];
            }
          } catch (\Exception $e) {
            $this->handleException($e);
            $result = [null, null, null, null];
        }
        return $result;
    }
    
    private function getUsersCreatedStats(Request $request)
    {
        try {
            $field = 'createdBy';
            $repo = $this->getRepository();
            $qb = $repo->createQueryBuilder('a');
            $qb->innerJoin('a', $this->prefix . 'auth_provider', 'ap', 'a.guid = ap.guid');
            $qb->select([$field, 'COUNT(DISTINCT a.guid) AS total_users']);
            $qb->orderBy($field);
            //$qb->setMaxResults(1);
            $qb->groupBy('ap.' . $field);
            $aux = $qb->execute()->fetchAll();
            //echo nl2br(htmlentities($qb));
            $r = [];
            foreach ($aux as $reg) {
              $key = $reg[$field];
              if (!$key) {
                $key = 0;
              }
              if (!array_key_exists($key, $r)) {
                $r[$key] = 0;
              }
              $r[$key] += $reg['total_users'];
            }
            $result = [];
            $labels = [];
            $ids = [];
            foreach (array_keys($r) as $key) {
              if ($key != 0) {
                array_push($ids, $key);
              }
              array_push($result, $r[$key]);
            }
            // Get Users
            if (count($ids) > 0) {
              array_push($labels, 'Desconocido / Web');
              $repouser = $this->getRepository('users');
              $qb = $repouser->createQueryBuilder('u');
              $qb->select(['id', 'username']);
              $qb->where("id in ('" . implode("','", $ids) . "')");
              $aux = $qb->execute()->fetchAll();
              foreach ($aux as $reg) {
                array_push($labels, $reg['username']);
              }
            }
          } catch (\Exception $e) {
            $this->handleException($e);
            $result = [];
            $labels = [];
        }
        return [$result, $labels];
    }

    /**
     * Fetch all user orders.
     *
     * @param string $orderBy
     * @param string $order
     *
     * @return Entity\Account[]
     */
    public function getAccounts($order = 'created', $approved)
    {
        $dir = 'ASC';
        if ($order[0] == '-') {
          $dir = 'DESC';
          $order = substr($order, 1);
        }
      
        if (in_array($order, ['created', 'lastseen'])) {
          $order = 'ap.' . $order;
        } elseif (in_array($order, ['createdby'])) {
            $order = 'ap.createdBy';
        }
        $repo = $this->getRepository('auth_account');
        $repo->setPagerEnabled(true);
        $qb = $repo->createQueryBuilder('a');
        //$qb->innerJoin('a', $this->prefix . 'auth_account_meta', 'am', 'a.guid = am.guid');
        $qb->leftJoin('a', $this->prefix . 'auth_provider', 'ap', 'a.guid = ap.guid');
        //$qb->select(['a.*', "am.meta as meta", "am.value as value", "am.id as metaid", "ap.lastseen", "ap.created", "ap.createdBy as createdby"]);
        //$qb->select(['a.*', 'ap.lastseen as lastseen', 'ap.created as created']);
        $qb->select(['a.*', "ap.lastseen", "ap.created", "ap.createdBy as createdby"]);
        $qb->orderBy($order, $dir);
        //echo nl2br(htmlentities($qb));
        return $this->getPager($qb, 'guid', $this->storage, $approved);
    }

    /**
     * Search for user orders.
     *
     * @param string $term
     * @param string $orderBy
     * @param null   $order
     *
     * @return array|\Bolt\Extension\ARANOVA\AuthExtendedDashboard\Pager\Pager
     */
    public function searchAccounts($term, $order, $approved)
    {
        $dir = 'ASC';
        if ($order[0] == '-') {
          $dir = 'DESC';
          $order = substr($order, 1);
        }
    
        if (in_array($order, ['created', 'lastseen'])) {
          $order = 'ap.' . $order;
        } elseif (in_array($order, ['createdby'])) {
            $order = 'ap.createdBy';
        }
        $repo = $this->getRepository('auth_account');
        $repo->setPagerEnabled(true);
        $qb = $repo->createQueryBuilder('a');
        $qb->leftJoin('a', $this->prefix . 'auth_provider', 'ap', 'a.guid = ap.guid');
        $qb->select(['a.*', "ap.lastseen", "ap.created", "ap.createdBy as createdby"]);
        $qb->orderBy($order, $dir);
        
        $qb->where($qb->expr()->like('displayname', ':term'))
            ->orWhere($qb->expr()->like('a.guid', ':term'))
            ->orWhere($qb->expr()->like('email', ':term'))
            ->orWhere($qb->expr()->like('roles', ':term'))
            ->setParameter('term', '%' . $term . '%')
        ;
        
        //echo nl2br(htmlentities($qb));
        return $this->getPager($qb, 'guid', $this->storage, $approved);
    }

    /**
     * @return Repository\UserOrder
     */
    protected function getRepository($reponame = 'auth_account')
    {
        return $this->storage->getRepository($reponame);
    }

}
