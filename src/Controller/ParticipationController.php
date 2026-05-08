<?php

namespace App\Controller;

use App\ContractBundle\Service\ContractPdfGenerator;
use App\Entity\Collaboration;
use App\Entity\ContratParticipation;
use App\Entity\Projet;
use App\Entity\Utilisateur;
use App\Form\DemandeParticipationType;
use App\MailingBundle\Service\ParticipationNotificationMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ParticipationController extends AbstractController
{
    #[Route('/participer/{id}', name: 'app_participation_add', requirements: ['id' => '\d+'])]
    public function add(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager,
        ParticipationNotificationMailer $participationNotificationMailer
    ): Response {
        $projet = $entityManager->getRepository(Projet::class)->find($id);

        if (!$projet) {
            throw $this->createNotFoundException('Projet introuvable.');
        }

        $user = $this->getUser();

        if (!$user || !method_exists($user, 'getId') || $user->getId() === null) {
            throw $this->createAccessDeniedException('Vous devez être connecté pour participer à un projet.');
        }

        $demande = new Collaboration();
        $demande->setProjet($projet);
        $demande->setUserId($user->getId());
        $demande->setEtat('EN_ATTENTE');
        $demande->setDateCreation(new \DateTime());

        $form = $this->createForm(DemandeParticipationType::class, $demande);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $roleChoisi = $form->get('roleSouhaite')->getData();
            $autreRole = trim((string) $form->get('autreRole')->getData());

            $disponibiliteChoisie = $form->get('disponibilite')->getData();
            $autreDisponibilite = trim((string) $form->get('autreDisponibilite')->getData());

            if ($roleChoisi === '__autre__') {
                $demande->setRoleSouhaite($autreRole !== '' ? $autreRole : null);
            } else {
                $demande->setRoleSouhaite($roleChoisi);
            }

            if ($disponibiliteChoisie === '__autre__') {
                $demande->setDisponibilite($autreDisponibilite !== '' ? $autreDisponibilite : null);
            } else {
                $demande->setDisponibilite($disponibiliteChoisie);
            }

            $entityManager->persist($demande);
            $entityManager->flush();

            $chef = $entityManager->getRepository(Utilisateur::class)->findOneBy([
                'email' => $projet->getCreateur(),
            ]);

            if ($user instanceof Utilisateur) {
                try {
                    $mailSent = $participationNotificationMailer->notifyChefForNewParticipation(
                        $demande,
                        $user,
                        $chef
                    );
                    if (!$mailSent) {
                        $this->addFlash('warning', 'Demande envoyee, mais email chef non envoye (email chef introuvable).');
                    }
                } catch (\Throwable) {
                    $this->addFlash('warning', 'Demande envoyee, mais impossible d\'envoyer la notification email au chef.');
                }
            }

            $this->addFlash('success', 'Votre demande a été envoyée avec succès.');

            return $this->redirectToRoute('app_mes_demandes');
        }

        return $this->render('participation/form.html.twig', [
            'form' => $form->createView(),
            'projet' => $projet,
            'editMode' => false,
        ]);
    }

    #[Route('/mes-demandes', name: 'app_mes_demandes')]
    public function mesDemandes(EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();

        if (!$user || !method_exists($user, 'getId') || $user->getId() === null) {
            throw $this->createAccessDeniedException('Vous devez être connecté pour voir vos demandes.');
        }

        $demandes = $entityManager->getRepository(Collaboration::class)->findBy(
            ['userId' => $user->getId()],
            ['dateCreation' => 'DESC', 'id' => 'DESC']
        );

        $contrats = $entityManager->getRepository(ContratParticipation::class)->findBy([
            'userId' => $user->getId(),
        ]);
        $contratsParDemande = [];
        foreach ($contrats as $contrat) {
            $demandeId = $contrat->getCollaboration()?->getId();
            if ($demandeId !== null) {
                $contratsParDemande[$demandeId] = $contrat;
            }
        }

        return $this->render('participation/mes_demandes.html.twig', [
            'demandes' => $demandes,
            'contratsParDemande' => $contratsParDemande,
        ]);
    }

    #[Route('/api/mes-demandes/calendar', name: 'app_api_mes_demandes_calendar_legacy', methods: ['GET'])]
    #[Route('/api/v1/participations/calendar', name: 'app_api_v1_participations_calendar', methods: ['GET'])]
    public function calendarApi(
        Request $request,
        EntityManagerInterface $entityManager,
        HttpClientInterface $httpClient
    ): JsonResponse
    {
        $user = $this->getUser();

        if (!$user || !method_exists($user, 'getId') || $user->getId() === null) {
            return new JsonResponse([
                'ok' => false,
                'message' => 'Utilisateur non connecte.',
            ], 401);
        }

        $month = (int) $request->query->get('month', 0);
        $year = (int) $request->query->get('year', 0);

        $repo = $entityManager->getRepository(Collaboration::class);
        $qb = $repo->createQueryBuilder('c')
            ->where('c.userId = :userId')
            ->setParameter('userId', (int) $user->getId())
            ->orderBy('c.dateCreation', 'DESC')
            ->addOrderBy('c.id', 'DESC');

        if ($month >= 1 && $month <= 12 && $year >= 2000 && $year <= 2100) {
            $start = new \DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month));
            $end = $start->modify('+1 month');
            $qb->andWhere('c.dateCreation >= :start')->andWhere('c.dateCreation < :end')
                ->setParameter('start', $start)
                ->setParameter('end', $end);
        }

        $demandes = $qb->getQuery()->getResult();

        $events = [];
        $counts = [];
        foreach ($demandes as $demande) {
            $date = $demande->getDateCreation();
            if (!$date instanceof \DateTimeInterface) {
                continue;
            }

            $key = $date->format('Y-m-d');
            $counts[$key] = (int) ($counts[$key] ?? 0) + 1;

            $events[] = [
                'id' => $demande->getId(),
                'date' => $key,
                'datetime' => $date->format(\DateTimeInterface::ATOM),
                'etat' => (string) ($demande->getEtat() ?? ''),
                'projet' => (string) ($demande->getProjet()?->getTitre() ?? 'Projet'),
            ];
        }

        $calendarApiUrl = rtrim((string) (
            $_ENV['CALENDAR_API_URL']
            ?? $_SERVER['CALENDAR_API_URL']
            ?? getenv('CALENDAR_API_URL')
        ), '/');

        $calendarApiKey = (string) (
            $_ENV['CALENDAR_API_KEY']
            ?? $_SERVER['CALENDAR_API_KEY']
            ?? getenv('CALENDAR_API_KEY')
        );

        $calendarApiCountry = strtoupper((string) (
            $_ENV['CALENDAR_API_COUNTRY']
            ?? $_SERVER['CALENDAR_API_COUNTRY']
            ?? getenv('CALENDAR_API_COUNTRY')
        ));

        $holidaysByDate = [];
        $apiStatus = 'disabled';
        $apiError = null;

        if ($calendarApiKey !== '' && $month >= 1 && $month <= 12 && $year >= 2000 && $year <= 2100) {
            try {
                $response = $httpClient->request('GET', $calendarApiUrl, [
                    'query' => [
                        'api_key' => $calendarApiKey,
                        'country' => $calendarApiCountry,
                        'year' => $year,
                        'month' => $month,
                    ],
                    'timeout' => 12,
                ]);

                $payload = $response->toArray(false);
                $holidays = $payload['response']['holidays'] ?? [];
                if (is_array($holidays)) {
                    foreach ($holidays as $holiday) {
                        if (!is_array($holiday)) {
                            continue;
                        }
                        $iso = (string) ($holiday['date']['iso'] ?? '');
                        $name = trim((string) ($holiday['name'] ?? ''));
                        if ($iso !== '') {
                            $day = substr($iso, 0, 10);
                            if (!isset($holidaysByDate[$day])) {
                                $holidaysByDate[$day] = [];
                            }
                            if ($name !== '') {
                                $holidaysByDate[$day][] = $name;
                            }
                        }
                    }
                }

                $apiStatus = 'ok';
            } catch (\Throwable $e) {
                $apiStatus = 'error';
                $apiError = $e->getMessage();
            }
        }

        return new JsonResponse([
            'ok' => true,
            'endpoint' => $request->getSchemeAndHttpHost() . $request->getPathInfo(),
            'filters' => [
                'month' => $month,
                'year' => $year,
            ],
            'counts' => $counts,
            'events' => $events,
            'total' => count($events),
            'holidaysByDate' => $holidaysByDate,
            'externalCalendarApi' => [
                'url' => $calendarApiUrl,
                'country' => $calendarApiCountry,
                'status' => $apiStatus,
                'error' => $apiError,
            ],
            'generatedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);
    }

    #[Route('/mes-demandes/contrat/{id}', name: 'app_participation_contrat_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function showContrat(
        int $id,
        EntityManagerInterface $entityManager
    ): Response {
        $demande = $entityManager->getRepository(Collaboration::class)->find($id);
        if (!$demande) {
            throw $this->createNotFoundException('Demande introuvable.');
        }

        $user = $this->getUser();
        if (!$user || !method_exists($user, 'getId') || $user->getId() === null) {
            throw $this->createAccessDeniedException('Vous devez etre connecte.');
        }
        if ((int) $demande->getUserId() !== (int) $user->getId()) {
            throw $this->createAccessDeniedException('Acces refuse.');
        }

        $contrat = $entityManager->getRepository(ContratParticipation::class)->findOneBy([
            'collaboration' => $demande,
        ]);
        if (!$contrat) {
            $this->addFlash('error', 'Aucun contrat disponible pour cette demande.');
            return $this->redirectToRoute('app_mes_demandes');
        }

        return $this->render('participation/contrat.html.twig', [
            'demande' => $demande,
            'contrat' => $contrat,
        ]);
    }

    #[Route('/mes-demandes/contrat/{id}/pdf', name: 'app_participation_contrat_pdf', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function downloadContratPdf(
        int $id,
        EntityManagerInterface $entityManager,
        ContractPdfGenerator $contractPdfGenerator
    ): Response {
        $demande = $entityManager->getRepository(Collaboration::class)->find($id);
        if (!$demande) {
            throw $this->createNotFoundException('Demande introuvable.');
        }

        $user = $this->getUser();
        if (!$user || !method_exists($user, 'getId') || $user->getId() === null) {
            throw $this->createAccessDeniedException('Vous devez etre connecte.');
        }
        if ((int) $demande->getUserId() !== (int) $user->getId()) {
            throw $this->createAccessDeniedException('Acces refuse.');
        }

        $contrat = $entityManager->getRepository(ContratParticipation::class)->findOneBy([
            'collaboration' => $demande,
        ]);
        if (!$contrat) {
            throw $this->createNotFoundException('Contrat introuvable.');
        }
        if ($contrat->getUserSignedAt() === null || strtoupper((string) $contrat->getStatut()) !== 'SIGNE') {
            $this->addFlash('error', 'Le PDF est disponible uniquement apres signature du contrat.');
            return $this->redirectToRoute('app_participation_contrat_show', ['id' => $demande->getId()]);
        }

        $pdfBinary = $contractPdfGenerator->generateSignedContractPdf($contrat, $demande);
        $projectTitle = trim((string) ($demande->getProjet()?->getTitre() ?? 'projet'));
        $safeTitle = preg_replace('/[^a-zA-Z0-9\-_]+/', '-', $projectTitle) ?: 'projet';
        $filename = sprintf('contrat-signe-%s-%d.pdf', strtolower($safeTitle), (int) $demande->getId());

        $response = new Response($pdfBinary);
        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $filename
        );
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }

    #[Route('/mes-demandes/contrat/{id}/signer', name: 'app_participation_contrat_sign', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function signContrat(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $demande = $entityManager->getRepository(Collaboration::class)->find($id);
        if (!$demande) {
            throw $this->createNotFoundException('Demande introuvable.');
        }

        $user = $this->getUser();
        if (!$user || !method_exists($user, 'getId') || $user->getId() === null) {
            throw $this->createAccessDeniedException('Vous devez etre connecte.');
        }
        if ((int) $demande->getUserId() !== (int) $user->getId()) {
            throw $this->createAccessDeniedException('Acces refuse.');
        }

        if (!$this->isCsrfTokenValid('sign_contrat_' . $demande->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_participation_contrat_show', ['id' => $demande->getId()]);
        }

        $contrat = $entityManager->getRepository(ContratParticipation::class)->findOneBy([
            'collaboration' => $demande,
        ]);
        if (!$contrat) {
            $this->addFlash('error', 'Contrat introuvable.');
            return $this->redirectToRoute('app_mes_demandes');
        }

        $signatureName = trim((string) $request->request->get('signature_name', ''));
        if ($signatureName === '' && method_exists($user, 'getNom')) {
            $signatureName = trim((string) $user->getNom());
        }
        if ($signatureName === '' && method_exists($user, 'getEmail')) {
            $signatureName = trim((string) $user->getEmail());
        }
        if ($signatureName === '') {
            $signatureName = 'Utilisateur';
        }

        $signatureDraw = trim((string) $request->request->get('signature_draw', ''));
        if ($signatureDraw === '') {
            $this->addFlash('error', 'Veuillez dessiner votre signature avant de valider.');
            return $this->redirectToRoute('app_participation_contrat_show', ['id' => $demande->getId()]);
        }

        $savedSignaturePath = $this->saveSignatureDrawing($signatureDraw, (int) $demande->getId(), (int) $user->getId());
        if ($savedSignaturePath === null) {
            $this->addFlash('error', 'Signature invalide. Veuillez recommencer le dessin.');
            return $this->redirectToRoute('app_participation_contrat_show', ['id' => $demande->getId()]);
        }

        $now = new \DateTimeImmutable();
        $contrat->setUserSignatureName($savedSignaturePath);
        $contrat->setUserSignedAt($now);
        $contrat->setStatut('SIGNE');
        $contrat->setUpdatedAt($now);

        $entityManager->flush();

        $this->addFlash('success', 'Contrat signe avec succes.');
        return $this->redirectToRoute('app_participation_contrat_show', ['id' => $demande->getId()]);
    }

    private function saveSignatureDrawing(string $dataUri, int $demandeId, int $userId): ?string
    {
        if (!str_starts_with($dataUri, 'data:image/png;base64,')) {
            return null;
        }

        $base64 = substr($dataUri, strlen('data:image/png;base64,'));
        // CHANGEMENT: substr retourne toujours string ici.
        // Ancien code: if ($base64 === false || $base64 === '') {
        if ($base64 === '') {
            return null;
        }

        $binary = base64_decode($base64, true);
        if ($binary === false || strlen($binary) < 120) {
            return null;
        }

        if (strlen($binary) > 2 * 1024 * 1024) {
            return null;
        }

        $projectDirParam = $this->getParameter('kernel.project_dir'); // CHANGEMENT
        if (!is_string($projectDirParam) || $projectDirParam === '') { // CHANGEMENT
            return null;
        }
        $projectDir = $projectDirParam;
        $relativeDir = 'uploads/signatures';
        $absoluteDir = $projectDir . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'signatures';
        if (!is_dir($absoluteDir) && !@mkdir($absoluteDir, 0775, true) && !is_dir($absoluteDir)) {
            return null;
        }

        $fileName = sprintf(
            'sig-d%s-u%s-%s.png',
            $demandeId,
            $userId,
            (new \DateTimeImmutable())->format('YmdHis')
        );

        $absolutePath = $absoluteDir . DIRECTORY_SEPARATOR . $fileName;
        $bytes = @file_put_contents($absolutePath, $binary);
        if ($bytes === false || $bytes <= 0) {
            return null;
        }

        return $relativeDir . '/' . $fileName;
    }

    #[Route('/participation/{id}/modifier', name: 'app_participation_edit', requirements: ['id' => '\d+'])]
    public function edit(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $demande = $entityManager->getRepository(Collaboration::class)->find($id);

        if (!$demande) {
            throw $this->createNotFoundException('Demande introuvable.');
        }

        $user = $this->getUser();

        if (!$user || !method_exists($user, 'getId') || $user->getId() === null) {
            throw $this->createAccessDeniedException('Vous devez être connecté pour modifier une demande.');
        }

        if ($demande->getUserId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Vous ne pouvez modifier que vos propres demandes.');
        }

        if ($demande->getEtat() !== 'EN_ATTENTE') {
            $this->addFlash('error', 'Seules les demandes en attente peuvent être modifiées.');
            return $this->redirectToRoute('app_mes_demandes');
        }

        $form = $this->createForm(DemandeParticipationType::class, $demande);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $roleChoisi = $form->get('roleSouhaite')->getData();
            $autreRole = trim((string) $form->get('autreRole')->getData());

            $disponibiliteChoisie = $form->get('disponibilite')->getData();
            $autreDisponibilite = trim((string) $form->get('autreDisponibilite')->getData());

            if ($roleChoisi === '__autre__') {
                $demande->setRoleSouhaite($autreRole !== '' ? $autreRole : null);
            } else {
                $demande->setRoleSouhaite($roleChoisi);
            }

            if ($disponibiliteChoisie === '__autre__') {
                $demande->setDisponibilite($autreDisponibilite !== '' ? $autreDisponibilite : null);
            } else {
                $demande->setDisponibilite($disponibiliteChoisie);
            }

            $entityManager->flush();


            $this->addFlash('success', 'Votre demande a été modifiée avec succès.');

            return $this->redirectToRoute('app_mes_demandes');
        }

        return $this->render('participation/form.html.twig', [
            'form' => $form->createView(),
            'projet' => $demande->getProjet(),
            'editMode' => true,
        ]);
    }

    #[Route('/participation/{id}/supprimer', name: 'app_participation_delete', requirements: ['id' => '\d+'])]
    public function delete(
        int $id,
        EntityManagerInterface $entityManager
    ): Response {
        $demande = $entityManager->getRepository(Collaboration::class)->find($id);

        if (!$demande) {
            throw $this->createNotFoundException('Demande introuvable.');
        }

        $user = $this->getUser();

        if (!$user || !method_exists($user, 'getId') || $user->getId() === null) {
            throw $this->createAccessDeniedException('Vous devez être connecté pour supprimer une demande.');
        }

        if ($demande->getUserId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Vous ne pouvez supprimer que vos propres demandes.');
        }

        if ($demande->getEtat() !== 'EN_ATTENTE') {
            $this->addFlash('error', 'Seules les demandes en attente peuvent être supprimées.');
            return $this->redirectToRoute('app_mes_demandes');
        }

        $entityManager->remove($demande);
        $entityManager->flush();


        $this->addFlash('success', 'Votre demande a été supprimée.');

        return $this->redirectToRoute('app_mes_demandes');
    }
}

