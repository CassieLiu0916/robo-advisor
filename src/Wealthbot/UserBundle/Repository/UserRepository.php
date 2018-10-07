<?php

namespace Wealthbot\UserBundle\Repository;

use Doctrine\ORM\EntityRepository;
use Wealthbot\ClientBundle\Entity\AccountGroup;
use Wealthbot\ClientBundle\Entity\ClientAccount;
use Wealthbot\ClientBundle\Entity\ClientPortfolio;
use Wealthbot\ClientBundle\Model\SystemAccount;
use Wealthbot\RiaBundle\Entity\RiaCompanyInformation;
use Wealthbot\UserBundle\Entity\Profile;
use Wealthbot\UserBundle\Entity\User;

/**
 * UserRepository.
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class UserRepository extends EntityRepository
{
    public function findAllClients()
    {
        $qb = $this->createQueryBuilder('cu')
            ->where('cu.roles LIKE :role')
            ->setParameter('role', '%"ROLE_CLIENT"%');

        return $qb->getQuery()->getResult();
    }

    public function getClientsOrderedById($limit = null, $order = 'DESC')
    {
        $query = $this->createQueryBuilder('cu')
            ->select('cu as user', 'cup', 'cp', 'cpi', 'COUNT(f.id) as nb_funds')
            ->leftJoin('cu.profile', 'cup')
            ->leftJoin('cu.clientAccounts', 'cca')
            ->leftJoin('cu.clientPortfolios', 'cp')
            ->leftJoin('cu.clientPersonalInformation', 'cpi')
            ->leftJoin('cca.accountOutsideFunds', 'f')
            ->where('cu.roles LIKE :role')
            ->setParameter('role', '%"ROLE_CLIENT"%')
            ->groupBy('cu.id')
            ->orderBy('cu.id', $order);

        if (!is_null($limit)) {
            $query->setMaxResults($limit);
        }

        return $query->getQuery()->getResult();
    }

    public function getUsersByRole($role, $limit = null, $order = 'DESC')
    {
        $query = $this->createQueryBuilder('u');
        $query
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%"'.$role.'"%')
            ->orderBy('u.id', $order);

        if (!is_null($limit)) {
            $query->setMaxResults($limit);
        }

        return $query->getQuery()->getResult();
    }

    public function getUserByIdAndRoles($id, $roles = [])
    {
        $query = $this->createQueryBuilder('u');

        if (is_array($roles) && !empty($roles)) {
            $strRoles = '';
            foreach ($roles as $role) {
                if ($strRoles === '') {
                    $strRoles .= 'u.roles LIKE :'.$role.' ';
                } else {
                    $strRoles .= 'OR u.roles LIKE :'.$role.' ';
                }

                $query
                        ->setParameter($role, '%"'.$role.'"%');
            }
            $query->where($strRoles);
        }

        $query
            ->andWhere('u.id = :id')
            ->setParameter('id', $id)
            ->setMaxResults(1);

        return $query->getQuery()->getOneOrNullResult();
    }

    public function findClientsByRiaIdQuery($riaId, $searchStr = null)
    {
        $query = $this->createQueryBuilder('cu');
        $query->select('cu', 'cup', 's')
            ->leftJoin('cu.profile', 'cup')
            ->leftJoin('cup.state', 's')
            ->where('cup.ria_user_id = :ria_id')
            ->andWhere('cu.roles LIKE :roles')
            ->setParameter('ria_id', $riaId)
            ->setParameter('roles', '%ROLE_CLIENT%');

        if ($searchStr) {
            $query
                ->andWhere('cup.first_name LIKE :searchStr OR cup.last_name LIKE :searchStr')
                ->setParameter('searchStr', '%'.$searchStr.'%');
        }

        return $query->getQuery();
    }

    public function findClientsByRiaId($riaId, $searchStr = null)
    {
        return $this->findClientsByRiaIdQuery($riaId, $searchStr)->getResult();
    }

    public function getClientByIdAndRiaId($id, $riaId)
    {
        $query = $this->createQueryBuilder('cu');
        $query->leftJoin('cu.profile', 'cup')
            ->where('cu.id = :id')
            ->andWhere('cup.ria_user_id = :ria_id')
            ->setMaxResults(1)
            ->setParameters([
                'id' => $id,
                'ria_id' => $riaId,
            ]);

        return $query->getQuery()->getOneOrNullResult();
    }

    public function getUsersByRiaId($id, $limit = null, $group = null)
    {
        $query = $this->createQueryBuilder('ru');
        $query->leftJoin('ru.profile', 'rup')
            ->where('rup.ria_user_id = :ria_id OR ru.id = :ria_id')
            ->andWhere('ru.roles LIKE :role_ria_admin OR ru.roles LIKE :role_ria_user OR ru.roles LIKE :role_ria')
            ->setParameters([
                'ria_id' => $id,
                'role_ria_admin' => '%"ROLE_RIA_ADMIN"%',
                'role_ria_user' => '%"ROLE_RIA_USER"%',
                'role_ria' => '%"ROLE_RIA"%',
            ]);

        if ($limit) {
            $query->setMaxResults($limit);
        }

        if ($group) {
            $query
                ->leftJoin('ru.groups', 'rug')
                ->andWhere('rug.name = :group_name')
                ->setParameter('group_name', $group);
        }

        return $query->getQuery()->getResult();
    }

    public function getUsersForRiaAdmin(User $riaAdmin)
    {
        $riaGroupNames = $riaAdmin->getGroupIds();

        $qb = $this->createQueryBuilder('u');
        $qb->leftJoin('u.groups', 'g')
            ->leftJoin('u.profile', 'up')
            ->where($qb->expr()->in('g.id', $riaGroupNames))
            ->andWhere('up.ria_user_id = :owner_id AND (u.roles LIKE :role_ria_admin OR u.roles LIKE :role_ria_user)')
            ->orWhere('u.id = :owner_id')
            ->setParameters([
                'owner_id' => $riaAdmin->getRia()->getId(),
                'role_ria_admin' => '%"ROLE_RIA_ADMIN"%',
                'role_ria_user' => '%"ROLE_RIA_USER"%',
            ]);

        //echo '<pre>';
        //echo($qb->getQuery()->getSQL());die;
        return $qb->getQuery()->getResult();
    }

    public function getUserByRiaIdAndUserId($riaId, $userId)
    {
        $query = $this->createQueryBuilder('ru');
        $query->leftJoin('ru.profile', 'rup')
            ->where('rup.ria_user_id = :ria_id')
            ->andWhere('ru.roles LIKE :role_ria_admin OR ru.roles LIKE :role_ria_user')
            ->andWhere('ru.id = :user_id')
            ->setParameters([
                'ria_id' => $riaId,
                'user_id' => $userId,
                'role_ria_admin' => '%"ROLE_RIA_ADMIN"%',
                'role_ria_user' => '%"ROLE_RIA_USER"%', ])
            ->setMaxResults(1);

        return $query->getQuery()->getOneOrNullResult();
    }

    public function getRiasOrderedById($limit = null, $order = 'DESC')
    {
        $query = $this->createQueryBuilder('ru')
            ->select('ru', 'rup', 'rci', 's')
            ->leftJoin('ru.profile', 'rup')
            ->leftJoin('ru.riaCompanyInformation', 'rci')
            ->leftJoin('rci.state', 's')
            ->where('ru.roles LIKE :role')
            ->setParameter('role', '%"ROLE_RIA"%')
            ->orderBy('ru.id', $order);

        if (!is_null($limit)) {
            $query->setMaxResults($limit);
        }

        return $query->getQuery()->getResult();
    }

    public function getRiasOrderedByName($limit = null, $order = 'ASC')
    {
        $query = $this->createQueryBuilder('ru')
            ->select('ru', 'rup', 'rci', 's')
            ->leftJoin('ru.profile', 'rup')
            ->leftJoin('ru.riaCompanyInformation', 'rci')
            ->leftJoin('rci.state', 's')
            ->where('ru.roles LIKE :role')
            ->setParameter('role', '%"ROLE_RIA"%')
            ->orderBy('rci.name', $order);

        if (!is_null($limit)) {
            $query->setMaxResults($limit);
        }

        return $query->getQuery()->getResult();
    }

    public function getClientsByMasterClientId($masterClientId)
    {
        $query = $this->createQueryBuilder('u')
            ->where('u.master_client_id = :master_client_id')
            ->setParameter('master_client_id', $masterClientId);

        return $query->getQuery()->getResult();
    }

    public function getClientByIdAndMasterClientId($clientId, $masterClientId)
    {
        $query = $this->createQueryBuilder('u')
            ->where('u.id = :client_id AND u.master_client_id = :master_client_id')
            ->setParameter('client_id', $clientId)
            ->setParameter('master_client_id', $masterClientId)
            ->setMaxResults(1);

        return $query->getQuery()->getOneOrNullResult();
    }

    public function findRiasQuery()
    {
        $query = $this->createQueryBuilder('ru')
            ->select('ru', 'p', 'ci', 's')
            ->leftJoin('ru.profile', 'p')
            ->leftJoin('ru.riaCompanyInformation', 'ci')
            ->leftJoin('ci.state', 's')
            ->where('ru.roles LIKE :role')
            ->setParameter('role', '%"ROLE_RIA"%');

        return $query->getQuery();
    }

    /**
     * @deprecated
     */
    public function getAdmin()
    {
        $query = $this->createQueryBuilder('ru')
            ->where('ru.roles LIKE :role')
            ->setParameter('role', '%"ROLE_SUPER_ADMIN"%');

        return $query->getQuery()->getOneOrNullResult();
    }

    public function getAllAdmins()
    {
        $qb = $this->createQueryBuilder('au')
            ->where('au.roles LIKE :role_admin')
            ->orWhere('au.roles LIKE :role_super_admin')
            ->setParameters([
                'role_super_admin' => '%"ROLE_SUPER_ADMIN"%',
                'role_admin' => '%ROLE_ADMIN%',
            ]);

        return $qb->getQuery()->getResult();
    }

    public function getClientsWithModel($modelId)
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.profile', 'p')
            ->where('p.suggested_portfolio_id = :portfolio_id')
            ->setParameter('portfolio_id', $modelId);

        return $qb->getQuery()->getResult();
    }

    public function findNotActivatedRiasForSendEmail()
    {
        $qb = $this->createQueryBuilder('ru')
            ->leftJoin('ru.riaCompanyInformation', 'ci')
            ->where('ru.roles LIKE :role')
            ->andWhere('ci.activated = :isActivated')
            ->andWhere('MOD(DATEDIFF(:today, ru.created), 7) = 0')
            ->andWhere('DATEDIFF(:today, ru.created) >= 14')
            ->setParameters([
                'role' => '%"ROLE_RIA"%',
                'isActivated' => false,
                'today' => date('Y-m-d'),
            ]);

        return $qb->getQuery()->getResult();
    }

    public function findNotFinishedRegistrationClientsForSendEmail()
    {
        $qb = $this->createQueryBuilder('cu')
            ->leftJoin('cu.profile', 'cup')
            ->where('cu.roles LIKE :role')
            ->andWhere('cup.registration_step < 3')
            ->andWhere('MOD(DATEDIFF(:today, cu.created), 7) = 0')
            ->setParameters([
                'role' => '%"ROLE_CLIENT"%',
                'today' => date('Y-m-d'),
            ]);

        return $qb->getQuery()->getResult();
    }

    public function findNotApprovedPortfolioClientsForSendEmail()
    {
        $qb = $this->createQueryBuilder('cu')
            ->leftJoin('cu.profile', 'cup')
            ->leftJoin('cu.clientPortfolios', 'cucp')
            ->where('cu.roles LIKE :role')
            ->andWhere('cucp.status = :status')
            ->andWhere('cup.registration_step = 3 OR cup.registration_step = 4')
            ->andWhere('MOD(DATEDIFF(:today, cucp.approved_at), 7) = 0')
            ->setParameters([
                'role' => '%"ROLE_CLIENT"%',
                'today' => date('Y-m-d'),
                'status' => 'not approved',
            ]);

        return $qb->getQuery()->getResult();
    }

    public function findNotCompleteAllApplicationsClientForSendEmail()
    {
        $qb = $this->createQueryBuilder('cu')
            ->leftJoin('cu.profile', 'cup')
            ->leftJoin('cu.clientAccounts', 'ca')
            ->leftJoin('cu.clientPortfolios', 'cucp')
            ->leftJoin('ca.groupType', 'gt')
            ->leftJoin('gt.group', 'g')
            ->where('cup.registration_step > 5')
            ->andWhere('(g.name = :group AND ca.process_step != :step1) OR (g.name != :group AND ca.process_step != :step2)')
            ->andWhere('MOD(DATEDIFF(:today, cucp.approved_at), 7) = 0')
            ->setParameters([
                'group' => AccountGroup::GROUP_EMPLOYER_RETIREMENT,
                'step1' => ClientAccount::PROCESS_STEP_COMPLETED_CREDENTIALS,
                'step2' => ClientAccount::PROCESS_STEP_FINISHED_APPLICATION,
                'today' => date('Y-m-d'),
            ]);

        return $qb->getQuery()->getResult();
    }

    public function findClientsWithoutProspectsByRiaIdQuery($riaId)
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.profile', 'p')
            ->leftJoin('c.clientAccounts', 'ca')
            ->where('p.ria_user_id = :ria_id')
            ->andWhere('c.roles LIKE :role')
            ->andWhere('p.client_status = :client_status')
            ->groupBy('c.id')
            ->setParameter('ria_id', $riaId)
            ->setParameter('role', '%ROLE_CLIENT%')
            ->setParameter('client_status', Profile::CLIENT_STATUS_CLIENT);

        return $qb;
    }

    public function findOrderedClientsWithoutProspectsByRiaId($riaId, $sort = null, $order = 'ASC')
    {
        $qb = $this->findClientsWithoutProspectsByRiaIdQuery($riaId);

        switch ($sort) {
            case 'name':
                $qb->orderBy('p.last_name', $order);
                $qb->addOrderBy('p.first_name', $order);
                break;
            default:
                $qb->orderBy('p.last_name', $order);
                $qb->addOrderBy('p.first_name', $order);
                break;
        }

        return $qb->getQuery()->getResult();
    }

    public function findClientsWithoutProspectsByRiaId($riaId, $searchStr = '')
    {
        $qb = $this->findClientsWithoutProspectsByRiaIdQuery($riaId);

        if ($searchStr) {
            $qb
                ->andWhere('p.first_name LIKE :searchStr OR p.last_name LIKE :searchStr')
                ->setParameter('searchStr', '%'.$searchStr.'%');
        }

        return $qb->getQuery()->getResult();
    }

    public function findOrderedProspectsByRia(User $ria, $sort = null, $order = 'ASC')
    {
        $qb = $this->createQueryBuilder('c')
            ->select('c as client, SUM(ca.value) as value_sum')
            ->leftJoin('c.profile', 'p')
            ->leftJoin('c.clientAccounts', 'ca')
            ->leftJoin('c.groups', 'ug')
            ->where('p.ria_user_id = :ria_id')
            ->andWhere('c.roles LIKE :role')
            ->andWhere('p.client_status = :client_status')
            ->groupBy('c.id')
            ->setParameter('role', '%ROLE_CLIENT%')
            ->setParameter('client_status', Profile::CLIENT_STATUS_PROSPECT);

        if ($ria->hasRole('ROLE_RIA_ADMIN') || $ria->hasRole('RIA_USER')) {
            $groupIds = [];
            foreach ($ria->getGroups() as $group) {
                $groupIds[] = $group->getId();
            }

            $qb->andWhere($qb->expr()->in('ug.id', $groupIds))
                ->setParameter('ria_id', $ria->getRia()->getId());
        } else {
            $qb->setParameter('ria_id', $ria->getId());
        }

        switch ($sort) {
            case 'value':
                $qb->orderBy('value_sum', $order);
                break;
            case 'group':
                $qb->orderBy('ug.name', $order);
                break;
            case 'step':
                $qb->orderBy('p.registration_step', $order);
                break;
            case 'name':
                $qb->orderBy('p.last_name', $order);
                $qb->addOrderBy('p.first_name', $order);
                break;
            default:
                $qb->orderBy('p.last_name', $order);
                $qb->addOrderBy('p.first_name', $order);
                break;
        }

        return $qb->getQuery()->getResult();
    }

    public function findClientsByRia(User $ria)
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.profile', 'p')
            ->leftJoin('c.groups', 'ug')
            ->where('p.ria_user_id = :ria_id')
            ->andWhere('c.roles LIKE :role')
            ->andWhere('p.client_status = :client_status')
            ->groupBy('c.id')
            ->setParameter('role', '%ROLE_CLIENT%')
            ->setParameter('client_status', Profile::CLIENT_STATUS_CLIENT);

        if (($ria->hasRole('ROLE_RIA_ADMIN') || $ria->hasRole('RIA_USER')) && !$ria->hasGroup('All')) {
            $groupIds = [];
            foreach ($ria->getGroups() as $group) {
                $groupIds[] = $group->getId();
            }

            $qb->andWhere($qb->expr()->in('ug.id', $groupIds))
                ->setParameter('ria_id', $ria->getRia()->getId());
        } else {
            $qb->setParameter('ria_id', $ria->getId());
        }

        return $qb->getQuery()->getResult();
    }

    public function findClientsWithNotApprovedPortfolioByRiaId($riaId)
    {
        $qb = $this->createQueryBuilder('cu');

        $qb->leftJoin('cu.clientPortfolios', 'cp')
            ->leftJoin('cu.profile', 'cup')
            ->where('cup.ria_user_id = :ria_id')
            //->andWhere('cup.suggested_portfolio_id IS NOT NULL')
            ->andWhere('cp.status = :status')
            ->andWhere('cp.is_active = :is_active')
            ->setParameters([
                'ria_id' => $riaId,
                'status' => ClientPortfolio::STATUS_PROPOSED,
                'is_active' => true,
            ]);

        return $qb->getQuery()->getResult();
    }

    /**
     * @param User $ria
     */
    public function findInitialRebalancedClients(User $ria)
    {
        return $this->createQueryBuilder('u')
            ->leftJoin('u.profile', 'p')
            ->where('p.ria = :ria')
            ->leftJoin('u.systemAccounts', 'a')
            ->andWhere('a.status IN (:success_statuses)')
            ->setParameter('success_statuses', [SystemAccount::STATUS_ACTIVE, SystemAccount::STATUS_REGISTERED])
            ->setParameter('ria', $ria)
            ->getQuery()
            ->getResult();
    }

    public function findRiaClientsByDate(User $ria, \DateTime $dateTo)
    {
        return $this->createQueryBuilder('u')
            ->leftJoin('u.profile', 'p')
            ->where('p.ria = :ria')
            ->andWhere('p.client_status = :status')
            ->andWhere('u.created <= :date')
            ->leftJoin('u.systemAccounts', 'a')
            ->andWhere('a.status IN (:success_statuses)')
            ->setParameter('success_statuses', [SystemAccount::STATUS_ACTIVE, SystemAccount::STATUS_REGISTERED])
            ->setParameter('ria', $ria)
            ->setParameter('date', $dateTo)
            ->setParameter('status', Profile::CLIENT_STATUS_CLIENT)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get ria clients by client id array.
     *
     * @param User  $ria
     * @param array $ids
     *
     * @return array
     */
    public function getRiaClientsByIds(User $ria, array $ids)
    {
        return $this->createQueryBuilder('u')
            ->leftJoin('u.profile', 'p')
            ->where('p.ria = :ria')
            ->setParameter('ria', $ria)
            ->andWhere('u.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Get all clients by date.
     *
     * @param \DateTime $dateTo
     *
     * @return array
     */
    public function getAllClientsByDate(\DateTime $dateTo)
    {
        return $this->createQueryBuilder('u')
            ->leftJoin('u.profile', 'p')
            ->andWhere('p.client_status = :status')
            ->andWhere('u.created <= :date')
            ->leftJoin('u.systemAccounts', 'a')
            ->andWhere('a.status IN (:success_statuses)')
            ->setParameter('success_statuses', [SystemAccount::STATUS_ACTIVE, SystemAccount::STATUS_REGISTERED])
            ->setParameter('date', $dateTo)
            ->setParameter('status', Profile::CLIENT_STATUS_CLIENT)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Get first ria.
     *
     * @return mixed
     */
    public function getFirstRia()
    {
        return $this
            ->createQueryBuilder('u')
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%"ROLE_RIA"%')
            ->orderBy('u.created', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    public function findClientsByRelationsType($relationsType)
    {
        $qb = $this->createQueryBuilder('cu')
            ->leftJoin('cu.profile', 'cup')
            ->leftJoin('cup.ria', 'r')
            ->leftJoin('r.riaCompanyInformation', 'rci')
            ->where('cu.roles LIKE :roles')
            ->andWhere('rci.relationship_type = :relationsType')
            ->setParameters([
                'roles' => '%ROLE_CLIENT%',
                'relationsType' => $relationsType,
            ])
        ;

        return $qb->getQuery()->getResult();
    }
}