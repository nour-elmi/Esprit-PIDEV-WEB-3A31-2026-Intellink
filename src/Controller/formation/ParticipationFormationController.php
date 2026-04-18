<?php

namespace App\Controller\formation;

use App\Entity\formation\FavoriFormation;
use App\Entity\formation\Participation;
use App\Entity\formation\ProgressionFormation;
use App\Repository\FavoriFormationRepository;
use App\Repository\FormationRepository;
use App\Repository\ParticipationRepository;
use App\Repository\ProgressionFormationRepository;
use App\Repository\QuizRepository;
use App\Service\formation\CertificateMailerService;
use App\Service\formation\FormationAdvisorService;
use App\Service\formation\FacePlusPlusService;
use App\Service\formation\TechNewsService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/formation')]
final class ParticipationFormationController extends AbstractController
{
    #[Route('/formations', name: 'app_utilisateur_formations')]
    public function formations(
        Request $request,
        FormationRepository $repo,
        ParticipationRepository $participationRepo,
        FavoriFormationRepository $favoriRepository,
        TechNewsService $techNewsService,
        PaginatorInterface $paginator
    ): Response
    {
        $utilisateur = $this->getUser();
        $idUtilisateur = $utilisateur->getId();

        $formationsQuery = $repo->createQueryBuilder('f')
            ->orderBy('f.idFormation', 'DESC');

        $formations = $paginator->paginate(
            $formationsQuery,
            max(1, (int) $request->query->get('page', 1)),
            6
        );
        
        $participations = $participationRepo->findBy(['idUtilisateur' => $idUtilisateur]);
        $inscritIds = array_map(
            fn($p) => $p->getFormation()->getIdFormation(),
            $participations
        );
        $favoriIds = $favoriRepository->findFormationIdsByUtilisateur($idUtilisateur);
        $favoris = $favoriRepository->findBy(
            ['idUtilisateur' => $idUtilisateur],
            ['dateAjout' => 'DESC']
        );

        $suggestedFormations = [];
        $addedSuggestionIds = [];

        $domainScores = [];
        foreach ($participations as $participation) {
            $domaine = trim((string) $participation->getFormation()?->getDomaine());
            if ($domaine !== '') {
                $domainScores[$domaine] = ($domainScores[$domaine] ?? 0) + 3;
            }
        }
        foreach ($favoris as $favori) {
            $domaine = trim((string) $favori->getFormation()?->getDomaine());
            if ($domaine !== '') {
                $domainScores[$domaine] = ($domainScores[$domaine] ?? 0) + 2;
            }
        }
        arsort($domainScores);
        $preferredDomaines = array_keys($domainScores);

        $baseExcludeIds = array_values(array_unique(array_map(
            'intval',
            array_merge($inscritIds, $favoriIds)
        )));

        if (count($suggestedFormations) < 4 && $preferredDomaines !== []) {
            $excludeIds = array_merge($baseExcludeIds, array_keys($addedSuggestionIds));
            $domainSuggestions = $repo->findTopFormationsByDomainExcludingIds(
                $preferredDomaines,
                $excludeIds,
                4 - count($suggestedFormations)
            );

            foreach ($domainSuggestions as $candidate) {
                $candidateId = $candidate->getIdFormation();
                if (!$candidateId || isset($addedSuggestionIds[$candidateId])) {
                    continue;
                }
                $suggestedFormations[] = $candidate;
                $addedSuggestionIds[$candidateId] = true;
            }
        }

        if (count($suggestedFormations) < 4) {
            $fallbackSuggestions = $repo->findTopFormationsExcludingIds(
                array_merge($baseExcludeIds, array_keys($addedSuggestionIds)),
                4 - count($suggestedFormations)
            );

            foreach ($fallbackSuggestions as $candidate) {
                $candidateId = $candidate->getIdFormation();
                if (!$candidateId || isset($addedSuggestionIds[$candidateId])) {
                    continue;
                }
                $suggestedFormations[] = $candidate;
                $addedSuggestionIds[$candidateId] = true;
            }
        }

        $techNews = $techNewsService->getLatestTechNews(5);

        return $this->render('formation/utilisateur_formation/formations.html.twig', [
            'formations' => $formations,
            'inscritIds' => $inscritIds,
            'favoriIds' => $favoriIds,
            'suggestedFormations' => $suggestedFormations,
            'techNews' => $techNews,
        ]);
    }

