<?php

namespace Bolt\Extension\ARANOVA\AuthExtendedDashboard\Storage\Repository;

use Bolt\Extension\ARANOVA\AuthExtendedDashboard\Pager;
use Bolt\Storage\Repository;
use Doctrine\DBAL\Query\QueryBuilder;
use Pagerfanta\Adapter\DoctrineDbalAdapter;

/**
 * Base repository for RegisterUserdata.
 *
 * Copyright (C) 2018 Pablo Sánchez
 * Copyright (C) 2018 ARANOVA
 *
 * @author    Pablo Sánchez <pablo.sanchez@aranova.es>
 * @copyright Copyright (c) 2018 Pablo Sánchez
 *            Copyright (C) 2018 ARANOVA
 * @license   https://opensource.org/licenses/MIT MIT
 */
abstract class AbstractRepository
{
    const ALIAS = null;

    /** @var bool */
    protected $pagerEnabled;
    /** @var Pager\Pager */
    protected $pager;

    /**
     * {@inheritdoc}
     */
    public function createQueryBuilder($alias = null)
    {
        $queryBuilder = $this->em->createQueryBuilder()
            ->from($this->getTableName(), static::ALIAS)
        ;

        return $queryBuilder;
    }

    /**
     * @return boolean
     */
    public function isPagerEnabled()
    {
        return $this->pagerEnabled;
    }

    /**
     * @param boolean $pagerEnabled
     *
     * @return AbstractAuthRepository
     */
    public function setPagerEnabled($pagerEnabled)
    {
        $this->pagerEnabled = $pagerEnabled;

        return $this;
    }

    /**
     * @param QueryBuilder $query
     * @param string       $column
     *
     * @return Pager\Pager
     */
    public function getPager(QueryBuilder $query, $column, $storage, $approved)
    {
        if ($this->pager === null) {
            $countField = static::ALIAS . '.' . $column;
            $select = $this->createSelectForCountField($countField);
            $callback = function (QueryBuilder $queryBuilder) use ($select, $countField) {
                $queryBuilder
                    ->select($select)
                    ->orderBy(1)
                    ->setMaxResults(1)
                ;
            };

            $adapter = new DoctrineDbalAdapter($query, $callback);
            $this->pager = new Pager\Pager($adapter, $storage, $approved);
        }

        return $this->pager;
    }

    private function createSelectForCountField($countField)
    {
        return sprintf('COUNT(DISTINCT %s) AS total_results', $countField);
    }
}
