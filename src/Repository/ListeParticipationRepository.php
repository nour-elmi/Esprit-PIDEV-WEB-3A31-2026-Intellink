<?php

namespace App\Repository;

use App\Entity\ListeParticipation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ListeParticipation>
 */
class ListeParticipationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ListeParticipation::class);
    }
     

    // Dans ListeParticipationRepository.php

    public function searchParticipations($offre, ?string $term)
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.id_offre = :offre') // Utilise le nom exact de la propriété dans l'entité
            ->setParameter('offre', $offre);

        if ($term) {
            // On cherche dans le statut, les skills, le nom ou le prénom
            $qb->andWhere('p.statutt LIKE :term OR p.skills LIKE :term OR p.nom_p LIKE :term OR p.prenom_p LIKE :term')
            ->setParameter('term', '%' . $term . '%');
        }

        $qb->orderBy('p.id_participation', 'DESC');

        return $qb->getQuery()->getResult();
    }

    




//    /**
//     * @return ListeParticipation[] Returns an array of ListeParticipation objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('l')
//            ->andWhere('l.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('l.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?ListeParticipation
//    {
//        return $this->createQueryBuilder('l')
//            ->andWhere('l.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