    #[Route('/aide-choix', name: 'app_utilisateur_aide_choix', methods: ['POST'])]
    public function aideChoix(Request $request, FormationAdvisorService $advisorService): JsonResponse
    {
        $payload = json_decode((string) $request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json(['error' => 'Payload invalide'], 400);
        }

        $answers = [
            'q1' => (string) ($payload['q1'] ?? ''),
            'q2' => (string) ($payload['q2'] ?? ''),
            'q3' => (string) ($payload['q3'] ?? ''),
            'q4' => (string) ($payload['q4'] ?? ''),
        ];

        $advice = $advisorService->advise($answers);

        return $this->json($advice);
    }

    #[Route('/formation/{id}', name: 'app_utilisateur_formation_show')]
    public function show(
        int $id,
        FormationRepository $repo,
        ParticipationRepository $participationRepo,
        FavoriFormationRepository $favoriRepository,
        ProgressionFormationRepository $progressionRepository,
        QuizRepository $quizRepository
    ): Response
    {
        $formation = $repo->find($id);

        if (!$formation) {
            throw $this->createNotFoundException('Formation introuvable');
        }

        $utilisateur = $this->getUser();
        $idUtilisateur = $utilisateur->getId();

        $dejaInscrit = $participationRepo->findOneBy([
            'formation' => $formation,
            'idUtilisateur' => $idUtilisateur,
        ]);

        $quiz = $quizRepository->findOneBy(['formation' => $formation]);
        $estFavori = $favoriRepository->findOneBy([
            'formation' => $formation,
            'idUtilisateur' => $idUtilisateur,
        ]) !== null;
        $progression = $dejaInscrit ? $progressionRepository->findOneBy([
            'participation' => $dejaInscrit,
        ]) : null;
        $hasQuiz = $quiz !== null;
        $quizCompleted = $progression ? $progression->getQuizScore() !== null : false;
        $progressionPercent = $progression ? $progression->getPourcentage() : 0.0;
        if ($hasQuiz && !$quizCompleted && $progressionPercent > 95.0) {
            $progressionPercent = 95.0;
        }
        $progressionSeconds = $progression ? $progression->getVideoSeconds() : 0;
        $progressionDuration = $progression ? $progression->getVideoDuration() : 0;

        return $this->render('formation/utilisateur_formation/formation_show.html.twig', [
            'formation' => $formation,
            'dejaInscrit' => $dejaInscrit,
            'quiz' => $quiz,
            'estFavori' => $estFavori,
            'progressionPercent' => $progressionPercent,
            'progressionSeconds' => $progressionSeconds,
            'progressionDuration' => $progressionDuration,
            'hasQuiz' => $hasQuiz,
            'quizCompleted' => $quizCompleted,
        ]);
    }

