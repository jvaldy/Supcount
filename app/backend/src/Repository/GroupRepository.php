<?php

namespace App\Repository;

use App\Entity\Group;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class GroupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Group::class);
    }

    public function findByMember(User $user): array
    {
        return $this->createQueryBuilder('g')
            ->innerJoin('g.members', 'm')
            ->where('m = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }
}
