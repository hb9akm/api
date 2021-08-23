<?php declare(strict_types=1);

namespace HB9AKM\API\V2\Model\Repository;

use Psr\Http\Message\ResponseInterface as ServerResponse;
use HB9AKM\API\V2\Model\Entity\Request as Request;
use HB9AKM\API\V2\Model\Entity\Response as Response;

abstract class AbstractRepository extends \Doctrine\ORM\EntityRepository {
    public static array $knownFilters = array('eq', 'neq', 'lt', 'lte', 'in', 'gt', 'gte');

    public function handleRequest(
        \HB9AKM\API\Main $main,
        Request $request,
        Response $response,
        $args
    ): ServerResponse {
        $this->getByRequest($request, $response);

        // TODO: Cache the $response

        return $response->getServerResponse();
    }

    protected function getByRequest(Request $request, Response $response): void {
        $qb = $this->_em->createQueryBuilder();

        // first, count all
        $qb->select('count(e.id)');
        $qb->from($this->_entityName, 'e');
        $response->setTotalCount((int) $qb->getQuery()->getSingleScalarResult());

        // then, count filtered
        $i = 1;
        foreach ($request->getFilter() as $fieldname=>$filterInfo) {
            foreach ($filterInfo as $operation=>$value) {
                $qb->andWhere($qb->expr()->$operation('e.' . $fieldname, '?' . $i));
                $qb->setParameter($i, $value);
                $i++;
            }
        }
        $response->setTotalFilteredCount((int) $qb->getQuery()->getSingleScalarResult());

        // then request limited result
        $qb->select('e');
        foreach ($request->getSorting() as $fieldname=>$order) {
            $qb->orderBy('e.' . $fieldname, $order);
        }
        $qb->setMaxResults($request->getLimit());
        $qb->setFirstResult($request->getOffset());
        $result = $qb->getQuery()->getResult();
        $response->setEntities($result);
    }
}