    #[Route('/progression/{id}/video', name: 'app_utilisateur_progression_video', methods: ['POST'])]
    public function updateProgressionVideo(
        int $id,
        Request $request,
        FormationRepository $formationRepository,
        ParticipationRepository $participationRepository,
        ProgressionFormationRepository $progressionRepository,
        QuizRepository $quizRepository,
        EntityManagerInterface $em
    ): JsonResponse {
        $formation = $formationRepository->find($id);
        if (!$formation) {
            return $this->json(['ok' => false, 'error' => 'Formation introuvable'], 404);
        }

        $utilisateur = $this->getUser();
        $idUtilisateur = $utilisateur->getId();

        $participation = $participationRepository->findOneBy([
            'formation' => $formation,
            'idUtilisateur' => $idUtilisateur,
        ]);
        if (!$participation) {
            return $this->json(['ok' => false, 'error' => 'Inscription requise'], 403);
        }

        $payload = json_decode((string) $request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json(['ok' => false, 'error' => 'Payload invalide'], 400);
        }

        $seconds = max(0, (int) ($payload['seconds'] ?? 0));
        $duration = max(0, (int) ($payload['duration'] ?? 0));

        if ($duration <= 0) {
            return $this->json(['ok' => false, 'error' => 'Duree invalide'], 400);
        }

        if ($seconds > $duration) {
            $seconds = $duration;
        }

        $progression = $progressionRepository->findOneBy([
            'participation' => $participation,
        ]);

        if (!$progression) {
            $progression = (new ProgressionFormation())
                ->setFormation($formation)
                ->setParticipation($participation)
                ->setIdUtilisateur($idUtilisateur);
            $em->persist($progression);
        }

        $maxSeconds = max($progression->getVideoSeconds(), $seconds);
        $maxDuration = max($progression->getVideoDuration(), $duration);
        if ($maxSeconds > $maxDuration) {
            $maxSeconds = $maxDuration;
        }

        $videoPercent = $maxDuration > 0 ? round(($maxSeconds / $maxDuration) * 100, 2) : 0.0;
        $hasQuiz = $quizRepository->findOneBy(['formation' => $formation]) !== null;
        $quizCompleted = $progression->getQuizScore() !== null;

        $pourcentage = $videoPercent;
        $statut = 'non_commence';
        $quizRequired = false;
        if ($hasQuiz && !$quizCompleted) {
            $pourcentage = min($videoPercent, 95.0);
            if ($pourcentage > 0.0) {
                $statut = 'en_cours';
            }
            if ($videoPercent >= 95.0) {
                $quizRequired = true;
            }
        } else {
            if ($pourcentage >= 95.0) {
                $statut = 'termine';
            } elseif ($pourcentage > 0.0) {
                $statut = 'en_cours';
            }
        }

        $progression
            ->setVideoSeconds($maxSeconds)
            ->setVideoDuration($maxDuration)
            ->setPourcentage($pourcentage)
            ->setStatut($statut)
            ->setUpdatedAt(new \DateTimeImmutable());

        $em->flush();

        return $this->json([
            'ok' => true,
            'progress' => $pourcentage,
            'seconds' => $maxSeconds,
            'duration' => $maxDuration,
            'status' => $statut,
            'quizRequired' => $quizRequired,
            'quizCompleted' => $quizCompleted,
            'hasQuiz' => $hasQuiz,
            'quizUrl' => $this->generateUrl('app_utilisateur_quiz_passer', ['id' => $formation->getIdFormation()]),
        ]);
    }

    #[Route('/favori/{id}/toggle', name: 'app_utilisateur_favori_toggle', methods: ['POST'])]
    public function toggleFavori(
        int $id,
        Request $request,
        FormationRepository $formationRepository,
        FavoriFormationRepository $favoriRepository,
        EntityManagerInterface $em
    ): Response {
        $formation = $formationRepository->find($id);
        if (!$formation) {
            throw $this->createNotFoundException('Formation introuvable');
        }

        $submittedToken = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('toggle_favori_' . $id, $submittedToken)) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $utilisateur = $this->getUser();
        $idUtilisateur = $utilisateur->getId();

        $favori = $favoriRepository->findOneBy([
            'formation' => $formation,
            'idUtilisateur' => $idUtilisateur,
        ]);

        $isFavori = false;
        $message = '';
        if ($favori) {
            $em->remove($favori);
            $message = 'Formation retiree des favoris.';
            $isFavori = false;
        } else {
            $favori = (new FavoriFormation())
                ->setFormation($formation)
                ->setIdUtilisateur($idUtilisateur)
                ->setDateAjout(new \DateTimeImmutable());
            $em->persist($favori);
            $message = 'Formation ajoutee aux favoris.';
            $isFavori = true;
        }

        $em->flush();

        if ($request->isXmlHttpRequest()) {
            return $this->json([
                'ok' => true,
                'isFavori' => $isFavori,
                'message' => $message,
                'formationId' => $formation->getIdFormation(),
            ]);
        }

        $this->addFlash('success', $message);

        $returnRoute = (string) $request->request->get('return_route', 'list');
        if ($returnRoute === 'show') {
            return $this->redirectToRoute('app_utilisateur_formation_show', ['id' => $formation->getIdFormation()]);
        }

