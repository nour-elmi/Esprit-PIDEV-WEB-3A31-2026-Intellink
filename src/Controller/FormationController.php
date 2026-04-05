<?php

namespace App\Controller;

use App\Entity\Formation;
use App\Repository\FormationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/formateur')]
final class FormationController extends AbstractController
{
    #[Route('/', name: 'app_formation_index')]
    public function index(FormationRepository $repo): Response
    {
        $formations = $repo->findAll();
        return $this->render('/formateur/index.html.twig', [
            'formations' => $formations,
        ]);
    }

    #[Route('/add', name: 'app_formation_add')]
public function add(Request $request, EntityManagerInterface $em): Response
{
    $errors = [];
    $old = [];

    if ($request->isMethod('POST')) {

        $titre = trim($request->request->get('titre'));
        $description = trim($request->request->get('description'));
        $domaine = trim($request->request->get('domaine'));
        $niveau = $request->request->get('niveau');
        $urlVideo = trim($request->request->get('urlVideo'));

        $old = $request->request->all();

        // 🔴 VALIDATION
        if (empty($titre)) {
            $errors['titre'] = "Le titre est obligatoire";
        } elseif (strlen($titre) < 3) {
            $errors['titre'] = "Le titre doit contenir au moins 3 caractères";
        }
        if (empty($domaine)) {
    $errors['domaine'] = "Le domaine est obligatoire";
} elseif (strlen($domaine) < 3) {
    $errors['domaine'] = "Minimum 3 caractères";
} elseif (!preg_match("/^[a-zA-ZÀ-ÿ\s]+$/", $domaine)) {
    $errors['domaine'] = "Seulement des lettres autorisées";
}

        if (empty($description)) {
            $errors['description'] = "La description est obligatoire";
        }

        if (empty($urlVideo)) {
            $errors['urlVideo'] = "L'URL est obligatoire";
        } elseif (!filter_var($urlVideo, FILTER_VALIDATE_URL)) {
            $errors['urlVideo'] = "URL invalide";
        }

        // ✅ SI PAS D'ERREURS
        if (empty($errors)) {
            $formation = new Formation();
            $formation->setTitre($titre);
            $formation->setDescription($description);
            $formation->setDomaine($domaine);
            $formation->setNiveau($niveau);
            $formation->setUrlVideo($urlVideo);
            $formation->setIdFormateur(1);

            $em->persist($formation);
            $em->flush();

            $this->addFlash('success', 'Formation ajoutée avec succès !');
            return $this->redirectToRoute('app_formation_index');
        }
    }

    return $this->render('formateur/add.html.twig', [
        'errors' => $errors,
        'old' => $old
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

        $titre = trim($request->request->get('titre'));
        $description = trim($request->request->get('description'));
        $domaine = trim($request->request->get('domaine'));
        $niveau = $request->request->get('niveau');
        $urlVideo = trim($request->request->get('urlVideo'));

        $old = $request->request->all();

        // 🔴 VALIDATION
        if (empty($titre)) {
            $errors['titre'] = "Le titre est obligatoire";
        } elseif (strlen($titre) < 3) {
            $errors['titre'] = "Minimum 3 caractères";
        }

        if (empty($domaine)) {
    $errors['domaine'] = "Le domaine est obligatoire";
} elseif (strlen($domaine) < 3) {
    $errors['domaine'] = "Minimum 3 caractères";
} elseif (!preg_match("/^[a-zA-ZÀ-ÿ\s]+$/", $domaine)) {
    $errors['domaine'] = "Seulement des lettres autorisées";
}

        if (empty($description)) {
            $errors['description'] = "La description est obligatoire";
        }

        if (empty($urlVideo)) {
            $errors['urlVideo'] = "URL obligatoire";
        } elseif (!filter_var($urlVideo, FILTER_VALIDATE_URL)) {
            $errors['urlVideo'] = "URL invalide";
        }

        // ✅ SI OK
        if (empty($errors)) {
            $formation->setTitre($titre);
            $formation->setDescription($description);
            $formation->setDomaine($domaine);
            $formation->setNiveau($niveau);
            $formation->setUrlVideo($urlVideo);

            $em->flush();

            $this->addFlash('success', 'Formation modifiée avec succès !');
            return $this->redirectToRoute('app_formation_index');
        }
    }

    return $this->render('formateur/edit.html.twig', [
        'formation' => $formation,
        'errors' => $errors,
        'old' => $old
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

        $this->addFlash('success', 'Formation supprimée avec succès !');
        return $this->redirectToRoute('app_formation_index');
    }

    #[Route('/show/{id}', name: 'app_formation_show')]
public function show(int $id, FormationRepository $repo): Response
{
    $formation = $repo->find($id);

    if (!$formation) {
        throw $this->createNotFoundException('Formation introuvable');
    }

    return $this->render('formateur/show.html.twig', [
        'formation' => $formation,
    ]);
}
}