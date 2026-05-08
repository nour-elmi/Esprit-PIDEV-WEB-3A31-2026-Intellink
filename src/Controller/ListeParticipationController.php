<?php

namespace App\Controller;

use App\Entity\ListeParticipation;
use App\Entity\Emploi;
use App\Entity\Utilisateur;                     // ← ajout
use App\Form\ListeParticipationType;
use App\Repository\EmploiRepository;
use App\Repository\ListeParticipationRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Nucleos\DompdfBundle\Factory\DompdfFactoryInterface;

final class ListeParticipationController extends AbstractController
{
    #[Route('/showListe/{id_offre}', name: 'showListe', defaults: ['id_offre' => null])]
    public function showListe(?Emploi $offre, ListeParticipationRepository $repo, PaginatorInterface $paginator, Request $request): Response
    {
        $user = $this->getUser();
        // Si c'est un candidat, on ne montre que ses propres participations
        if ($user instanceof Utilisateur) {
            $data = $offre 
                ? $repo->findBy(['id_offre' => $offre, 'id_user' => $user->getId()])
                : $repo->findBy(['id_user' => $user->getId()]);
        } else {
            // Pas connecté : on ne montre rien
            $data = [];
        }

        $participations = $paginator->paginate(
            $data,
            $request->query->getInt('page', 1),
            5
        );

        return $this->render('emploi/front/showListe.html.twig', [
            'lesParticipations' => $participations,
            'offreChoisie' => $offre
        ]);
    }

    #[Route('/showListeBack/{id}', name: 'showListeBack', defaults: ['id' => null])]
    public function showListeBack(?Emploi $offre, ListeParticipationRepository $repo, PaginatorInterface $paginator, Request $request): Response
    {
        $data = $offre 
            ? $repo->findBy(['id_offre' => $offre]) 
            : $repo->findAll();

        $participations = $paginator->paginate(
            $data,
            $request->query->getInt('page', 1),
            8 // On peut en mettre plus dans le back-office
        );

        return $this->render('emploi/back/ListeBack.html.twig', [
            'lesParticipations' => $participations,
            'offreChoisie' => $offre
        ]);
    }
    
    private function appelerCoherePourCV(string $nom, string $prenom, string $skills, string $offre, HttpClientInterface $httpClient): string 
    {
        $apiKey = "2DcO8uLgBZTWfOazk6sCoblUVlJns29uvxEIaGGl";
        $url = "https://api.cohere.ai/v1/chat";

        $prompt = "Rédige uniquement un cv professionnel de 7 lignes minimum pour $prenom $nom. Poste : $offre. Compétences : $skills";

        try {
            $response = $httpClient->request('POST', $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'command-r-08-2024',
                    'message' => $prompt,
                ],
            ]);

            $content = $response->toArray();

            // En Symfony, toArray() décode déjà le JSON, 
            // donc les caractères comme \n ou \u00e9 sont déjà convertis.
            if (isset($content['text'])) {
                return $content['text'];
            }

