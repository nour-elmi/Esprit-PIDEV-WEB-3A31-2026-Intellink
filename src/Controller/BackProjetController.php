<?php

namespace App\Controller;

use App\ContractBundle\Service\ContractPdfGenerator;
use App\Entity\ActionLog;
use App\Entity\Collaboration;
use App\Entity\ContratParticipation;
use App\Entity\Projet;
use App\Entity\Utilisateur;
use App\Form\ProjetType;
use App\MailingBundle\Service\ParticipationNotificationMailer;
use App\PaginationBundle\Service\PaginationService;
use App\Service\ParticipationBadgeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class BackProjetController extends AbstractController
{
    #[Route('/back/projets', name: 'app_back_projets', methods: ['GET'])]
    public function index(
        Request $request,
        EntityManagerInterface $entityManager,
        PaginationService $paginationService
    ): Response
    {
        $chef = $this->getConnectedChefOrFail();
        $chefEmail = (string) $chef->getEmail();

        $repo = $entityManager->getRepository(Projet::class);

        $search = trim((string) $request->query->get('search', ''));
        $statut = trim((string) $request->query->get('statut', ''));
        $sort = trim((string) $request->query->get('sort', 'recent'));

        $qb = $repo->createQueryBuilder('p')
            ->where('p.createur = :createur')
            ->setParameter('createur', $chefEmail);

        if ($search !== '') {
            $qb->andWhere('p.titre LIKE :search OR p.description LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($statut !== '' && $statut !== 'tous') {
            $qb->andWhere('p.statut = :statut')
                ->setParameter('statut', $statut);
        }

        if ($sort === 'ancien') {
            $qb->orderBy('p.id', 'ASC');
        } elseif ($sort === 'prix_asc') {
            $qb->orderBy('p.prix', 'ASC');
        } elseif ($sort === 'prix_desc') {
            $qb->orderBy('p.prix', 'DESC');
        } else {
            $qb->orderBy('p.id', 'DESC');
        }

        $projectsCount = (int) $repo->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.createur = :createur')
            ->setParameter('createur', $chefEmail)
            ->getQuery()
            ->getSingleScalarResult();

        $projectIds = [];
        $projectsById = [];
        if ($projectsCount > 0) {
            $chefProjects = $repo->findBy(['createur' => $chefEmail], ['id' => 'DESC']);
            foreach ($chefProjects as $chefProject) {
                $pid = (int) $chefProject->getId();
                $projectIds[] = $pid;
                $projectsById[$pid] = $chefProject;
            }
        }

        $chefTopProject = [
            'best' => null,
            'leaderboard' => [],
            'totalParticipations' => 0,
            'coverage' => 0,
        ];
        if (!empty($projectIds)) {
            $participationRows = $entityManager->getRepository(Collaboration::class)->createQueryBuilder('c')
                ->select('IDENTITY(c.projet) AS projectId')
                ->addSelect('COUNT(c.id) AS total')
                ->addSelect('SUM(CASE WHEN c.etat = :acceptedEtat THEN 1 ELSE 0 END) AS accepted')
                ->where('c.projet IN (:projectIds)')
                ->setParameter('projectIds', $projectIds)
                ->setParameter('acceptedEtat', 'ACCEPTEE')
                ->groupBy('c.projet')
                ->getQuery()
                ->getArrayResult();

            $countsByProject = [];
            foreach ($participationRows as $row) {
                $pid = (int) ($row['projectId'] ?? 0);
                if ($pid <= 0) {
                    continue;
                }
                $countsByProject[$pid] = [
                    'total' => (int) ($row['total'] ?? 0),
                    'accepted' => (int) ($row['accepted'] ?? 0),
                ];
            }

            $leaderboard = [];
            $totalParticipations = 0;
            foreach ($projectsById as $pid => $projectEntity) {
                $totals = $countsByProject[(int) $pid] ?? ['total' => 0, 'accepted' => 0];
                $totalCount = (int) ($totals['total'] ?? 0);
                $acceptedCount = (int) ($totals['accepted'] ?? 0);
                $totalParticipations += $totalCount;

                $leaderboard[] = [
                    'id' => (int) $pid,
                    'title' => (string) ($projectEntity->getTitre() ?? ('Projet #' . (int) $pid)),
                    'total' => $totalCount,
                    'accepted' => $acceptedCount,
                    'status' => (string) ($projectEntity->getStatut() ?? ''),
                ];
            }

            usort(
                $leaderboard,
                static function (array $a, array $b): int {
                    if ($a['total'] === $b['total']) {
                        if ($a['accepted'] === $b['accepted']) {
                            return $a['id'] <=> $b['id'];
                        }
                        return $b['accepted'] <=> $a['accepted'];
                    }
                    return $b['total'] <=> $a['total'];
                }
            );

            $bestProject = $leaderboard[0] ?? null;
            $coverage = 0;
            if ($bestProject !== null && $totalParticipations > 0) {
                $coverage = (int) round(((int) $bestProject['total'] / $totalParticipations) * 100);
            }

            $chefTopProject = [
                'best' => $bestProject,
                'leaderboard' => array_slice($leaderboard, 0, 3),
                'totalParticipations' => $totalParticipations,
                'coverage' => $coverage,
            ];
        }

        $demandesParEtat = $entityManager->getRepository(Collaboration::class)->createQueryBuilder('c')
            ->select('c.etat AS etat, COUNT(c.id) AS total')
            ->join('c.projet', 'p')
            ->where('p.createur = :createur')
            ->setParameter('createur', $chefEmail)
            ->groupBy('c.etat')
            ->getQuery()
            ->getArrayResult();

        $totalDemandes = 0;
        $acceptedDemandes = 0;
        foreach ($demandesParEtat as $ligne) {
            $count = (int) ($ligne['total'] ?? 0);
            $totalDemandes += $count;
            if (strtoupper((string) ($ligne['etat'] ?? '')) === 'ACCEPTEE') {
                $acceptedDemandes = $count;
            }
        }

        $totalActions = (int) $entityManager->getRepository(ActionLog::class)->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.actorId = :actorId')
            ->setParameter('actorId', (int) $chef->getId())
            ->getQuery()
            ->getSingleScalarResult();

        $weekStart = new \DateTimeImmutable('-7 days');
        $weeklyActions = (int) $entityManager->getRepository(ActionLog::class)->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.actorId = :actorId')
            ->andWhere('a.createdAt >= :weekStart')
            ->setParameter('actorId', (int) $chef->getId())
            ->setParameter('weekStart', $weekStart)
            ->getQuery()
            ->getSingleScalarResult();

        $chefBadgeProfile = $this->buildChefBadgeProfile([
            'projectsCount' => $projectsCount,
            'acceptedDemandes' => $acceptedDemandes,
            'totalDemandes' => $totalDemandes,
            'totalActions' => $totalActions,
            'weeklyActions' => $weeklyActions,
        ]);

        $chefNotifications = [];
        if (!empty($projectIds)) {
            $collabQb = $entityManager->getRepository(Collaboration::class)->createQueryBuilder('c');
            $collaborations = $collabQb
                ->join('c.projet', 'p')
                ->addSelect('p')
                ->where('p.id IN (:projectIds)')
                ->setParameter('projectIds', $projectIds)
                ->orderBy('c.dateCreation', 'DESC')
                ->addOrderBy('c.id', 'DESC')
                ->setMaxResults(30)
                ->getQuery()
                ->getResult();

            $signedContracts = $entityManager->getRepository(ContratParticipation::class)->createQueryBuilder('ct')
                ->where('ct.chefId = :chefId')
                ->andWhere('ct.userSignedAt IS NOT NULL')
                ->setParameter('chefId', (int) $chef->getId())
                ->orderBy('ct.userSignedAt', 'DESC')
                ->setMaxResults(30)
                ->getQuery()
                ->getResult();

            $userIds = [];
            foreach ($collaborations as $collaboration) {
                if (!$collaboration instanceof Collaboration) {
                    continue;
                }
                $uid = $collaboration->getUserId();
                if ($uid !== null) {
                    $userIds[] = (int) $uid;
                }
            }
            foreach ($signedContracts as $contract) {
                if (!$contract instanceof ContratParticipation) {
                    continue;
                }
                $uid = $contract->getUserId();
                if ($uid !== null) {
                    $userIds[] = (int) $uid;
                }
            }
            $userIds = array_values(array_unique($userIds));

            $usersById = [];
            if (!empty($userIds)) {
                $users = $entityManager->getRepository(Utilisateur::class)->findBy(['id' => $userIds]);
                foreach ($users as $userEntity) {
                    $usersById[(int) $userEntity->getId()] = $userEntity;
                }
            }

            foreach ($collaborations as $collaboration) {
                if (!$collaboration instanceof Collaboration) {
                    continue;
                }
                $project = $collaboration->getProjet();
                if (!$project instanceof Projet) {
                    continue;
                }

                $uid = (int) ($collaboration->getUserId() ?? 0);
                $candidate = $usersById[$uid] ?? null;
                $candidateLabel = 'Utilisateur #' . $uid;
                if ($candidate instanceof Utilisateur) {
                    $candidateName = trim((string) $candidate->getNom());
                    $candidateLabel = $candidateName !== '' ? $candidateName : ((string) $candidate->getEmail());
                }

                $eventDate = $collaboration->getDateCreation();
                $chefNotifications[] = [
                    'id' => 'collab_' . (int) $collaboration->getId(),
                    'type' => 'demande',
                    'title' => 'Nouvelle demande de participation',
                    'message' => $candidateLabel . ' a demande a participer au projet "' . (string) $project->getTitre() . '".',
                    'date' => $eventDate?->format(\DateTimeInterface::ATOM),
                    'url' => $this->generateUrl('app_back_projet_demandes', ['id' => (int) $project->getId()]),
                ];
            }

            foreach ($signedContracts as $contract) {
                if (!$contract instanceof ContratParticipation) {
                    continue;
                }

                $uid = (int) ($contract->getUserId() ?? 0);
                $candidate = $usersById[$uid] ?? null;
                $candidateLabel = 'Utilisateur #' . $uid;
                if ($candidate instanceof Utilisateur) {
                    $candidateName = trim((string) $candidate->getNom());
                    $candidateLabel = $candidateName !== '' ? $candidateName : ((string) $candidate->getEmail());
                }

                $projectId = (int) ($contract->getProjetId() ?? 0);
                $projectTitle = 'Projet #' . $projectId;
                if (isset($projectsById[$projectId]) && $projectsById[$projectId] instanceof Projet) {
                    $projectTitle = (string) $projectsById[$projectId]->getTitre();
                }
                $eventDate = $contract->getUserSignedAt();
                $chefNotifications[] = [
                    'id' => 'contract_' . (int) $contract->getId(),
                    'type' => 'contrat',
                    'title' => 'Contrat signe par le participant',
                    'message' => $candidateLabel . ' a signe le contrat pour "' . $projectTitle . '".',
                    'date' => $eventDate?->format(\DateTimeInterface::ATOM),
                    'url' => $this->generateUrl('app_back_contrat_show', ['id' => (int) $contract->getId()]),
                ];
            }

            usort(
                $chefNotifications,
                static function (array $a, array $b): int {
                    $aTime = isset($a['date']) ? strtotime((string) $a['date']) : 0;
                    $bTime = isset($b['date']) ? strtotime((string) $b['date']) : 0;

                    return $bTime <=> $aTime;
                }
            );

            $chefNotifications = array_slice($chefNotifications, 0, 20);
        }

        $page = $paginationService->getPageFromRequest($request);
        $pagination = $paginationService->paginateQueryBuilder($qb, $page, 6);

        return $this->render('back/projets.html.twig', [
            'projets' => $pagination->getItems(),
            'pagination' => $pagination,
            'search' => $search,
            'statut' => $statut,
            'sort' => $sort,
            'chefBadgeProfile' => $chefBadgeProfile,
            'chefNotifications' => $chefNotifications,
            'chefTopProject' => $chefTopProject,
        ]);
    }

    #[Route('/back/projets/ajouter', name: 'app_back_projet_add', methods: ['GET', 'POST'])]
    public function add(Request $request, EntityManagerInterface $entityManager): Response
    {
        $chef = $this->getConnectedChefOrFail();

        $projet = new Projet();
        $projet->setStatut('en attente');

        $form = $this->createForm(ProjetType::class, $projet);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $projet->setCreateur((string) $chef->getEmail());
            if ($projet->getDateCreation() === null) {
                $projet->setDateCreation(new \DateTimeImmutable());
            }

            $entityManager->persist($projet);
            $this->logAction(
                $entityManager,
                (int) $chef->getId(),
                null,
                'PROJET_AJOUTE',
                'Vous avez ajoute le projet : ' . $projet->getTitre()
            );
            $entityManager->flush();

            $this->addFlash('success', 'Projet ajoute avec succes.');
            return $this->redirectToRoute('app_back_projets');
        }

        return $this->render('back/form.html.twig', [
            'form' => $form->createView(),
            'title' => 'Ajouter un projet',
            'subtitle' => 'Creez un nouveau projet avec une presentation claire et professionnelle.',
            'button_label' => 'Creer le projet',
            'is_edit' => false,
        ]);
    }

    #[Route('/back/projets/{id}/modifier', name: 'app_back_projet_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $chef = $this->getConnectedChefOrFail();
        $projet = $entityManager->getRepository(Projet::class)->find($id);

        if (!$projet || $projet->getCreateur() !== (string) $chef->getEmail()) {
            throw $this->createNotFoundException('Projet introuvable.');
        }

        $form = $this->createForm(ProjetType::class, $projet);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->logAction(
                $entityManager,
                (int) $chef->getId(),
                $projet->getId(),
                'PROJET_MODIFIE',
                'Vous avez modifie le projet : ' . $projet->getTitre()
            );
            $entityManager->flush();

            $this->addFlash('success', 'Projet modifie avec succes.');
            return $this->redirectToRoute('app_back_projets');
        }

        return $this->render('back/form.html.twig', [
            'form' => $form->createView(),
            'title' => 'Modifier le projet',
            'subtitle' => 'Mettez a jour les informations du projet.',
            'button_label' => 'Enregistrer les modifications',
            'is_edit' => true,
        ]);
    }

    #[Route('/back/projets/{id}/supprimer', name: 'app_back_projet_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $chef = $this->getConnectedChefOrFail();
        $projet = $entityManager->getRepository(Projet::class)->find($id);

        if (!$projet || $projet->getCreateur() !== (string) $chef->getEmail()) {
            throw $this->createNotFoundException('Projet introuvable.');
        }

        if (!$this->isCsrfTokenValid('delete_projet_' . $projet->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_back_projets');
        }

        $titreProjet = (string) $projet->getTitre();
        $projetId = $projet->getId();

        $this->logAction(
            $entityManager,
            (int) $chef->getId(),
            $projetId,
            'PROJET_SUPPRIME',
            'Vous avez supprime le projet : ' . $titreProjet
        );

        $entityManager->remove($projet);
        $entityManager->flush();

        $this->addFlash('success', 'Projet supprime avec succes.');
        return $this->redirectToRoute('app_back_projets');
    }

    #[Route('/back/projets/{id}/demandes', name: 'app_back_projet_demandes', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function demandes(
        int $id,
        EntityManagerInterface $entityManager,
        ParticipationBadgeService $participationBadgeService
    ): Response
    {
        $chef = $this->getConnectedChefOrFail();
        $projet = $entityManager->getRepository(Projet::class)->find($id);

        if (!$projet || $projet->getCreateur() !== (string) $chef->getEmail()) {
            throw $this->createNotFoundException('Projet introuvable.');
        }

        $demandes = $entityManager->getRepository(Collaboration::class)->findBy(
            ['projet' => $projet],
            ['id' => 'DESC']
        );

        $userIds = array_values(array_unique(array_filter(array_map(
            static fn(Collaboration $d) => $d->getUserId(),
            $demandes
        ))));

        $utilisateursParId = [];
        if (!empty($userIds)) {
            $utilisateurs = $entityManager->getRepository(Utilisateur::class)->findBy(['id' => $userIds]);
            foreach ($utilisateurs as $u) {
                $utilisateursParId[$u->getId()] = $u;
            }
        }

        $scoresParDemande = [];
        foreach ($demandes as $demande) {
            $uid = $demande->getUserId();
            $user = $uid !== null ? ($utilisateursParId[$uid] ?? null) : null;
            $scoresParDemande[$demande->getId()] = $this->buildDemandeScoreData($demande, $user);
        }

        $badgesParUserId = [];
        if (!empty($userIds)) {
            $allUserDemandes = $entityManager->getRepository(Collaboration::class)->findBy(
                ['userId' => $userIds],
                ['id' => 'DESC']
            );
            $demandesParUserId = [];
            foreach ($allUserDemandes as $userDemande) {
                $uid = $userDemande->getUserId();
                if ($uid === null) {
                    continue;
                }
                $demandesParUserId[(int) $uid][] = $userDemande;
            }
            foreach ($userIds as $uid) {
                $badgesParUserId[(int) $uid] = $participationBadgeService->buildForDemandes(
                    $demandesParUserId[(int) $uid] ?? []
                );
            }
        }

        $signedContractsByUserId = [];
        if (!empty($userIds)) {
            $signedRows = $entityManager->getRepository(ContratParticipation::class)->createQueryBuilder('ct')
                ->select('ct.userId AS userId, COUNT(ct.id) AS signedCount')
                ->where('ct.userId IN (:userIds)')
                ->andWhere('ct.userSignedAt IS NOT NULL')
                ->setParameter('userIds', $userIds)
                ->groupBy('ct.userId')
                ->getQuery()
                ->getArrayResult();

            foreach ($signedRows as $row) {
                $signedContractsByUserId[(int) ($row['userId'] ?? 0)] = (int) ($row['signedCount'] ?? 0);
            }
        }

        $comparisonRanking = [];
        $comparisonByDemande = [];
        foreach ($demandes as $demande) {
            $demandeId = (int) ($demande->getId() ?? 0);
            $uid = (int) ($demande->getUserId() ?? 0);
            $user = $utilisateursParId[$uid] ?? null;
            $scoreData = $scoresParDemande[$demandeId] ?? [];
            $badgeData = $badgesParUserId[$uid] ?? [];

            $iaScore = (int) ($scoreData['score'] ?? 0);
            $badgeScore = (int) ($badgeData['score'] ?? 0);
            $badgeLevel = (string) ($badgeData['levelLabel'] ?? 'Niveau debutant');
            $signedContracts = (int) ($signedContractsByUserId[$uid] ?? 0);
            $signedNormalized = min(100, $signedContracts * 20);

            $preferenceScore = (int) round(($iaScore * 0.60) + ($badgeScore * 0.25) + ($signedNormalized * 0.15));
            $preferenceScore = max(0, min(100, $preferenceScore));

            $item = [
                'demandeId' => $demandeId,
                'userId' => $uid,
                'candidateName' => $user?->getNom() ?: ('Utilisateur #' . $uid),
                'candidateEmail' => $user?->getEmail() ?: '',
                'iaScore' => $iaScore,
                'badgeScore' => $badgeScore,
                'badgeLevel' => $badgeLevel,
                'signedContracts' => $signedContracts,
                'preferenceScore' => $preferenceScore,
            ];

            $comparisonRanking[] = $item;
            $comparisonByDemande[$demandeId] = $item;
        }

        usort(
            $comparisonRanking,
            static function (array $a, array $b): int {
                if ($a['preferenceScore'] === $b['preferenceScore']) {
                    if ($a['iaScore'] === $b['iaScore']) {
                        if ($a['signedContracts'] === $b['signedContracts']) {
                            return $a['demandeId'] <=> $b['demandeId'];
                        }
                        return $b['signedContracts'] <=> $a['signedContracts'];
                    }
                    return $b['iaScore'] <=> $a['iaScore'];
                }
                return $b['preferenceScore'] <=> $a['preferenceScore'];
            }
        );

        $demandeComparaison = [
            'ranking' => array_slice($comparisonRanking, 0, 5),
            'top' => $comparisonRanking[0] ?? null,
            'byDemande' => $comparisonByDemande,
        ];

        $contrats = $entityManager->getRepository(ContratParticipation::class)->findBy([
            'projetId' => $projet->getId(),
        ]);
        $contratsParDemande = [];
        foreach ($contrats as $contrat) {
            $demandeId = $contrat->getCollaboration()?->getId();
            if ($demandeId !== null) {
                $contratsParDemande[$demandeId] = $contrat;
            }
        }

        return $this->render('back/demandes.html.twig', [
            'projet' => $projet,
            'demandes' => $demandes,
            'utilisateursParId' => $utilisateursParId,
            'scoresParDemande' => $scoresParDemande,
            'badgesParUserId' => $badgesParUserId,
            'signedContractsByUserId' => $signedContractsByUserId,
            'demandeComparaison' => $demandeComparaison,
            'contratsParDemande' => $contratsParDemande,
        ]);
    }

    #[Route('/back/demande/{id}/accept', name: 'app_back_demande_accept', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function accepter(
        int $id,
        EntityManagerInterface $entityManager,
        ParticipationNotificationMailer $participationNotificationMailer
    ): Response
    {
        $chef = $this->getConnectedChefOrFail();
        $collaboration = $entityManager->getRepository(Collaboration::class)->find($id);

        if (!$collaboration) {
            throw $this->createNotFoundException('Demande introuvable.');
        }

        $projet = $collaboration->getProjet();
        if (!$projet || $projet->getCreateur() !== (string) $chef->getEmail()) {
            throw $this->createNotFoundException('Projet introuvable.');
        }

        $demandeur = $entityManager->getRepository(Utilisateur::class)->find($collaboration->getUserId());
        $nomDemandeur = $demandeur?->getNom() ?: ('Utilisateur ID ' . $collaboration->getUserId());

        $collaboration->setEtat('ACCEPTEE');

        $contrat = $entityManager->getRepository(ContratParticipation::class)->findOneBy([
            'collaboration' => $collaboration,
        ]);

        $now = new \DateTimeImmutable();
        if (!$contrat) {
            $contrat = new ContratParticipation();
            $contrat->setCollaboration($collaboration);
            $contrat->setProjetId((int) $projet->getId());
            $contrat->setChefId((int) $chef->getId());
            $contrat->setUserId((int) $collaboration->getUserId());
            $contrat->setStatut('ENVOYE_AU_USER');
            $contrat->setContenu($this->buildContractContent($projet, $demandeur, $collaboration, $chef));
            $contrat->setExpiresAt($now->modify('+15 days'));
            $contrat->setCreatedAt($now);
            $contrat->setUpdatedAt($now);
            $entityManager->persist($contrat);
        } elseif ($contrat->getStatut() !== 'SIGNE' && $contrat->getUserSignedAt() === null) {
            $contrat->setStatut('ENVOYE_AU_USER');
            $contrat->setUpdatedAt($now);
            if ($contrat->getContenu() === null || trim($contrat->getContenu()) === '') {
                $contrat->setContenu($this->buildContractContent($projet, $demandeur, $collaboration, $chef));
            }
        }

        $this->logAction(
            $entityManager,
            (int) $chef->getId(),
            $projet->getId(),
            'DEMANDE_ACCEPTEE',
            'Vous avez accepte la demande de participation de ' . $nomDemandeur . ' pour le projet : ' . $projet->getTitre()
        );
        $entityManager->flush();

        if ($demandeur instanceof Utilisateur) {
            try {
                $contractUrl = $this->generateUrl(
                    'app_participation_contrat_show',
                    ['id' => (int) $collaboration->getId()],
                    UrlGeneratorInterface::ABSOLUTE_URL
                );

                $mailSent = $participationNotificationMailer->notifyUserToSignContract(
                    $collaboration,
                    $contrat,
                    $demandeur,
                    $chef,
                    $contractUrl
                );

                if (!$mailSent) {
                    $this->addFlash('warning', 'Demande acceptee, mais email utilisateur non envoye (email invalide ou introuvable).');
                }
            } catch (\Throwable) {
                $this->addFlash('warning', 'Demande acceptee, mais impossible d\'envoyer l\'email de signature du contrat.');
            }
        }

        $this->addFlash('success', 'La demande a ete acceptee.');
        return $this->redirectToRoute('app_back_projet_demandes', ['id' => $projet->getId()]);
    }

    #[Route('/back/contrat/{id}', name: 'app_back_contrat_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function showContratChef(int $id, EntityManagerInterface $entityManager): Response
    {
        $chef = $this->getConnectedChefOrFail();
        $contrat = $entityManager->getRepository(ContratParticipation::class)->find($id);

        if (!$contrat) {
            throw $this->createNotFoundException('Contrat introuvable.');
        }

        $collaboration = $contrat->getCollaboration();
        $projet = $collaboration?->getProjet();
        if (!$projet || $projet->getCreateur() !== (string) $chef->getEmail()) {
            throw $this->createAccessDeniedException('Acces refuse a ce contrat.');
        }

        $candidat = $entityManager->getRepository(Utilisateur::class)->find($contrat->getUserId());

        return $this->render('back/contrat_show.html.twig', [
            'contrat' => $contrat,
            'projet' => $projet,
            'collaboration' => $collaboration,
            'candidat' => $candidat,
        ]);
    }

    #[Route('/back/contrat/{id}/valider', name: 'app_back_contrat_validate', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function validateContratChef(int $id, EntityManagerInterface $entityManager): Response
    {
        $chef = $this->getConnectedChefOrFail();
        $contrat = $entityManager->getRepository(ContratParticipation::class)->find($id);

        if (!$contrat) {
            throw $this->createNotFoundException('Contrat introuvable.');
        }

        $collaboration = $contrat->getCollaboration();
        $projet = $collaboration?->getProjet();
        if (!$projet || $projet->getCreateur() !== (string) $chef->getEmail()) {
            throw $this->createAccessDeniedException('Acces refuse a ce contrat.');
        }

        if ($contrat->getUserSignedAt() !== null) {
            $this->addFlash('success', 'Le contrat est deja finalise par la signature du participant.');
        } else {
            $this->addFlash('error', 'La validation manuelle chef est desactivee. Le contrat sera finalise apres signature utilisateur.');
        }

        return $this->redirectToRoute('app_back_contrat_show', ['id' => $contrat->getId()]);
    }

    #[Route('/back/contrat/{id}/pdf', name: 'app_back_contrat_pdf', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function downloadContratPdfChef(
        int $id,
        EntityManagerInterface $entityManager,
        ContractPdfGenerator $contractPdfGenerator
    ): Response {
        $chef = $this->getConnectedChefOrFail();
        $contrat = $entityManager->getRepository(ContratParticipation::class)->find($id);

        if (!$contrat) {
            throw $this->createNotFoundException('Contrat introuvable.');
        }

        $collaboration = $contrat->getCollaboration();
        $projet = $collaboration?->getProjet();
        if (!$projet || $projet->getCreateur() !== (string) $chef->getEmail()) {
            throw $this->createAccessDeniedException('Acces refuse a ce contrat.');
        }

        if ($contrat->getUserSignedAt() === null || strtoupper((string) $contrat->getStatut()) !== 'SIGNE') {
            $this->addFlash('error', 'Le PDF est disponible uniquement apres signature du contrat.');
            return $this->redirectToRoute('app_back_contrat_show', ['id' => $contrat->getId()]);
        }

        if (!$collaboration) {
            throw $this->createNotFoundException('Demande liee au contrat introuvable.');
        }

        $pdfBinary = $contractPdfGenerator->generateSignedContractPdf($contrat, $collaboration);
        $projectTitle = trim((string) ($projet?->getTitre() ?? 'projet'));
        $safeTitle = preg_replace('/[^a-zA-Z0-9\-_]+/', '-', $projectTitle) ?: 'projet';
        $filename = sprintf('contrat-signe-%s-%d.pdf', strtolower($safeTitle), (int) $contrat->getId());

        $response = new Response($pdfBinary);
        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $filename
        );
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }

    #[Route('/back/demande/{id}/refuse', name: 'app_back_demande_refuse', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function refuser(int $id, EntityManagerInterface $entityManager): Response
    {
        $chef = $this->getConnectedChefOrFail();
        $collaboration = $entityManager->getRepository(Collaboration::class)->find($id);

        if (!$collaboration) {
            throw $this->createNotFoundException('Demande introuvable.');
        }

        $projet = $collaboration->getProjet();
        if (!$projet || $projet->getCreateur() !== (string) $chef->getEmail()) {
            throw $this->createNotFoundException('Projet introuvable.');
        }

        $demandeur = $entityManager->getRepository(Utilisateur::class)->find($collaboration->getUserId());
        $nomDemandeur = $demandeur?->getNom() ?: ('Utilisateur ID ' . $collaboration->getUserId());

        $collaboration->setEtat('REFUSEE');
        $this->logAction(
            $entityManager,
            (int) $chef->getId(),
            $projet->getId(),
            'DEMANDE_REFUSEE',
            'Vous avez refuse la demande de participation de ' . $nomDemandeur . ' pour le projet : ' . $projet->getTitre()
        );
        $entityManager->flush();

        $this->addFlash('success', 'La demande a ete refusee.');
        return $this->redirectToRoute('app_back_projet_demandes', ['id' => $projet->getId()]);
    }

    #[Route('/back/historique', name: 'app_back_historique', methods: ['GET'])]
    public function historique(EntityManagerInterface $entityManager): Response
    {
        $chef = $this->getConnectedChefOrFail();

        $logs = $entityManager->getRepository(ActionLog::class)->findBy(
            ['actorId' => $chef->getId()],
            ['createdAt' => 'DESC']
        );

        return $this->render('back/historique.html.twig', [
            'logs' => $logs,
        ]);
    }

    #[Route('/back/projets/ai-description', name: 'app_back_projet_ai_description', methods: ['POST'])]
    public function generateDescriptionIA(Request $request, HttpClientInterface $httpClient): JsonResponse
    {
        $this->getConnectedChefOrFail();
        $isDebug = (bool) $this->getParameter('kernel.debug');

        $payload = json_decode($request->getContent(), true);
        $title = trim((string) ($payload['title'] ?? ''));

        if ($title === '' || strlen($title) < 3) {
            return new JsonResponse([
                'ok' => false,
                'message' => 'Le titre doit contenir au moins 3 caracteres.',
            ], 400);
        }

        $openAiApiKey = (string) (
            $_ENV['OPENAI_API_KEY']
            ?? $_SERVER['OPENAI_API_KEY']
            ?? getenv('OPENAI_API_KEY')
            ?? ''
        );

        $openAiModel = (string) (
            $_ENV['OPENAI_MODEL']
            ?? $_SERVER['OPENAI_MODEL']
            ?? getenv('OPENAI_MODEL')
            ?? 'gpt-4o-mini'
        );

        $geminiApiKey = (string) (
            $_ENV['GEMINI_API_KEY']
            ?? $_SERVER['GEMINI_API_KEY']
            ?? getenv('GEMINI_API_KEY')
            ?? ''
        );

        $geminiModel = (string) (
            $_ENV['GEMINI_MODEL']
            ?? $_SERVER['GEMINI_MODEL']
            ?? getenv('GEMINI_MODEL')
            ?? 'gemini-2.0-flash'
        );

        $geminiBaseUrl = rtrim((string) (
            $_ENV['GEMINI_BASE_URL']
            ?? $_SERVER['GEMINI_BASE_URL']
            ?? getenv('GEMINI_BASE_URL')
            ?? 'https://generativelanguage.googleapis.com/v1beta'
        ), '/');

        $provider = strtolower((string) (
            $_ENV['AI_PROVIDER']
            ?? $_SERVER['AI_PROVIDER']
            ?? getenv('AI_PROVIDER')
            ?? ''
        ));

        if ($provider === '') {
            $provider = 'auto';
        }

        $ollamaBaseUrl = rtrim((string) (
            $_ENV['OLLAMA_BASE_URL']
            ?? $_SERVER['OLLAMA_BASE_URL']
            ?? getenv('OLLAMA_BASE_URL')
            ?? 'http://127.0.0.1:11434'
        ), '/');

        $ollamaModel = (string) (
            $_ENV['OLLAMA_MODEL']
            ?? $_SERVER['OLLAMA_MODEL']
            ?? getenv('OLLAMA_MODEL')
            ?? 'llama3.2'
        );

        $providers = ['gemini', 'openai', 'ollama'];
        if ($provider === 'openai') {
            $providers = ['openai', 'gemini', 'ollama'];
        } elseif ($provider === 'ollama') {
            $providers = ['ollama', 'gemini', 'openai'];
        } elseif ($provider === 'gemini') {
            $providers = ['gemini', 'openai', 'ollama'];
        }

        $normalizeDescription = static function (string $description): string {
            $description = preg_replace('/\s+/', ' ', $description) ?? $description;
            $description = trim($description);
            if (strlen($description) > 220) {
                $description = substr($description, 0, 217) . '...';
            }
            return $description;
        };

        $debugReasons = [];

        foreach ($providers as $currentProvider) {
            if ($currentProvider === 'gemini') {
                if ($geminiApiKey === '') {
                    $debugReasons[] = 'GEMINI_API_KEY vide.';
                    continue;
                }

                try {
                    $response = $httpClient->request(
                        'POST',
                        $geminiBaseUrl . '/models/' . $geminiModel . ':generateContent?key=' . urlencode($geminiApiKey),
                        [
                            'headers' => [
                                'Content-Type' => 'application/json',
                            ],
                            'json' => [
                                'contents' => [
                                    [
                                        'role' => 'user',
                                        'parts' => [
                                            [
                                                'text' => 'Tu rediges des descriptions de projet en francais, courtes, claires et professionnelles. Maximum 2 phrases. '
                                                    . 'Titre du projet: "' . $title . '". '
                                                    . 'Genere une description concise (max 220 caracteres), concrete et attractive.',
                                            ],
                                        ],
                                    ],
                                ],
                                'generationConfig' => [
                                    'temperature' => 0.8,
                                    'maxOutputTokens' => 140,
                                ],
                            ],
                            'timeout' => 20,
                        ]
                    );

                    $data = $response->toArray(false);
                    $parts = $data['candidates'][0]['content']['parts'] ?? [];
                    $chunks = [];
                    if (is_array($parts)) {
                        foreach ($parts as $part) {
                            if (is_array($part) && isset($part['text'])) {
                                $chunks[] = (string) $part['text'];
                            }
                        }
                    }

                    $description = $normalizeDescription(trim(implode(' ', $chunks)));
                    if ($description !== '') {
                        return new JsonResponse([
                            'ok' => true,
                            'description' => $description,
                            'source' => 'ai',
                            'provider' => 'gemini',
                        ]);
                    }

                    $sample = substr(json_encode($data, JSON_UNESCAPED_UNICODE) ?: '', 0, 300);
                    $debugReasons[] = 'Reponse Gemini vide. Payload: ' . $sample;
                } catch (\Throwable $e) {
                    $debugReasons[] = 'Erreur Gemini: ' . $e->getMessage();
                }

                continue;
            }

            if ($currentProvider === 'openai') {
                if ($openAiApiKey === '') {
                    $debugReasons[] = 'OPENAI_API_KEY vide.';
                    continue;
                }

                try {
                    $response = $httpClient->request('POST', 'https://api.openai.com/v1/chat/completions', [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $openAiApiKey,
                            'Content-Type' => 'application/json',
                        ],
                        'json' => [
                            'model' => $openAiModel,
                            'messages' => [
                                [
                                    'role' => 'system',
                                    'content' => 'Tu rediges des descriptions de projet en francais, courtes, claires et professionnelles. Maximum 2 phrases.',
                                ],
                                [
                                    'role' => 'user',
                                    'content' => 'Titre du projet: "' . $title . '". Genere une description concise (max 220 caracteres), concrete et attractive.',
                                ],
                            ],
                            'max_tokens' => 120,
                            'temperature' => 0.8,
                        ],
                        'timeout' => 20,
                    ]);

                    $data = $response->toArray(false);
                    $description = $normalizeDescription(trim((string) ($data['choices'][0]['message']['content'] ?? '')));
                    if ($description !== '') {
                        return new JsonResponse([
                            'ok' => true,
                            'description' => $description,
                            'source' => 'ai',
                            'provider' => 'openai',
                        ]);
                    }

                    $sample = substr(json_encode($data, JSON_UNESCAPED_UNICODE) ?: '', 0, 300);
                    $debugReasons[] = 'Reponse OpenAI vide. Payload: ' . $sample;
                } catch (\Throwable $e) {
                    $debugReasons[] = 'Erreur OpenAI: ' . $e->getMessage();
                }

                continue;
            }

            try {
                $response = $httpClient->request('POST', $ollamaBaseUrl . '/api/generate', [
                    'headers' => [
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'model' => $ollamaModel,
                        'prompt' => 'Tu rediges des descriptions de projet en francais, courtes, claires et professionnelles. Maximum 2 phrases. '
                            . 'Titre du projet: "' . $title . '". '
                            . 'Genere une description concise (max 220 caracteres), concrete et attractive.',
                        'stream' => false,
                        'options' => [
                            'temperature' => 0.8,
                        ],
                    ],
                    'timeout' => 30,
                ]);

                $data = $response->toArray(false);
                $description = $normalizeDescription(trim((string) ($data['response'] ?? '')));
                if ($description !== '') {
                    return new JsonResponse([
                        'ok' => true,
                        'description' => $description,
                        'source' => 'ai',
                        'provider' => 'ollama',
                    ]);
                }

                $sample = substr(json_encode($data, JSON_UNESCAPED_UNICODE) ?: '', 0, 300);
                $debugReasons[] = 'Reponse Ollama vide. Payload: ' . $sample;
            } catch (\Throwable $e) {
                $debugReasons[] = 'Erreur Ollama: ' . $e->getMessage();
            }
        }

        $json = [
            'ok' => true,
            'description' => $this->buildFallbackDescription($title),
            'source' => 'fallback',
        ];
        if ($isDebug && !empty($debugReasons)) {
            $json['reason'] = implode(' | ', $debugReasons);
        }
        return new JsonResponse($json);
    }


    private function getConnectedChefOrFail(): Utilisateur
    {
        $user = $this->getUser();
        if (!$user instanceof Utilisateur || $user->getId() === null) {
            throw $this->createAccessDeniedException('Vous devez etre connecte.');
        }
        return $user;
    }

    private function buildDemandeScoreData(Collaboration $demande, ?Utilisateur $utilisateur): array
    {
        $score = 30;
        $details = [];
        $pointsRole = 0;
        $pointsDispo = 0;
        $pointsPortfolio = 0;
        $pointsMotivation = 0;
        $pointsProfil = 0;

        $role = strtolower(trim((string) $demande->getRoleSouhaite()));
        if ($role !== '') {
            if (str_contains($role, 'full-stack')) {
                $pointsRole = 22;
            } elseif (str_contains($role, 'back') || str_contains($role, 'front') || str_contains($role, 'designer')) {
                $pointsRole = 17;
            } elseif (str_contains($role, 'qa') || str_contains($role, 'test')) {
                $pointsRole = 14;
            } else {
                $pointsRole = 12;
            }
            $details[] = 'Role renseigne';
        } else {
            $details[] = 'Role manquant';
        }

        $dispo = strtolower(trim((string) $demande->getDisponibilite()));
        if ($dispo !== '') {
            if (str_contains($dispo, 'flex')) {
                $pointsDispo = 18;
            } elseif (str_contains($dispo, '4 h') || str_contains($dispo, '3 h')) {
                $pointsDispo = 16;
            } elseif (str_contains($dispo, '2 h')) {
                $pointsDispo = 12;
            } elseif (str_contains($dispo, 'week') || str_contains($dispo, 'soir')) {
                $pointsDispo = 8;
            } else {
                $pointsDispo = 10;
            }
            $details[] = 'Disponibilite exploitable';
        } else {
            $details[] = 'Disponibilite non precisee';
        }

        $portfolio = trim((string) $demande->getPortfolio());
        if ($portfolio !== '') {
            $isValidUrl = filter_var($portfolio, FILTER_VALIDATE_URL) !== false;
            $pointsPortfolio = $isValidUrl ? 16 : 8;
            $details[] = $isValidUrl ? 'Portfolio valide' : 'Portfolio present';
        } else {
            $details[] = 'Portfolio absent';
        }

        $motivation = trim((string) $demande->getMotivation());
        $motivationLength = $this->safeLength($motivation);
        if ($motivationLength >= 120) {
            $pointsMotivation = 18;
            $details[] = 'Motivation tres detaillee';
        } elseif ($motivationLength >= 60) {
            $pointsMotivation = 14;
            $details[] = 'Motivation correcte';
        } elseif ($motivationLength >= 20) {
            $pointsMotivation = 9;
            $details[] = 'Motivation courte';
        } elseif ($motivationLength > 0) {
            $pointsMotivation = 4;
            $details[] = 'Motivation tres faible';
        } else {
            $details[] = 'Motivation absente';
        }

        if ($utilisateur instanceof Utilisateur) {
            $profileFilled = 0;
            if (trim((string) $utilisateur->getNom()) !== '') {
                $profileFilled++;
            }
            if (trim((string) $utilisateur->getEmail()) !== '') {
                $profileFilled++;
            }
            if (trim((string) $utilisateur->getImage()) !== '') {
                $profileFilled++;
            }
            if (trim((string) $utilisateur->getSkills()) !== '') {
                $profileFilled++;
            }
            $pointsProfil = $profileFilled * 3;
            $details[] = 'Profil complet a ' . ($profileFilled * 25) . '%';
        } else {
            $details[] = 'Profil candidat introuvable';
        }

        $score += $pointsRole + $pointsDispo + $pointsPortfolio + $pointsMotivation + $pointsProfil;
        $score = max(0, min(100, $score));

        $level = $score >= 75 ? 'excellent' : ($score >= 55 ? 'moyen' : 'risque');
        $levelLabel = $score >= 75 ? 'FORT POTENTIEL' : ($score >= 55 ? 'A CONSIDERER' : 'RISQUE');
        $recommendation = $score >= 75
            ? 'Profil solide pour rejoindre le projet rapidement.'
            : ($score >= 55
                ? 'Profil interessant mais demande une verification rapide.'
                : 'Profil fragile: verifier motivation, disponibilite et portfolio avant decision.');

        return [
            'score' => $score,
            'level' => $level,
            'levelLabel' => $levelLabel,
            'recommendation' => $recommendation,
            'breakdown' => [
                'role' => $pointsRole,
                'disponibilite' => $pointsDispo,
                'portfolio' => $pointsPortfolio,
                'motivation' => $pointsMotivation,
                'profil' => $pointsProfil,
            ],
            'highlights' => $details,
            'motivationLength' => $motivationLength,
        ];
    }

    private function safeLength(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return (int) mb_strlen($value);
        }

        return strlen($value);
    }

    private function logAction(
        EntityManagerInterface $entityManager,
        int $actorId,
        ?int $projetId,
        string $action,
        string $details
    ): void {
        $log = new ActionLog();
        $log->setActorId($actorId);
        $log->setProjetId($projetId);
        $log->setAction($action);
        $log->setDetails($details);
        $log->setCreatedAt(new \DateTimeImmutable());

        $entityManager->persist($log);
    }

    private function buildFallbackDescription(string $title): string
    {
        $templates = [
            'Projet "%s" : solution collaborative orientee resultats, avec des objectifs clairs et une execution agile pour produire un impact concret.',
            '"%s" est un projet innovant qui structure le travail d equipe, optimise les processus et accelere la livraison de valeur.',
            'Le projet "%s" vise a proposer une experience utile et moderne, en combinant qualite, performance et collaboration efficace.',
            '"%s" propose une approche pratique pour repondre a un besoin reel, avec une vision claire et des livrables mesurables.',
        ];

        $index = abs(crc32($title)) % count($templates);
        $description = sprintf($templates[$index], $title);

        if (strlen($description) > 220) {
            return substr($description, 0, 217) . '...';
        }

        return $description;
    }

    private function buildChefBadgeProfile(array $stats): array
    {
        $projectsCount = (int) ($stats['projectsCount'] ?? 0);
        $acceptedDemandes = (int) ($stats['acceptedDemandes'] ?? 0);
        $totalDemandes = (int) ($stats['totalDemandes'] ?? 0);
        $totalActions = (int) ($stats['totalActions'] ?? 0);
        $weeklyActions = (int) ($stats['weeklyActions'] ?? 0);

        $acceptRate = $totalDemandes > 0 ? (int) round(($acceptedDemandes / $totalDemandes) * 100) : 0;

        $score = 22
            + min(30, $projectsCount * 8)
            + min(24, $acceptedDemandes * 6)
            + min(18, $weeklyActions * 3)
            + min(12, (int) floor($acceptRate / 10));
        $score = max(0, min(100, $score));

        $level = 'Pilote debutant';
        if ($score >= 80) {
            $level = 'Chef elite';
        } elseif ($score >= 65) {
            $level = 'Chef confirme';
        } elseif ($score >= 45) {
            $level = 'Chef en progression';
        }

        $badges = [
            [
                'title' => 'Batisseur',
                'rule' => '2 projets crees',
                'earned' => $projectsCount >= 2,
            ],
            [
                'title' => 'Recruteur',
                'rule' => '3 demandes acceptees',
                'earned' => $acceptedDemandes >= 3,
            ],
            [
                'title' => 'Actif',
                'rule' => '5 actions cette semaine',
                'earned' => $weeklyActions >= 5,
            ],
            [
                'title' => 'Decisionnaire',
                'rule' => 'Taux acceptation >= 50%',
                'earned' => $totalDemandes >= 2 && $acceptRate >= 50,
            ],
        ];

        $earned = array_values(array_filter($badges, static fn (array $badge): bool => $badge['earned'] === true));
        $nextBadge = null;
        foreach ($badges as $badge) {
            if (!$badge['earned']) {
                $nextBadge = $badge;
                break;
            }
        }

        return [
            'score' => $score,
            'level' => $level,
            'projectsCount' => $projectsCount,
            'acceptedDemandes' => $acceptedDemandes,
            'totalDemandes' => $totalDemandes,
            'acceptRate' => $acceptRate,
            'totalActions' => $totalActions,
            'weeklyActions' => $weeklyActions,
            'earnedBadges' => $earned,
            'nextBadge' => $nextBadge,
        ];
    }

    private function buildContractContent(
        Projet $projet,
        ?Utilisateur $demandeur,
        Collaboration $collaboration,
        Utilisateur $chef
    ): string {
        $today = (new \DateTimeImmutable())->format('d/m/Y');
        $projetTitre = trim((string) $projet->getTitre());
        $candidatNom = trim((string) ($demandeur?->getNom() ?? 'Candidat'));
        $chefNom = trim((string) ($chef->getNom() ?? 'Chef de projet'));
        $role = trim((string) ($collaboration->getRoleSouhaite() ?? 'Role non precise'));
        $disponibilite = trim((string) ($collaboration->getDisponibilite() ?? 'Disponibilite non precisee'));

        return "CONTRAT DE PARTICIPATION\n\n"
            . "Date: {$today}\n"
            . "Projet: {$projetTitre}\n"
            . "Chef de projet: {$chefNom}\n"
            . "Participant: {$candidatNom}\n\n"
            . "1) Le participant rejoint le projet avec le role: {$role}.\n"
            . "2) Disponibilite declaree: {$disponibilite}.\n"
            . "3) Le participant s'engage a respecter la confidentialite et les delais.\n"
            . "4) Le chef de projet s'engage a fournir un cadre de travail clair.\n"
            . "5) Ce contrat est final des la signature du participant.\n";
    }
}