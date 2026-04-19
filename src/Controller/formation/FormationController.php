<?php

namespace App\Controller\formation;

use App\Entity\formation\Formation;
use App\Entity\formation\Question;
use App\Entity\formation\Quiz;
use App\Repository\FormationRepository;
use App\Repository\QuizRepository;
use App\Service\formation\LocalQuizGeneratorService;
use App\Service\formation\YouTubeTranscriptService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/formation')]
final class FormationController extends AbstractController
{
    #[Route('/', name: 'app_formation_index')]
    public function index(
        Request $request,
        FormationRepository $repo,
        PaginatorInterface $paginator
    ): Response
    {
        $utilisateur = $this->getUser();
        $formateurId = $utilisateur->getId();

        $formationsQuery = $repo->createQueryBuilder('f')
            ->andWhere('f.idFormateur = :idFormateur')
            ->setParameter('idFormateur', $formateurId)
            ->orderBy('f.idFormation', 'DESC');

        $formations = $paginator->paginate(
            $formationsQuery,
            max(1, (int) $request->query->get('page', 1)),
            6
        );

        $totalFormations = (int) $repo->count(['idFormateur' => $formateurId]);
        $totalInscriptions = (int) $repo->createQueryBuilder('f')
            ->select('COUNT(p.idParticipation)')
            ->leftJoin('f.participations', 'p')
            ->andWhere('f.idFormateur = :idFormateur')
            ->setParameter('idFormateur', $formateurId)
            ->getQuery()
            ->getSingleScalarResult();

        return $this->render('formation/formateur/index.html.twig', [
            'formations' => $formations,
            'totalFormations' => $totalFormations,
            'totalInscriptions' => $totalInscriptions,
        ]);
    }

    #[Route('/add', name: 'app_formation_add')]
    public function add(Request $request, EntityManagerInterface $em): Response
    {
        $errors = [];
        $old = [];

        if ($request->isMethod('POST')) {
            $titre = trim((string) $request->request->get('titre', ''));
            $description = trim((string) $request->request->get('description', ''));
            $domaine = trim((string) $request->request->get('domaine', ''));
            $niveau = (string) $request->request->get('niveau', '');
            $urlVideo = trim((string) $request->request->get('urlVideo', ''));

            $old = $request->request->all();

            if ($titre === '') {
                $errors['titre'] = 'Le titre est obligatoire';
            } elseif (mb_strlen($titre) < 3) {
                $errors['titre'] = 'Le titre doit contenir au moins 3 caracteres';
            }

            if ($domaine === '') {
                $errors['domaine'] = 'Le domaine est obligatoire';
            } elseif (mb_strlen($domaine) < 3) {
                $errors['domaine'] = 'Minimum 3 caracteres';
            } elseif (!preg_match('/^[\p{L}\s]+$/u', $domaine)) {
                $errors['domaine'] = 'Seulement des lettres autorisees';
            }

            if ($description === '') {
                $errors['description'] = 'La description est obligatoire';
            }

            if ($urlVideo === '') {
                $errors['urlVideo'] = "L'URL est obligatoire";
            } elseif (!filter_var($urlVideo, FILTER_VALIDATE_URL)) {
                $errors['urlVideo'] = 'URL invalide';
            }

            if (empty($errors)) {
                $formation = new Formation();
                $formation->setTitre($titre);
                $formation->setDescription($description);
                $formation->setDomaine($domaine);
                $formation->setNiveau($niveau);
                $formation->setUrlVideo($urlVideo);

                $utilisateur = $this->getUser();
                $formation->setIdFormateur($utilisateur->getId());

                $em->persist($formation);
                $em->flush();

                $this->addFlash('success', 'Formation ajoutee avec succes !');

                return $this->redirectToRoute('app_formation_index');
            }
        }

        return $this->render('formation/formateur/add.html.twig', [
            'errors' => $errors,
            'old' => $old,
        ]);
    }