            return "Erreur : Le modèle a répondu mais le texte est absent.";

        } catch (\Exception $e) {
            return "Erreur lors de l'appel à Cohere : " . $e->getMessage();
        }
    }

    #[Route('/cv/generate-ai', name: 'app_cv_generate_ai', methods: ['POST'])]
    public function generateAI(Request $request, HttpClientInterface $httpClient, EmploiRepository $repo): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        // On peut même récupérer le titre de l'offre dynamiquement si tu envoies l'ID
        $offreTitre = "Poste non spécifié"; 
        
        $resultat = $this->appelerCoherePourCV(
            $data['nom'] ?? '',
            $data['prenom'] ?? '',
            $data['skills'] ?? '',
            $offreTitre,
            $httpClient
        );

        return new JsonResponse(['text' => $resultat]);
    }

    #[Route('/addListe/{id_offre}', name: 'addListe')]
    public function addListe(int $id_offre, ManagerRegistry $doctrine, Request $request, EmploiRepository $emploiRepo): Response 
    {
        $user = $this->getUser();
        if (!$user instanceof Utilisateur) {
            throw $this->createAccessDeniedException('Vous devez être connecté pour postuler.');
        }

        $em = $doctrine->getManager();
        $offre = $emploiRepo->find($id_offre);
        if (!$offre) {
            throw $this->createNotFoundException("L'offre n'existe pas.");
        }

        $participation = new ListeParticipation();
        $participation->setIdOffre($offre);
        $participation->setDateParticipation(new \DateTime());
        $participation->setIdUser($user->getId());   // ← plus de hardcode !

        $form = $this->createForm(ListeParticipationType::class, $participation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // CHANGEMENT: getScore() est desormais non-null (int), ce test est toujours faux.
            // Ancienne logique conservee en commentaire:
            // if ($participation->getScore() === null) {
            //     $participation->setScore(0);
            // }
            /** @var UploadedFile $cvFile */
            $cvFile = $form->get('cv')->getData();
            if ($cvFile) {
                $newFilename = 'cv-' . uniqid() . '.' . $cvFile->guessExtension();
                try {
                    $cvDirectory = $this->getParameter('cv_directory'); // CHANGEMENT
                    if (!is_string($cvDirectory) || $cvDirectory === '') { // CHANGEMENT
                        throw new \RuntimeException('Parametre cv_directory invalide.');
                    }
                    $cvFile->move($cvDirectory, $newFilename);
                    $participation->setCv($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Impossible d\'enregistrer le CV.');
                }
            }
            $em->persist($participation);
            $em->flush();
            $this->addFlash('success', 'Candidature envoyée.');
            return $this->redirectToRoute('showListe');
        }

        return $this->render('emploi/front/addListe.html.twig', [
            'formOffre' => $form->createView(),
            'offre' => $offre
        ]);
    }

    #[Route('/deleteListe/{id}', name:'deleteListe')]
    public function deleteListe(int $id, ManagerRegistry $Manager, ListeParticipationRepository $repo, Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof Utilisateur) {
            throw $this->createAccessDeniedException();
        }

        $em = $Manager->getManager();
        $participation = $repo->find($id);
        if (!$participation) {
            throw $this->createNotFoundException('Participation introuvable.');
        }

        // Autorisation : seul le candidat ou le recruteur propriétaire de l'offre peut supprimer
        $offre = $participation->getIdOffre();
        if (!$offre) { // CHANGEMENT: securise appel sur offre nullable
            throw $this->createNotFoundException('Offre introuvable.');
        }
        if ($participation->getIdUser() !== $user->getId() && $offre->getIdUser() !== $user->getId()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas supprimer cette candidature.');
        }

        // Vérification CSRF (optionnelle)
        // if (!$this->isCsrfTokenValid('delete_liste_'.$id, $request->request->get('_token'))) { ... }

        $em->remove($participation);
        $em->flush();
        $this->addFlash('success', 'Candidature supprimée.');
        return $this->redirectToRoute('showListeBack');
    }

    #[Route('/updateStatut/{id}/{nouveauStatut}', name: 'updateStatut')]
    public function updateStatut(int $id, string $nouveauStatut, ListeParticipationRepository $repo, EntityManagerInterface $em): Response
    {
        $recruteur = $this->getUser();
        if (!$recruteur instanceof Utilisateur) {
            throw $this->createAccessDeniedException();
        }

        $participation = $repo->find($id);
        if (!$participation) {
            throw $this->createNotFoundException();
        }

        $offre = $participation->getIdOffre();
        // Seul le propriétaire de l'offre peut changer le statut
        if (!$offre) { // CHANGEMENT: securise appel sur offre nullable
            throw $this->createNotFoundException('Offre introuvable.');
        }
        if ($offre->getIdUser() !== $recruteur->getId()) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à modifier ce statut.');
        }

        try {
            $enumValue = \App\Enum\stat::from($nouveauStatut);
            $participation->setStatut($enumValue);
            $em->flush();
            $this->addFlash('success', 'Statut mis à jour !');
        } catch (\ValueError $e) {
            $this->addFlash('error', 'Valeur de statut invalide : ' . $nouveauStatut);
        }

        $response = $this->redirectToRoute('showListeBack', ['id' => $offre->getId()]);
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
        return $response;
    }

    #[Route('/cv/download-pdf', name: 'download_pdf', methods: ['POST'])]
    public function downloadPdf(Request $request, DompdfFactoryInterface $factory): Response
    {
        // On récupère le texte que l'IA a généré (envoyé par le formulaire de la modale)
        $contenu = $request->request->get('cv_text');

        if (!$contenu) {
            return new Response("Erreur : Aucun contenu trouvé pour le CV.", 400);
        }

        // On utilise la factory du bundle Nucleos pour créer l'objet Dompdf
        $dompdf = $factory->create();
        
        // On génère le HTML à partir d'un template Twig pour que ce soit joli
        $html = $this->renderView('emploi/front/pdf.html.twig', [
            'cv_content' => $contenu
        ]);
        
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // On renvoie le binaire du PDF pour déclencher le téléchargement
        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="Mon_CV_IA.pdf"'
        ]);
    }
}
