<?php

namespace Wealthbot\AdminBundle\Repository;

use Doctrine\ORM\EntityRepository;

/**
 * AssetClassRepository.
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class AssetClassRepository extends EntityRepository
{
    public function findAdminAssetsWithJoinedSubclassesByModelId($modelId)
    {
        return $this->createQueryBuilder('ac')
            ->select('ac', 's')
            ->leftJoin('ac.subclasses', 's')
            ->where('ac.model_id = :model_id')
            ->andWhere('s.owner_id IS NULL')
            ->setParameter('model_id', $modelId)
            ->getQuery()
            ->execute();
    }

    public function getAssetClassesForModelQB($modelId)
    {
        $qb = $this->createQueryBuilder('ac')
            ->andWhere('ac.model_id = :model_id')
            ->setParameter('model_id', $modelId)
            ->orderBy('ac.id', 'ASC');

        return $qb;
    }

    public function findByModelIdAndOwnerId($modelId, $ownerId = null)
    {
        $qb = $this->createQueryBuilder('ac')
            ->select('ac', 's')
            ->leftJoin('ac.subclasses', 's')
            ->where('ac.model_id = :model_id')
            ->setParameter('model_id', $modelId);

        return $qb->getQuery()->getResult();
    }

    public function findWithSubclassesByModelIdAndOwnerId($modelId, $ownerId = null)
    {
        $qb = $this->createQueryBuilder('ac')
            ->select('ac', 's')
            ->leftJoin('ac.subclasses', 's')
            ->where('ac.model_id = :model_id')
            ->setParameter('model_id', $modelId);

        if (null !== $ownerId) {
            $qb->andWhere('s.owner_id = :owner_id')
                ->setParameter('owner_id', $ownerId);
        } else {
            $qb->andWhere('s.owner_id IS NULL');
        }

        return $qb->getQuery()->getResult();
    }
}