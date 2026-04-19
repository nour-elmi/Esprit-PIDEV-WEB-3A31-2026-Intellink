<?php

namespace App\Controller;

use App\Entity\Collaboration;
use App\Entity\ContratParticipation;
use App\Entity\FavoriProjet;
use App\Entity\Projet;
use App\Entity\Utilisateur;
use App\PaginationBundle\Service\PaginationService;
use App\Service\ParticipationBadgeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ProjetController extends AbstractController
{
    #[Route('/projets', name: 'app_projet_index')]
    public function index(
        Request $request,
        EntityManagerInterface $entityManager,
        ParticipationBadgeService $participationBadgeService,
        PaginationService $paginationService
    ): Response
    {
        $projectsQb = $entityManager->getRepository(Projet::class)
            ->createQueryBuilder('p')
            ->orderBy('p.id', 'DESC');

        $page = $paginationService->getPageFromRequest($request);
        $pagination = $paginationService->paginateQueryBuilder($projectsQb, $page, 6);
        $projets = $pagination->getItems();

        $demandesUtilisateur = [];
        $user = $this->getUser();
        if ($user && method_exists($user, 'getId') && $user->getId() !== null) {
            $demandesUtilisateur = $entityManager->getRepository(Collaboration::class)->findBy(
                ['userId' => $user->getId()],
                ['dateCreation' => 'DESC', 'id' => 'DESC']
            );
        }

        $badgeProfile = $participationBadgeService->buildForDemandes($demandesUtilisateur);
        $favoriteProjectIds = [];
        $favoriteProjects = [];
        $userStatusNotifications = [];

        if ($user && method_exists($user, 'getId') && $user->getId() !== null) {
            $favoris = $entityManager->getRepository(FavoriProjet::class)->findBy(
                ['userId' => (int) $user->getId()],
                ['createdAt' => 'DESC', 'id' => 'DESC']
            );

            foreach ($favoris as $favori) {
                $projectId = $favori->getProjet()?->getId();
                if ($projectId === null) {
                    continue;
                }
                $favoriteProjectIds[] = $projectId;
                $favoriteProjects[] = $favori->getProjet();
            }

            $contrats = $entityManager->getRepository(ContratParticipation::class)->findBy([
                'userId' => (int) $user->getId(),
            ]);
            $contratsByDemandeId = [];
            foreach ($contrats as $contrat) {
                $demandeId = $contrat->getCollaboration()?->getId();
                if ($demandeId === null) {
                    continue;
                }
                $contratsByDemandeId[(int) $demandeId] = $contrat;
            }

            foreach ($demandesUtilisateur as $demande) {
                $demandeId = (int) ($demande->getId() ?? 0);
                if ($demandeId <= 0) {
                    continue;
                }

                $project = $demande->getProjet();
                $projectTitle = trim((string) ($project?->getTitre() ?? ('Projet #' . $demandeId)));
                $etat = strtoupper((string) ($demande->getEtat() ?? ''));
                $contrat = $contratsByDemandeId[$demandeId] ?? null;

                if ($etat === 'ACCEPTEE' && $contrat instanceof ContratParticipation && $contrat->getUserSignedAt() === null) {
                    $eventDate = $contrat->getUpdatedAt() ?? $contrat->getCreatedAt() ?? $demande->getDateCreation() ?? new \DateTimeImmutable();
                    $userStatusNotifications[] = [
                        'id' => 'contract_to_sign_' . $demandeId,
                        'title' => 'Veuillez signer le contrat - ' . $projectTitle,
                        'kicker' => 'Contrat a signer',
                        'type' => 'contract-sign',
                        'createdAt' => $eventDate->format(\DateTimeInterface::ATOM),
                        'href' => $this->generateUrl('app_participation_contrat_show', ['id' => $demandeId]),
                    ];
                    continue;
                }

                if ($etat === 'REFUSEE') {
                    $eventDate = $demande->getDateCreation() ?? new \DateTimeImmutable();
                    $userStatusNotifications[] = [
                        'id' => 'demande_refusee_' . $demandeId,
                        'title' => 'Demande refusee - ' . $projectTitle,
                        'kicker' => 'Demande refusee',
                        'type' => 'refused',
                        'createdAt' => $eventDate->format(\DateTimeInterface::ATOM),
                        'href' => $this->generateUrl('app_mes_demandes'),
                    ];
                }
            }

            usort(
                $userStatusNotifications,
                static function (array $a, array $b): int {
                    return strcmp((string) ($b['createdAt'] ?? ''), (string) ($a['createdAt'] ?? ''));
                }
            );
        }

        return $this->render('projet/index.html.twig', [
            'projets' => $projets,
            'pagination' => $pagination,
            'badgeProfile' => $badgeProfile,
            'favoriteProjectIds' => array_values(array_unique($favoriteProjectIds)),
            'favoriteProjects' => $favoriteProjects,
            'userStatusNotifications' => $userStatusNotifications,
        ]);
    }

    #[Route('/projets/{id}/favori/toggle', name: 'app_projet_favori_toggle', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function toggleFavori(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof Utilisateur || $user->getId() === null) {
            return new JsonResponse([
                'ok' => false,
                'message' => 'Utilisateur non connecte.',
            ], 401);
        }

        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('toggle_favori_projet', $token)) {
            return new JsonResponse([
                'ok' => false,
                'message' => 'Token CSRF invalide.',
            ], 403);
        }

        $projet = $entityManager->getRepository(Projet::class)->find($id);
        if (!$projet) {
            return new JsonResponse([
                'ok' => false,
                'message' => 'Projet introuvable.',
            ], 404);
        }

        $repo = $entityManager->getRepository(FavoriProjet::class);
        $existing = $repo->findOneBy([
            'userId' => (int) $user->getId(),
            'projet' => $projet,
        ]);

        $isFavorite = false;
        if ($existing) {
            $entityManager->remove($existing);
        } else {
            $favori = new FavoriProjet();
            $favori->setUserId((int) $user->getId());
            $favori->setProjet($projet);
            $favori->setCreatedAt(new \DateTimeImmutable());
            $entityManager->persist($favori);
            $isFavorite = true;
        }

        $entityManager->flush();

        $favoritesCount = (int) $repo->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('f.userId = :uid')
            ->setParameter('uid', (int) $user->getId())
            ->getQuery()
            ->getSingleScalarResult();

        return new JsonResponse([
            'ok' => true,
            'isFavorite' => $isFavorite,
            'favoritesCount' => $favoritesCount,
            'projectId' => $id,
        ]);
    }

    #[Route('/projets/favoris', name: 'app_projet_favoris', methods: ['GET'])]
    public function favoris(EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user instanceof Utilisateur || $user->getId() === null) {
            throw $this->createAccessDeniedException('Vous devez etre connecte.');
        }

        $favoris = $entityManager->getRepository(FavoriProjet::class)->findBy(
            ['userId' => (int) $user->getId()],
            ['createdAt' => 'DESC', 'id' => 'DESC']
        );

        $favoriteProjects = [];
        foreach ($favoris as $favori) {
            $projet = $favori->getProjet();
            if ($projet !== null) {
                $favoriteProjects[] = $projet;
            }
        }

        return $this->render('projet/favoris.html.twig', [
            'favoriteProjects' => $favoriteProjects,
            'favoritesCount' => count($favoriteProjects),
        ]);
    }

    #[Route('/projets/{id}', name: 'app_projet_show', requirements: ['id' => '\d+'])]
    public function show(int $id, EntityManagerInterface $entityManager): Response
    {
        $projet = $entityManager->getRepository(Projet::class)->find($id);

        if (!$projet) {
            throw $this->createNotFoundException('Projet introuvable.');
        }

        $alreadyParticipated = false;
        $user = $this->getUser();

        if ($user && method_exists($user, 'getId') && $user->getId() !== null) {
            $alreadyParticipated = $entityManager->getRepository(Collaboration::class)->findOneBy([
                'userId' => $user->getId(),
                'projet' => $projet,
            ]) !== null;
        }

        return $this->render('projet/show.html.twig', [
            'projet' => $projet,
            'alreadyParticipated' => $alreadyParticipated,
        ]);
    }

    #[Route('/projets/cv/recommandations', name: 'app_projet_scan_cv', methods: ['GET'])]
    public function scanCvRecommendations(EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof Utilisateur || $user->getId() === null) {
            return new JsonResponse([
                'ok' => false,
                'message' => 'Utilisateur non connecte.',
            ], 401);
        }

        $cvFile = trim((string) $user->getSkills());
        if ($cvFile === '') {
            return new JsonResponse([
                'ok' => false,
                'message' => 'Aucun CV lie au profil. Ajoute un CV dans ton profil.',
                'profileUrl' => $this->generateUrl('app_profile'),
            ], 400);
        }

        $cvDirectory = (string) $this->getParameter('cv_directory');
        $cvPath = rtrim($cvDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . basename($cvFile);
        if (!is_file($cvPath)) {
            return new JsonResponse([
                'ok' => false,
                'message' => 'Fichier CV introuvable sur le serveur. Recharge le CV depuis ton profil.',
                'profileUrl' => $this->generateUrl('app_profile'),
            ], 404);
        }

        $cvText = $this->extractCvText($cvPath);
        if (trim($cvText) === '') {
            return new JsonResponse([
                'ok' => false,
                'message' => 'Lecture du CV impossible. Essaie un PDF plus simple ou recharge ton CV.',
            ], 422);
        }

        $cvKeywords = $this->extractKeywords($cvText);
        if (empty($cvKeywords)) {
            return new JsonResponse([
                'ok' => false,
                'message' => 'Le CV ne contient pas assez d’informations exploitables.',
            ], 422);
        }

        $ignoredTokens = $this->buildIgnoredTokens($user);
        $cvKeywords = array_values(array_filter(
            $cvKeywords,
            fn (string $kw): bool => $this->isUsefulKeyword($kw, $ignoredTokens)
        ));

        $technicalLexicon = $this->getTechnicalLexicon();
        $cvTechnicalKeywords = array_values(array_filter(
            $cvKeywords,
            static fn (string $kw): bool => in_array($kw, $technicalLexicon, true)
        ));

        $projects = $entityManager->getRepository(Projet::class)->findBy([], ['id' => 'DESC']);
        $recommendations = [];

        foreach ($projects as $project) {
            $title = (string) $project->getTitre();
            $description = (string) ($project->getDescription() ?? '');

            $titleKeywords = $this->extractKeywords($title);
            $allKeywords = $this->extractKeywords($title . ' ' . $description);
            $titleKeywords = array_values(array_filter(
                $titleKeywords,
                fn (string $kw): bool => $this->isUsefulKeyword($kw, $ignoredTokens)
            ));
            $allKeywords = array_values(array_filter(
                $allKeywords,
                fn (string $kw): bool => $this->isUsefulKeyword($kw, $ignoredTokens)
            ));

            $projectTextNormalized = $this->normalizeText($title . ' ' . $description);
            $projectTitleNormalized = $this->normalizeText($title);

            $matchedAll = array_values(array_intersect($cvKeywords, $allKeywords));

            // Matching souple: accepte les correspondances "contains" pour éviter un filtre trop strict.
            $softMatched = [];
            foreach (array_slice($cvKeywords, 0, 90) as $kw) {
                if (strlen($kw) < 4) {
                    continue;
                }
                if (str_contains($projectTextNormalized, $kw)) {
                    $softMatched[] = $kw;
                }
            }
            $matchedAll = array_values(array_unique(array_merge($matchedAll, $softMatched)));

            $matchedTitle = array_values(array_intersect($cvKeywords, $titleKeywords));
            foreach (array_slice($cvKeywords, 0, 90) as $kw) {
                if (strlen($kw) >= 4 && str_contains($projectTitleNormalized, $kw)) {
                    $matchedTitle[] = $kw;
                }
            }
            $matchedTitle = array_values(array_unique($matchedTitle));
            $titleMatchCount = count($matchedTitle);
            $descMatchCount = max(0, count($matchedAll) - $titleMatchCount);

            $projectTechnicalKeywords = array_values(array_filter(
                $allKeywords,
                static fn (string $kw): bool => in_array($kw, $technicalLexicon, true)
            ));
            $technicalOverlap = array_values(array_intersect($cvTechnicalKeywords, $projectTechnicalKeywords));
            $technicalOverlapCount = count(array_unique($technicalOverlap));
            $projectQualityPenalty = $this->isLikelyNoiseProject($title, $description, $projectTechnicalKeywords) ? 18 : 0;

            // Similarité lexicale globale (utile si le CV PDF est imparfait mais contient des mots clés).
            $cvSlice = array_slice($cvKeywords, 0, 80);
            $projectSlice = array_slice(array_values(array_unique($allKeywords)), 0, 80);
            $globalOverlap = count(array_intersect($cvSlice, $projectSlice));
            $globalSimilarity = 0;
            if (!empty($projectSlice)) {
                $globalSimilarity = (int) round(($globalOverlap / max(1, count($projectSlice))) * 100);
            }

            $rawScore = 10
                + ($technicalOverlapCount * 24)
                + ($titleMatchCount * 8)
                + ($descMatchCount * 4)
                + min(14, $globalSimilarity);
            $rawScore -= $projectQualityPenalty;
            $score = max(0, min(99, $rawScore));

            // Garde uniquement les projets plausibles et techniquement cohérents.
            $hasMeaningfulMatch = ($technicalOverlapCount >= 1)
                || (count($matchedAll) >= 2 && count($projectTechnicalKeywords) >= 1)
                || ($globalSimilarity >= 12 && count($projectTechnicalKeywords) >= 1);
            if (!$hasMeaningfulMatch) {
                continue;
            }

            $highlights = array_slice(array_values(array_unique(array_merge($technicalOverlap, $matchedAll))), 0, 6);
            $recommendations[] = [
                'projectId' => $project->getId(),
                'title' => $title,
                'description' => $description !== '' ? mb_substr($description, 0, 150) : 'Description non disponible.',
                'status' => (string) $project->getStatut(),
                'score' => $score,
                'highlights' => $highlights,
                'url' => $this->generateUrl('app_projet_show', ['id' => $project->getId()]),
            ];
        }

        // Fallback UX: s'il n'y a aucun match direct, on propose uniquement
        // des projets qui contiennent de vraies technos (et on exclut le bruit).
        if (empty($recommendations)) {
            foreach (array_slice($projects, 0, 6) as $project) {
                $title = (string) $project->getTitre();
                $description = (string) ($project->getDescription() ?? '');
                $cleanTitle = $this->normalizeText($title);
                $cleanDesc = $this->normalizeText($description);

                $allKeywords = $this->extractKeywords($title . ' ' . $description);
                $allKeywords = array_values(array_filter(
                    $allKeywords,
                    fn (string $kw): bool => $this->isUsefulKeyword($kw, $ignoredTokens)
                ));
                $projectTechnicalKeywords = array_values(array_unique(array_intersect($allKeywords, $technicalLexicon)));

                if ($this->isLikelyNoiseProject($title, $description, $projectTechnicalKeywords)) {
                    continue;
                }
                if (count($projectTechnicalKeywords) === 0) {
                    continue;
                }

                $baseScore = 44 + min(28, count($projectTechnicalKeywords) * 7);
                if (strlen($cleanDesc) >= 40) {
                    $baseScore += 6;
                }
                if (
                    str_contains($cleanDesc, 'developp')
                    || str_contains($cleanDesc, 'application')
                    || str_contains($cleanDesc, 'site')
                    || str_contains($cleanTitle, 'python')
                    || str_contains($cleanTitle, 'web')
                    || str_contains($cleanTitle, 'website')
                ) {
                    $baseScore += 10;
                }

                $recommendations[] = [
                    'projectId' => $project->getId(),
                    'title' => $title,
                    'description' => $description !== '' ? mb_substr($description, 0, 150) : 'Description non disponible.',
                    'status' => (string) $project->getStatut(),
                    'score' => min(90, $baseScore),
                    'highlights' => array_slice($projectTechnicalKeywords, 0, 6),
                    'url' => $this->generateUrl('app_projet_show', ['id' => $project->getId()]),
                ];
            }
        }

        usort($recommendations, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);
        $recommendations = array_slice($recommendations, 0, 6);

        return new JsonResponse([
            'ok' => true,
            'count' => count($recommendations),
            'recommendations' => $recommendations,
            'scannedKeywords' => array_slice($cvKeywords, 0, 20),
        ]);
    }

    private function extractCvText(string $path): string
    {
        $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return '';
        }

        if ($ext === 'txt' || $ext === 'md') {
            return trim($raw);
        }

        if ($ext === 'pdf') {
            // Extraction légère sans dépendance externe: utile pour CV PDF simples.
            $textParts = [];
            if (preg_match_all('/\(([^()]*)\)/', $raw, $matches) && !empty($matches[1])) {
                $textParts = $matches[1];
            }
            if (empty($textParts)) {
                preg_match_all('/[A-Za-z0-9\+\#\.\-\_\/]{3,}/', $raw, $fallback);
                $textParts = $fallback[0] ?? [];
            }

            $text = trim(implode(' ', array_slice($textParts, 0, 12000)));
            return preg_replace('/\s+/', ' ', $text) ?? $text;
        }

        return trim($raw);
    }

    private function extractKeywords(string $text): array
    {
        $normalized = $this->normalizeText($text);
        if ($normalized === '') {
            return [];
        }

        $parts = preg_split('/[^a-z0-9\+#\.]+/i', $normalized) ?: [];
        $stopWords = [
            'le','la','les','de','des','du','un','une','et','ou','en','dans','pour','par','avec',
            'sur','au','aux','ce','cet','cette','ces','mon','ma','mes','ton','ta','tes','son','sa',
            'ses','notre','votre','leurs','the','and','for','with','from','this','that','your','you',
            'html','http','https','www','com'
        ];
        $stopSet = array_fill_keys($stopWords, true);

        $keywords = [];
        foreach ($parts as $part) {
            $w = trim($part);
            if ($w === '') {
                continue;
            }
            // On garde plus de tokens courts pour faciliter le matching (ex: ia, ui, qa, ml).
            if (strlen($w) < 2) {
                continue;
            }
            if (isset($stopSet[$w])) {
                continue;
            }
            if (ctype_digit($w)) {
                continue;
            }
            $keywords[$w] = ($keywords[$w] ?? 0) + 1;
        }

        arsort($keywords);
        return array_slice(array_keys($keywords), 0, 120);
    }

    private function normalizeText(string $value): string
    {
        $value = mb_strtolower($value, 'UTF-8');
        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($transliterated) && $transliterated !== '') {
            $value = $transliterated;
        }
        return trim($value);
    }

    private function buildIgnoredTokens(Utilisateur $user): array
    {
        $source = $this->normalizeText((string) $user->getNom() . ' ' . (string) $user->getEmail());
        $parts = preg_split('/[^a-z0-9]+/i', $source) ?: [];
        $parts = array_values(array_filter($parts, static fn (string $p): bool => strlen($p) >= 3));

        $base = [
            'projet', 'project', 'cv', 'competences', 'competence',
            'bachirou', 'bachirouuu', 'aaaa', 'test'
        ];

        return array_values(array_unique(array_merge($base, $parts)));
    }

    private function isUsefulKeyword(string $kw, array $ignoredTokens): bool
    {
        if (in_array($kw, $ignoredTokens, true)) {
            return false;
        }

        if (preg_match('/^(.)\1{2,}$/', $kw)) {
            return false;
        }

        // Ignore tokens avec majorité chiffres ou très bruités
        $digits = preg_match_all('/\d/', $kw, $m) ?: 0;
        if (strlen($kw) > 0 && ($digits / strlen($kw)) > 0.45) {
            return false;
        }

        return true;
    }

    private function isLikelyNoiseProject(string $title, string $description, array $projectTechnicalKeywords): bool
    {
        $titleNorm = $this->normalizeText($title);
        $descNorm = $this->normalizeText($description);
        $titleWords = preg_split('/[^a-z0-9]+/i', $titleNorm) ?: [];
        $titleWords = array_values(array_filter($titleWords, static fn (string $w): bool => $w !== ''));

        $veryShortDesc = strlen(trim($descNorm)) < 20;
        $fewTitleWords = count($titleWords) <= 1;
        $noTech = count($projectTechnicalKeywords) === 0;
        $repeatedChars = preg_match('/([a-z])\1{3,}/', $titleNorm) === 1;

        return ($veryShortDesc && $noTech) || ($fewTitleWords && $noTech) || $repeatedChars;
    }

    private function getTechnicalLexicon(): array
    {
        return [
            'php','symfony','laravel','java','spring','python','django','flask','fastapi','c','cpp','csharp',
            'javascript','typescript','node','nodejs','react','angular','vue','nextjs','html','css','sass',
            'bootstrap','tailwind','mysql','postgresql','sqlite','mongodb','redis','docker','kubernetes',
            'git','github','gitlab','devops','api','rest','graphql','microservices','aws','azure','gcp',
            'machine','learning','ia','ai','nlp','data','analytics','powerbi','tableau','figma','ui','ux',
            'mobile','android','ios','flutter','reactnative','security','cybersecurity','testing','qa',
            'developpement','developpeur','developpeuse','fullstack','backend','frontend','site','web',
            'application','logiciel','base','donnees','database','reseau','cloud','automatisation','scrum',
            'agile','uml','sql','nosql','jira','postman','linux','windows','typescript','javafx'
        ];
    }
}
