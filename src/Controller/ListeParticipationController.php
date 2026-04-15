<?php

namespace App\Controller;

use App\Entity\ListeParticipation;
use App\Entity\Emploi;
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

final class ListeParticipationController extends AbstractController
{
    #[Route('/showListe/{id_offre}', name: 'showListe', defaults: ['id_offre' => null])]
    public function showListe(?Emploi $offre, ListeParticipationRepository $repo, PaginatorInterface $paginator, Request $request): Response
    {
        // On crée la requête de base
        $data = $offre 
            ? $repo->findBy(['id_offre' => $offre]) 
            : $repo->findAll();

        $participations = $paginator->paginate(
            $data, // Les données
            $request->query->getInt('page', 1), // Numéro de page
            5 // Nombre d'éléments par page
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

    // On passe l'id_offre dans l'URL pour savoir pour quel job on postule
    #[Route('/addListe/{id_offre}', name: 'addListe')]
    public function addListe(int $id_offre, ManagerRegistry $doctrine, Request $request, EmploiRepository $emploiRepo): Response {
        $em = $doctrine->getManager();
        $offre = $emploiRepo->find($id_offre);

        if (!$offre) {
            throw $this->createNotFoundException("L'offre n'existe pas.");
        }

        $participation = new ListeParticipation();
        $participation->setIdOffre($offre);
        $participation->setDateParticipation(new \DateTime());
        $participation->setIdUser(1); // À dynamiser avec $this->getUser() plus tard

        $form = $this->createForm(ListeParticipationType::class, $participation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            
            // 1. On récupère le fichier uploadé via le champ 'cv'
            /** @var UploadedFile $cvFile */
            $cvFile = $form->get('cv')->getData();

            if ($cvFile) {
                // 2. On génère un nom unique : "cv-idunique.pdf"
                $newFilename = 'cv-' . uniqid() . '.' . $cvFile->guessExtension();

                // 3. On déplace le fichier vers le dossier de destination
                try {
                    $cvFile->move(
                        $this->getParameter('cv_directory'), // Ce paramètre doit être défini dans services.yaml
                        $newFilename
                    );
                    
                    // 4. On enregistre le NOM du fichier en base de données
                    $participation->setCv($newFilename);
                    
                } catch (FileException $e) {
                    // Optionnel : ajouter un message flash d'erreur si l'upload échoue
                    $this->addFlash('error', 'Impossible d\'enregistrer le CV.');
                }
            }

            $participation->setDateParticipation(new \DateTime());
            $em->persist($participation);
            $em->flush();

            return $this->redirectToRoute('showListe');
        }

        return $this->render('emploi/front/addListe.html.twig', [
            'formOffre' => $form->createView(),
            'offre' => $offre
        ]);
    }

    #[Route('/deleteListe/{id}', name:'deleteListe')]
    public function deleteListe($id, ManagerRegistry $Manager, ListeParticipationRepository $repo)
    {
        $em= $Manager->getManager();
        $Liste= $repo->find($id);
        $em->remove($Liste);
        $em->flush();
        return $this->redirectToRoute('showListeBack');
    }

    #[Route('/updateStatut/{id}/{nouveauStatut}', name: 'updateStatut')]
    public function updateStatut(int $id, string $nouveauStatut, ListeParticipationRepository $repo, EntityManagerInterface $em): Response
    {
        $p = $repo->find($id);
        
        try {
            // Cela va crash si $nouveauStatut n'est pas 'aceptee', 'refusee' ou 'en_attente'
            $enumValue = \App\Enum\stat::from($nouveauStatut);
            $p->setStatut($enumValue);
            
            $em->flush();
            $this->addFlash('success', 'Statut mis à jour !');
        } catch (\ValueError $e) {
            $this->addFlash('error', 'Valeur de statut invalide : ' . $nouveauStatut);
        }

        $response = $this->redirectToRoute('showListeBack', ['id' => $p->getIdOffre()->getId()]);
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
        return $response;
    }
}