    #[Route('/edit/{id}', name: 'app_formation_edit')]
    public function edit(int $id, Request $request, FormationRepository $repo, EntityManagerInterface $em): Response
    {
        $formation = $repo->find($id);

        if (!$formation) {
            throw $this->createNotFoundException('Formation introuvable');
        }

        $errors = [];
        $old = [];

        if ($request->isMethod('POST')) {
            $titre = trim((string) $request->request->get('titre', ''));
            $description = trim((string) $request->request->get('description', ''));
            $domaine = trim((string) $request->request->get('domaine', ''));
            $niveau = (string) $request->request->get('niveau', '');
            $urlVideo = trim((string) $request->request->get('urlVideo', ''));

            $old = $request->request->all();

            if ($titre === '') {
                $errors['titre'] = 'Le titre est obligatoire';
            } elseif (mb_strlen($titre) < 3) {
                $errors['titre'] = 'Minimum 3 caracteres';
            }

            if ($domaine === '') {
                $errors['domaine'] = 'Le domaine est obligatoire';
            } elseif (mb_strlen($domaine) < 3) {
                $errors['domaine'] = 'Minimum 3 caracteres';
            } elseif (!preg_match('/^[\p{L}\s]+$/u', $domaine)) {
                $errors['domaine'] = 'Seulement des lettres autorisees';
            }

            if ($description === '') {
                $errors['description'] = 'La description est obligatoire';
            }

            if ($urlVideo === '') {
                $errors['urlVideo'] = 'URL obligatoire';
            } elseif (!filter_var($urlVideo, FILTER_VALIDATE_URL)) {
                $errors['urlVideo'] = 'URL invalide';
            }

            if (empty($errors)) {
                $formation->setTitre($titre);
                $formation->setDescription($description);
                $formation->setDomaine($domaine);
                $formation->setNiveau($niveau);
                $formation->setUrlVideo($urlVideo);

                $em->flush();

                $this->addFlash('success', 'Formation modifiee avec succes !');

                return $this->redirectToRoute('app_formation_index');
            }
        }

        return $this->render('formation/formateur/edit.html.twig', [
            'formation' => $formation,
            'errors' => $errors,
            'old' => $old,
        ]);
    }

    #[Route('/delete/{id}', name: 'app_formation_delete')]
    public function delete(int $id, FormationRepository $repo, EntityManagerInterface $em): Response
    {
        $formation = $repo->find($id);

        if (!$formation) {
            throw $this->createNotFoundException('Formation introuvable');
        }

        $em->remove($formation);
        $em->flush();

        $this->addFlash('success', 'Formation supprimee avec succes !');

        return $this->redirectToRoute('app_formation_index');
    }

    #[Route('/show/{id}', name: 'app_formation_show')]
    public function show(int $id, FormationRepository $repo, QuizRepository $quizRepository): Response
    {
        $formation = $repo->find($id);

        if (!$formation) {
            throw $this->createNotFoundException('Formation introuvable');
        }

        $quiz = $quizRepository->findOneBy(['formation' => $formation]);

        return $this->render('formation/formateur/show.html.twig', [
            'formation' => $formation,
            'quiz' => $quiz,
        ]);
    }

