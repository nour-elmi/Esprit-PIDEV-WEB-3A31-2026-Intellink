<?php

namespace App\Controller\formation;

use App\Entity\formation\Participation;
use App\Repository\FormationRepository;
use App\Repository\ParticipationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
#[Route('/formation')]
final class ParticipationFormationController extends AbstractController
{
    // Utilisateur statique
    private int $idUtilisateur = 1;

    // Voir toutes les formations disponibles
   #[Route('/formations', name: 'app_utilisateur_formations')]
public function formations(FormationRepository $repo, ParticipationRepository $participationRepo): Response
{
    $formations = $repo->findAll();
    
    // Récupérer les IDs des formations où l'utilisateur est inscrit
    $participations = $participationRepo->findBy(['idUtilisateur' => $this->idUtilisateur]);
    $inscritIds = array_map(
        fn($p) => $p->getFormation()->getIdFormation(),
        $participations
    );

    return $this->render('formation/utilisateur_formation/formations.html.twig', [
        'formations' => $formations,
        'inscritIds' => $inscritIds,
    ]);
}

    // Voir le détail d'une formation
    #[Route('/formation/{id}', name: 'app_utilisateur_formation_show')]
    public function show(int $id, FormationRepository $repo, ParticipationRepository $participationRepo): Response
    {
        $formation = $repo->find($id);

        if (!$formation) {
            throw $this->createNotFoundException('Formation introuvable');
        }

        // Vérifier si l'utilisateur est déjà inscrit
        $dejaInscrit = $participationRepo->findOneBy([
            'formation' => $formation,
            'idUtilisateur' => $this->idUtilisateur,
        ]);

        return $this->render('formation/utilisateur_formation/formation_show.html.twig', [
            'formation' => $formation,
            'dejaInscrit' => $dejaInscrit,
        ]);
    }

    // S'inscrire à une formation
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

    // Vérifier si déjà inscrit
    $dejaInscrit = $participationRepo->findOneBy([
        'formation' => $formation,
        'idUtilisateur' => $this->idUtilisateur,
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

        // 🔴 VALIDATION

        // Poste actuel
        if (empty($posteActuel)) {
            $errors['posteActuel'] = "Le poste est obligatoire";
        } elseif (strlen($posteActuel) < 3) {
            $errors['posteActuel'] = "Minimum 3 caractères";
        } elseif (!preg_match("/^[a-zA-ZÀ-ÿ\s]+$/", $posteActuel)) {
            $errors['posteActuel'] = "Seulement des lettres autorisées";
        }

        // Attentes
        if (empty($attentes)) {
            $errors['attentes'] = "Les attentes sont obligatoires";
        } elseif (strlen($attentes) < 10) {
            $errors['attentes'] = "Minimum 10 caractères";
        }

        // ✅ SI PAS D'ERREURS
        if (empty($errors)) {
            $participation = new Participation();
            $participation->setFormation($formation);
            $participation->setIdUtilisateur($this->idUtilisateur);
            $participation->setPosteActuel($posteActuel);
            $participation->setAttentes($attentes);
            $participation->setDateInscription(new \DateTime());
            $participation->setScore(0);

            $em->persist($participation);
            $em->flush();

            $this->addFlash('success', 'Inscription réussie !');
            return $this->redirectToRoute('app_utilisateur_mes_participations');
        }

        // 🔁 retourner avec erreurs
        return $this->render('formation/utilisateur_formation/participer.html.twig', [
            'formation' => $formation,
            'errors' => $errors,
            'old' => $old
        ]);
    }

    // GET
    return $this->render('formation/utilisateur_formation/participer.html.twig', [
        'formation' => $formation
    ]);
}
    // Voir ses participations
    #[Route('/mes-participations', name: 'app_utilisateur_mes_participations')]
    public function mesParticipations(ParticipationRepository $repo): Response
    {
        $participations = $repo->findBy(['idUtilisateur' => $this->idUtilisateur]);

        return $this->render('formation/utilisateur_formation/mes_participations.html.twig', [
            'participations' => $participations,
        ]);
    }

    // Annuler une participation
    #[Route('/annuler/{id}', name: 'app_utilisateur_annuler')]
    public function annuler(int $id, ParticipationRepository $repo, EntityManagerInterface $em): Response
    {
        $participation = $repo->find($id);

        if (!$participation || $participation->getIdUtilisateur() !== $this->idUtilisateur) {
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
    $participation = $repo->find($id);

    if (!$participation || $participation->getIdUtilisateur() !== $this->idUtilisateur) {
        throw $this->createNotFoundException('Participation introuvable');
    }

    $errors = [];
    $old = [];

    if ($request->isMethod('POST')) {

        $posteActuel = trim($request->request->get('posteActuel'));
        $attentes = trim($request->request->get('attentes'));

        $old = $request->request->all();

        // 🔴 VALIDATION

        // Poste actuel
        if (empty($posteActuel)) {
            $errors['posteActuel'] = "Le poste est obligatoire";
        } elseif (strlen($posteActuel) < 3) {
            $errors['posteActuel'] = "Minimum 3 caractères";
        } elseif (!preg_match("/^[a-zA-ZÀ-ÿ\s]+$/", $posteActuel)) {
            $errors['posteActuel'] = "Seulement des lettres autorisées";
        }

        // Attentes
        if (empty($attentes)) {
            $errors['attentes'] = "Les attentes sont obligatoires";
        } elseif (strlen($attentes) < 10) {
            $errors['attentes'] = "Minimum 10 caractères";
        }

        // ✅ SI PAS D'ERREURS
        if (empty($errors)) {
            $participation->setPosteActuel($posteActuel);
            $participation->setAttentes($attentes);

            $em->flush();

            $this->addFlash('success', 'Participation modifiée avec succès !');

            return $this->redirectToRoute('app_utilisateur_mes_participations');
        }

        // 🔁 retourner avec erreurs
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