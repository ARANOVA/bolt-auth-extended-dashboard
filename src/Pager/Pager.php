<?php

namespace Bolt\Extension\ARANOVA\AuthExtendedDashboard\Pager;

use Bolt\Storage\Entity\Builder;
use Pagerfanta\Adapter\AdapterInterface;
use Pagerfanta\Pagerfanta;

/**
 * Pager for auth data.
 *
 * Copyright (C) 2014-2016 Gawain Lynch
 * Copyright (C) 2017 Svante Richter
 *
 * @author    Gawain Lynch <gawain.lynch@gmail.com>
 * @copyright Copyright (c) 2014-2016, Gawain Lynch
 *            Copyright (C) 2017 Svante Richter
 * @license   https://opensource.org/licenses/MIT MIT
 */
class Pager extends Pagerfanta
{
    /** @var Builder */
    private $builder;
    /** @var bool */
    private $built;
    
    private $storage; 

    private $approved;


    /**
     * {@inheritdoc}
     */
    public function __construct(AdapterInterface $adapter, $storage, $approved)
    {
        parent::__construct($adapter);

        $this->builder = $storage->getRepository('auth_account')->getEntityBuilder();
        
        $this->storage = $storage;
        $this->approved = $approved;
    }
    
    private function videoResume($guid)
    {
        $res = array(
            'principiante'  => 0,
            'avanzado'      => 0,
            'medio'         => 0,
            'teoria'        => 0
        );
        $videos = $this->getVideoResults($guid);
        foreach ($videos as $video) {
          $res[$video['filtertaxonomy']] = $video['total_videos'];
        }
        return $res;
    }

    private function getVideoResults($guid)
    {
        $repo = $this->storage->getRepository('userdata');
        $qb = $repo->createQueryBuilder();
        $qb->select(['filtertaxonomy', 'COUNT(content_id) AS total_videos']);
        $qb->where('contenttype="videos" and auth_user_id="' . $guid . '"');
        $qb->groupBy('filtertaxonomy');
        $res = $qb->execute()->fetchAll();
        return $res;
    }

    private function parseTest($test)
    {
        $aux = json_decode($test['result'], true);
        $test['ok'] = array_key_exists('ok', $aux) ? $aux['ok'] : [];
        $test['fail'] = array_key_exists('fail', $aux) ? $aux['fail'] : [];
        $test['uncompleted'] = array_key_exists('uncompleted', $aux) ? $aux['uncompleted'] : [];
        $test['total'] = count($test['fail']) + count($test['ok']) + count($test['uncompleted']);
        $test['percent'] = round((count($test['ok']) / $test['total'])*100);
        return $test;
    }

    private function testResume($guid, $approved, $filtertaxonomy= null)
    {
        $res = array(
            'ok' 		  => 0,
            'fail'	  => 0
        );
        $tests = $this->getTestResults($guid, $filtertaxonomy, null, "-created", true, true);
        foreach ($tests as $test) {
          $test = $this->parseTest($test);
          if ($test['percent'] > intval($approved)) {
				    $res['ok']++;
          } else {
				    $res['fail']++;
          }
        }
        return $res;
    }
    
    private function getTestResults($guid, $filtertaxonomy = null, $limit = null, $order = null, $full = false, $unique = false)
    {
        $repo = $this->storage->getRepository('userdata');
        $qb = $repo->createQueryBuilder();
        $where = '';
        if ($filtertaxonomy) {
            $where = ' and filtertaxonomy="' . $filtertaxonomy . '"';
        }
        $qb->where('contenttype="tests" and auth_user_id="' . $guid . '"' . $where);
        if ($limit) {
            $qb->setMaxResults($limit);
        }
        if ($order) {
            $qb->orderBy($order);
        } else {
            $qb->orderBy('-created');
        }
        $res = $qb->execute()->fetchAll();
        if (!$res) {
            return [];
        }
        if (!$full) {
            $results = [];
            foreach ($res as $result) {
                array_push($results, $result['content_id']);
            }
            $results = array_unique($results);
        } else {
            if ($limit == 1) {
                $results = $res[0];
            } else {
                if ($unique) {
                    $results = [];
                    $uniques = [];
                    foreach ($res as $result) {
                        if (!in_array($result['content_id'], $uniques)) {
                            array_push($results, $result);
                            array_push($uniques, $result['content_id']);
                        }
                    }
                } else {
                    $results = $res;
                }
					
            }
        }
        return $results;
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrentPageResults()
    {
        $results = parent::getCurrentPageResults();
        if ($this->built === null) {
            foreach ($results as $key => $data) {
                $entity = $this->builder->getEntity();
                $this->builder->createFromDatabaseValues($data, $entity);
                // Recuperar metas=
                $repo = $this->storage->getRepository('auth_account_meta');
                $qb = $repo->createQueryBuilder('am');
                $qb->select(["meta", "value", "id as metaid"]);
                $qb->where('guid = :guid');
                $qb->setParameter('guid', $results[$key]['guid']);
                $aux = $qb->execute()->fetchAll();
                $metas = [];
                foreach ($aux as $data) {
                  $metas[$data['meta']] = $data['value'];
                }
                $results[$key] = array_merge(
                  $results[$key],
                  $metas,
                  ['tests' => $this->testResume($results[$key]['guid'], $this->approved, 'tests')],
                  ['videos' => $this->videoResume($results[$key]['guid'])]
                );
                //$results[$key] = $entity;
            }
            $this->setCurrentPageResults($results);
            $this->built = true;
        }

        return $results;
    }

    /**
     * @param array|\Traversable $currentPageResults
     */
    private function setCurrentPageResults($currentPageResults)
    {
        $reflection = new \ReflectionClass($this);
        $prop = $reflection->getParentClass()->getProperty('currentPageResults');
        $prop->setAccessible(true);
        $prop->setValue($this, $currentPageResults);
    }
}