    #[Route('/{id}/quiz', name: 'app_formation_quiz_manage')]
    public function manageQuiz(
        int $id,
        Request $request,
        FormationRepository $formationRepository,
        QuizRepository $quizRepository,
        LocalQuizGeneratorService $localQuizGenerator,
        YouTubeTranscriptService $youtubeTranscriptService,
        EntityManagerInterface $em
    ): Response {
        $formation = $formationRepository->find($id);
        if (!$formation) {
            throw $this->createNotFoundException('Formation introuvable');
        }

        $quiz = $quizRepository->findOneBy(['formation' => $formation]);
        $isCreation = $quiz === null;

        if ($isCreation) {
            $quiz = new Quiz();
            $quiz->setFormation($formation);
            $quiz->setTitre('Quiz - ' . $formation->getTitre());
        }

        $questionsData = [];
        foreach ($quiz->getQuestions() as $question) {
            $questionsData[] = [
                'enonce' => $question->getEnonce() ?? '',
                'reponse' => in_array($question->getReponseCorrecte(), ['vrai', 'faux'], true) ? $question->getReponseCorrecte() : 'vrai',
            ];
        }

        if ($questionsData === []) {
            $questionsData[] = ['enonce' => '', 'reponse' => 'vrai'];
        }

        $errors = [];
        $old = [
            'duree' => $quiz->getDuree() ?? 10,
            'nombreQuestions' => max(1, count($questionsData)),
            'quizSource' => 'description',
        ];
        $isYoutubeVideo = $youtubeTranscriptService->isYouTubeUrl((string) $formation->getUrlVideo());

        if ($request->isMethod('POST')) {
            $duree = (int) $request->request->get('duree', 0);
            $nombreQuestions = (int) $request->request->get('nombreQuestions', 0);
            $enonces = $request->request->all('questions_enonce');
            $reponses = $request->request->all('questions_reponse');
            $quizSource = (string) $request->request->get('quiz_source', 'description');
            if (!in_array($quizSource, ['description', 'youtube'], true)) {
                $quizSource = 'description';
            }
            if ($quizSource === 'youtube' && !$isYoutubeVideo) {
                $quizSource = 'description';
            }
            $isAutoGenerate = (string) $request->request->get('auto_generate', '0') === '1';

            $old['duree'] = $duree;
            $old['nombreQuestions'] = $nombreQuestions;
            $old['quizSource'] = $quizSource;

            if ($isAutoGenerate) {
                if ($nombreQuestions <= 0) {
                    $errors['nombreQuestions'] = 'Le nombre de questions doit etre superieur a 0.';
                }

                $sourceText = '';
                if ($quizSource === 'youtube') {
                    $result = $youtubeTranscriptService->fetchTranscriptFromUrl((string) $formation->getUrlVideo());
                    if (!($result['ok'] ?? false)) {
                        $errors['generation'] = (string) ($result['error'] ?? 'Transcription YouTube indisponible.');
                    } else {
                        $sourceText = (string) ($result['transcript'] ?? '');
                    }
                } else {
                    $sourceText = (string) $formation->getDescription();
                }

                if (trim($sourceText) === '' && !isset($errors['generation'])) {
                    $errors['generation'] = 'Aucun contenu textuel exploitable pour la generation.';
                }

                if (empty($errors)) {
                    $generatedResult = $localQuizGenerator->generate($sourceText, $nombreQuestions);
                    if (!($generatedResult['ok'] ?? false)) {
                        $errors['generation'] = (string) ($generatedResult['error'] ?? 'Generation impossible avec le contenu actuel.');
                    } else {
                        $questionsData = (array) ($generatedResult['questions'] ?? []);
                        if ($questionsData === []) {
                            $errors['generation'] = 'Generation impossible avec le contenu actuel.';
                        }
                        $old['nombreQuestions'] = count($questionsData);
                        if ($questionsData !== []) {
                            $this->addFlash('success', 'Questions generees automatiquement. Vous pouvez les modifier avant enregistrement.');
                        }
                    }
                }

                return $this->render('formation/formateur/quiz_manage.html.twig', [
                    'formation' => $formation,
                    'quiz' => $quiz,
                    'isCreation' => $isCreation,
                    'errors' => $errors,
                    'old' => $old,
                    'questionsData' => $questionsData,
                    'isYoutubeVideo' => $isYoutubeVideo,
                ]);
            }

            if ($duree <= 0) {
                $errors['duree'] = 'La duree doit etre superieure a 0 minute.';
            }

            if ($nombreQuestions <= 0) {
                $errors['nombreQuestions'] = 'Le nombre de questions doit etre superieur a 0.';
            }

            if (count($enonces) !== $nombreQuestions || count($reponses) !== $nombreQuestions) {
                $errors['questions'] = 'Le formulaire des questions est incomplet.';
            }

            $questionsData = [];
            for ($i = 0; $i < $nombreQuestions; $i++) {
                $enonce = trim((string) ($enonces[$i] ?? ''));
                $reponse = (string) ($reponses[$i] ?? 'vrai');
                if (!in_array($reponse, ['vrai', 'faux'], true)) {
                    $reponse = 'vrai';
                }

                $questionsData[] = [
                    'enonce' => $enonce,
                    'reponse' => $reponse,
                ];

                if ($enonce === '') {
                    $errors['question_' . $i] = 'La question ' . ($i + 1) . ' est obligatoire.';
                }
            }

            if (empty($errors)) {
                if ($isCreation) {
                    $em->persist($quiz);
                }

                foreach ($quiz->getQuestions() as $existingQuestion) {
                    $em->remove($existingQuestion);
                }

                $quiz->setDuree($duree);
                $quiz->setScoreMax((float) $nombreQuestions);

                foreach ($questionsData as $questionData) {
                    $question = new Question();
                    $question->setQuiz($quiz);
                    $question->setEnonce($questionData['enonce']);
                    $question->setReponseCorrecte($questionData['reponse']);
                    $question->setPoints(1.0);
                    $em->persist($question);
                }

                $em->flush();

                $this->addFlash('success', $isCreation ? 'Quiz ajoute avec succes !' : 'Quiz modifie avec succes !');

                return $this->redirectToRoute('app_formation_show', ['id' => $formation->getIdFormation()]);
            }
        }

        return $this->render('formation/formateur/quiz_manage.html.twig', [
            'formation' => $formation,
            'quiz' => $quiz,
            'isCreation' => $isCreation,
            'errors' => $errors,
            'old' => $old,
            'questionsData' => $questionsData,
            'isYoutubeVideo' => $isYoutubeVideo,
        ]);
    }
}