        return $this->redirectToRoute('app_utilisateur_formations');
    }

    #[Route('/participer/{id}', name: 'app_utilisateur_participer')]
    public function participer(
        int $id,
        Request $request,
        FormationRepository $repo,
        ParticipationRepository $participationRepo,
        EntityManagerInterface $em
    ): Response {
        $formation = $repo->find($id);

        if (!$formation) {
            throw $this->createNotFoundException('Formation introuvable');
        }

        $utilisateur = $this->getUser();
        $idUtilisateur = $utilisateur->getId();

        $dejaInscrit = $participationRepo->findOneBy([
            'formation' => $formation,
            'idUtilisateur' => $idUtilisateur,
        ]);

        if ($dejaInscrit) {
            $this->addFlash('warning', 'Vous êtes déjà inscrit à cette formation !');
            return $this->redirectToRoute('app_utilisateur_formation_show', ['id' => $id]);
        }

        $errors = [];
        $old = [];

        if ($request->isMethod('POST')) {

            $posteActuel = trim($request->request->get('posteActuel'));
            $attentes = trim($request->request->get('attentes'));

            $old = $request->request->all();

            if (empty($posteActuel)) {
                $errors['posteActuel'] = "Le poste est obligatoire";
            } elseif (strlen($posteActuel) < 3) {
                $errors['posteActuel'] = "Minimum 3 caractères";
            } elseif (!preg_match("/^[a-zA-ZÀ-ÿ\s]+$/", $posteActuel)) {
                $errors['posteActuel'] = "Seulement des lettres autorisées";
            }

            if (empty($attentes)) {
                $errors['attentes'] = "Les attentes sont obligatoires";
            } elseif (strlen($attentes) < 10) {
                $errors['attentes'] = "Minimum 10 caractères";
            }

            if (empty($errors)) {
                $participation = new Participation();
                $participation->setFormation($formation);
                $participation->setIdUtilisateur($idUtilisateur);
                $participation->setPosteActuel($posteActuel);
                $participation->setAttentes($attentes);
                $participation->setDateInscription(new \DateTime());
                $participation->setScore(0);

                $em->persist($participation);
                $em->flush();

                $this->addFlash('success', 'Inscription réussie !');
                return $this->redirectToRoute('app_utilisateur_mes_participations');
            }

            return $this->render('formation/utilisateur_formation/participer.html.twig', [
                'formation' => $formation,
                'errors' => $errors,
                'old' => $old
            ]);
        }

        return $this->render('formation/utilisateur_formation/participer.html.twig', [
            'formation' => $formation
        ]);
    }

    #[Route('/quiz/{id}/passer', name: 'app_utilisateur_quiz_passer', methods: ['GET', 'POST'])]
    public function passerQuiz(
        int $id,
        Request $request,
        FormationRepository $formationRepository,
        ParticipationRepository $participationRepository,
        ProgressionFormationRepository $progressionRepository,
        QuizRepository $quizRepository,
        CertificateMailerService $certificateMailerService,
        EntityManagerInterface $em
    ): Response {
        $formation = $formationRepository->find($id);
        if (!$formation) {
            throw $this->createNotFoundException('Formation introuvable');
        }

        $utilisateur = $this->getUser();
        $idUtilisateur = $utilisateur->getId();

        $participation = $participationRepository->findOneBy([
            'formation' => $formation,
            'idUtilisateur' => $idUtilisateur,
        ]);

        if (!$participation) {
            $this->addFlash('warning', "Inscrivez-vous a la formation pour passer le quiz.");
            return $this->redirectToRoute('app_utilisateur_participer', ['id' => $formation->getIdFormation()]);
        }

        $quiz = $quizRepository->findOneBy(['formation' => $formation]);
        if (!$quiz || $quiz->getQuestions()->count() === 0) {
            $this->addFlash('warning', 'Quiz non disponible pour cette formation.');
            return $this->redirectToRoute('app_utilisateur_formation_show', ['id' => $formation->getIdFormation()]);
        }

        $questions = $quiz->getQuestions()->toArray();
        usort($questions, fn($a, $b) => ($a->getIdQuestion() ?? 0) <=> ($b->getIdQuestion() ?? 0));

        if ($request->isMethod('POST')) {
            $answers = $request->request->all('answers');
            $cheatEventsRaw = (string) $request->request->get('cheat_events', '[]');
            $cheatEvents = json_decode($cheatEventsRaw, true);
            if (!is_array($cheatEvents)) {
                $cheatEvents = [];
            }

            $score = 0.0;
            foreach ($questions as $question) {
                $questionId = (string) $question->getIdQuestion();
                $selected = strtolower(trim((string) ($answers[$questionId] ?? '')));
                $correct = strtolower(trim((string) ($question->getReponseCorrecte() ?? '')));
                if ($selected !== '' && $selected === $correct) {
                    $score += (float) ($question->getPoints() ?? 1.0);
                }
            }
            $scoreMax = 0.0;
            foreach ($questions as $question) {
                $scoreMax += (float) ($question->getPoints() ?? 1.0);
            }
            $isQuizValide = $scoreMax > 0 ? $score >= ($scoreMax * 0.6) : $score > 0;

            $participation->setScore($score);

            $progression = $progressionRepository->findOneBy([
                'participation' => $participation,
            ]);

            if (!$progression) {
                $progression = (new ProgressionFormation())
                    ->setFormation($formation)
                    ->setParticipation($participation)
                    ->setIdUtilisateur($idUtilisateur)
                    ->setVideoSeconds(0)
                    ->setVideoDuration(0)
                    ->setPourcentage(0.0)
                    ->setStatut('en_cours');
                $em->persist($progression);
            }

            $progression
                ->setQuizScore($score)
                ->setUpdatedAt(new \DateTimeImmutable());

            if ($progression->getPourcentage() >= 95.0) {
                $progression
                    ->setPourcentage(100.0)
                    ->setStatut('termine');
            } elseif ($progression->getPourcentage() > 0.0 || $progression->getQuizScore() !== null) {
                $progression->setStatut('en_cours');
            } else {
                $progression->setStatut('non_commence');
            }

            $em->flush();

            if ($isQuizValide) {
                try {
                    /** @var \App\Entity\Utilisateur $utilisateur */
                    $utilisateur = $this->getUser();
                    $certificateMailerService->sendQuizCertificate(
                        $utilisateur,
                        $formation,
                        $score,
                        $scoreMax
                    );
                } catch (\Throwable $e) {
                    $this->addFlash('warning', "Le quiz est valide, mais l'envoi du certificat par email a echoue.");
                }
            }

            if (count($cheatEvents) > 0) {
                $this->addFlash('warning', 'Quiz termine, mais une activite suspecte a ete detectee pendant le passage.');
            } elseif ($isQuizValide) {
                $this->addFlash('success', 'Quiz valide. Le certificat PDF a ete envoye a votre email.');
            } else {
                $this->addFlash('warning', 'Quiz termine, mais score insuffisant pour valider le quiz.');
            }

            return $this->redirectToRoute('app_utilisateur_mes_participations');
        }

        return $this->render('formation/utilisateur_formation/quiz_pass.html.twig', [
            'formation' => $formation,
            'quiz' => $quiz,
            'questions' => $questions,
            'participation' => $participation,
        ]);
    }

    #[Route('/quiz/{id}/face-check', name: 'app_utilisateur_quiz_face_check', methods: ['POST'])]
    public function quizFaceCheck(
        int $id,
        Request $request,
        FormationRepository $formationRepository,
        ParticipationRepository $participationRepository,
        FacePlusPlusService $facePlusPlusService
    ): JsonResponse {
        $formation = $formationRepository->find($id);
        if (!$formation) {
            return $this->json(['ok' => false, 'error' => 'Formation introuvable'], 404);
        }

        $utilisateur = $this->getUser();
        $idUtilisateur = $utilisateur->getId();

        $participation = $participationRepository->findOneBy([
            'formation' => $formation,
            'idUtilisateur' => $idUtilisateur,
        ]);
        if (!$participation) {
            return $this->json(['ok' => false, 'error' => 'Inscription requise'], 403);
        }

        $payload = json_decode((string) $request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json(['ok' => false, 'error' => 'Payload invalide'], 400);
        }

        $reference = (string) ($payload['reference'] ?? '');
        $current = (string) ($payload['current'] ?? '');
        $mode = strtolower(trim((string) ($payload['mode'] ?? 'full')));
        if (!in_array($mode, ['full', 'detect'], true)) {
            $mode = 'full';
        }

        if ($current === '') {
            return $this->json(['ok' => false, 'error' => 'Images manquantes'], 400);
        }

        if ($mode === 'detect') {
            $result = $facePlusPlusService->analyzeCurrentFrame($current);
        } else {
            if ($reference === '') {
                return $this->json(['ok' => false, 'error' => 'Image de reference manquante'], 400);
            }
            $result = $facePlusPlusService->analyzeFrames($reference, $current);
        }

        if (!($result['ok'] ?? false)) {
            return $this->json($result, 502);
        }

        return $this->json($result);
    }

    #[Route('/mes-participations', name: 'app_utilisateur_mes_participations')]
    public function mesParticipations(ParticipationRepository $repo): Response
    {
        $utilisateur = $this->getUser();
        $idUtilisateur = $utilisateur->getId();

        $participations = $repo->findBy(['idUtilisateur' => $idUtilisateur]);

        return $this->render('formation/utilisateur_formation/mes_participations.html.twig', [
            'participations' => $participations,
        ]);
    }

    #[Route('/annuler/{id}', name: 'app_utilisateur_annuler')]
    public function annuler(int $id, ParticipationRepository $repo, EntityManagerInterface $em): Response
    {
        $utilisateur = $this->getUser();
        $idUtilisateur = $utilisateur->getId();

        $participation = $repo->find($id);

        if (!$participation || $participation->getIdUtilisateur() !== $idUtilisateur) {
            throw $this->createNotFoundException('Participation introuvable');
        }

        $em->remove($participation);
        $em->flush();

        $this->addFlash('success', 'Participation annulée avec succès !');
        return $this->redirectToRoute('app_utilisateur_mes_participations');
    }

    #[Route('/modifier-participation/{id}', name: 'app_utilisateur_modifier_participation')]
    public function modifierParticipation(
        int $id,
        Request $request,
        ParticipationRepository $repo,
        EntityManagerInterface $em
    ): Response {
        $utilisateur = $this->getUser();
        $idUtilisateur = $utilisateur->getId();

        $participation = $repo->find($id);

        if (!$participation || $participation->getIdUtilisateur() !== $idUtilisateur) {
            throw $this->createNotFoundException('Participation introuvable');
        }

        $errors = [];
        $old = [];

        if ($request->isMethod('POST')) {

            $posteActuel = trim($request->request->get('posteActuel'));
            $attentes = trim($request->request->get('attentes'));

            $old = $request->request->all();

            if (empty($posteActuel)) {
                $errors['posteActuel'] = "Le poste est obligatoire";
            } elseif (strlen($posteActuel) < 3) {
                $errors['posteActuel'] = "Minimum 3 caractères";
            } elseif (!preg_match("/^[a-zA-ZÀ-ÿ\s]+$/", $posteActuel)) {
                $errors['posteActuel'] = "Seulement des lettres autorisées";
            }

            if (empty($attentes)) {
                $errors['attentes'] = "Les attentes sont obligatoires";
            } elseif (strlen($attentes) < 10) {
                $errors['attentes'] = "Minimum 10 caractères";
            }

            if (empty($errors)) {
                $participation->setPosteActuel($posteActuel);
                $participation->setAttentes($attentes);

                $em->flush();

                $this->addFlash('success', 'Participation modifiée avec succès !');
                return $this->redirectToRoute('app_utilisateur_mes_participations');
            }

            return $this->render('formation/utilisateur_formation/modifier_participation.html.twig', [
                'participation' => $participation,
                'errors' => $errors,
                'old' => $old
            ]);
        }

        return $this->render('formation/utilisateur_formation/modifier_participation.html.twig', [
            'participation' => $participation
        ]);
    }
}
