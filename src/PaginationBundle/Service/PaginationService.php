<?php

namespace App\PaginationBundle\Service;

use App\PaginationBundle\Model\PaginationResult;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\HttpFoundation\Request;

final class PaginationService
{
    public function getPageFromRequest(Request $request, string $parameter = 'page'): int
    {
        $raw = (string) $request->query->get($parameter, '1');
        $page = (int) $raw;

        return max(1, $page);
    }

    public function paginateQueryBuilder(QueryBuilder $queryBuilder, int $page, int $perPage): PaginationResult
    {
        $safePerPage = max(1, $perPage);
        $safePage = max(1, $page);

        $countQb = clone $queryBuilder;
        $countQb->resetDQLPart('orderBy')
            ->select('COUNT(p.id)');

        $totalItems = (int) $countQb->getQuery()->getSingleScalarResult();
        $totalPages = max(1, (int) ceil($totalItems / $safePerPage));
        $currentPage = min($safePage, $totalPages);

        $offset = ($currentPage - 1) * $safePerPage;

        $itemsQb = clone $queryBuilder;
        $itemsQb->setFirstResult($offset)
            ->setMaxResults($safePerPage);

        $paginator = new Paginator($itemsQb, true);
        $items = iterator_to_array($paginator, false);

        return new PaginationResult(
            $items,
            $totalItems,
            $currentPage,
            $safePerPage,
            $totalPages
        );
    }
}

