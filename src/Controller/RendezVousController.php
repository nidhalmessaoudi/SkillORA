<?php

namespace App\Controller;

use App\Entity\AvailabilitySlot;
use App\Entity\Course;
use App\Entity\Notification;
use App\Entity\RendezVous;
use App\Entity\User;
use App\Service\RendezVousMailer;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class RendezVousController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MailerInterface $mailer,
        private RendezVousMailer $rendezVousMailer,
        private LoggerInterface $logger,
        private SluggerInterface $slugger,
        private ValidatorInterface $validator,
    ) {}

    // =============================================
    //  STUDENT ROUTES: view & book rendez-vous
    // =============================================

    /**
     * Student: list MY rendez-vous requests
     */
    #[Route('/rendezvous', name: 'public_rendezvous_index')]
    #[Route('/rendezvous/new', name: 'app_rendez_vous_new')]
    #[IsGranted('ROLE_USER')]
    public function publicIndex(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $rawStatus = (string) $request->query->get('status', 'all');
        $statusAliases = [
            'all' => 'all',
            'en_attente' => 'en_attente',
            'confirmé' => 'confirmé',
            'confirme' => 'confirmé',
            'refusé' => 'refusé',
            'refuse' => 'refusé',
            'rejeté' => 'refusé',
            'rejete' => 'refusé',
        ];
        $selectedStatus = $statusAliases[$rawStatus] ?? 'all';
        $perPage = 9;
        $page = max(1, $request->query->getInt('page', 1));

        $rdvRepo = $this->entityManager->getRepository(RendezVous::class);
        $baseListQb = $rdvRepo->createQueryBuilder('r')
            ->leftJoin('r.slot', 's')->addSelect('s')
            ->leftJoin('r.professor', 'p')->addSelect('p')
            ->leftJoin('r.student', 'st')->addSelect('st')
            ->leftJoin('r.course', 'c')->addSelect('c');

        $baseCountQb = $rdvRepo->createQueryBuilder('r')
            ->select('COUNT(r.id)');

        if ($this->isGranted('ROLE_PROFESSOR') && !$this->isGranted('ROLE_ADMIN')) {
            $baseListQb
                ->andWhere('r.professor = :user')
                ->setParameter('user', $user);
            $baseCountQb
                ->andWhere('r.professor = :user')
                ->setParameter('user', $user);
        } elseif (!$this->isGranted('ROLE_ADMIN')) {
            $baseListQb
                ->andWhere('r.student = :user')
                ->setParameter('user', $user);
            $baseCountQb
                ->andWhere('r.student = :user')
                ->setParameter('user', $user);
        }

        $listQb = clone $baseListQb;

        if ($selectedStatus === 'all') {
            $listQb
                ->andWhere('r.statut IN (:activeStatuses)')
                ->setParameter('activeStatuses', [RendezVous::STATUS_EN_ATTENTE, RendezVous::STATUS_CONFIRME])
                ->addOrderBy(
                    "CASE WHEN r.statut = '" . RendezVous::STATUS_EN_ATTENTE . "' THEN 0 WHEN r.statut = '" . RendezVous::STATUS_CONFIRME . "' THEN 1 ELSE 2 END",
                    'ASC'
                )
                ->addOrderBy('r.createdAt', 'DESC');
        } elseif ($selectedStatus === 'en_attente') {
            $listQb
                ->andWhere('r.statut = :statusPending')
                ->setParameter('statusPending', RendezVous::STATUS_EN_ATTENTE)
                ->addOrderBy('r.createdAt', 'DESC');
        } elseif ($selectedStatus === 'confirmé') {
            $listQb
                ->andWhere('r.statut = :statusConfirmed')
                ->setParameter('statusConfirmed', RendezVous::STATUS_CONFIRME)
                ->addOrderBy('r.createdAt', 'DESC');
        } else {
            $listQb
                ->andWhere('r.statut = :statusRefused')
                ->setParameter('statusRefused', RendezVous::STATUS_REJETE)
                ->addOrderBy('r.createdAt', 'DESC');
        }

        $countQb = clone $listQb;
        $totalItems = (int) $countQb
            ->select('COUNT(DISTINCT r.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $totalPages = max(1, (int) ceil($totalItems / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $rendezvous = $listQb
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        $activeCountQb = clone $baseCountQb;
        $pendingCountQb = clone $baseCountQb;
        $confirmedCountQb = clone $baseCountQb;
        $refusedCountQb = clone $baseCountQb;

        $activeCount = (int) $activeCountQb
            ->andWhere('r.statut IN (:activeStatuses)')
            ->setParameter('activeStatuses', [RendezVous::STATUS_EN_ATTENTE, RendezVous::STATUS_CONFIRME])
            ->getQuery()
            ->getSingleScalarResult();

        $pendingCount = (int) $pendingCountQb
            ->andWhere('r.statut = :statusPending')
            ->setParameter('statusPending', RendezVous::STATUS_EN_ATTENTE)
            ->getQuery()
            ->getSingleScalarResult();

        $confirmedCount = (int) $confirmedCountQb
            ->andWhere('r.statut = :statusConfirmed')
            ->setParameter('statusConfirmed', RendezVous::STATUS_CONFIRME)
            ->getQuery()
            ->getSingleScalarResult();

        $refusedCount = (int) $refusedCountQb
            ->andWhere('r.statut = :statusRefused')
            ->setParameter('statusRefused', RendezVous::STATUS_REJETE)
            ->getQuery()
            ->getSingleScalarResult();

        // Available slots: only future, not booked
        $availableSlots = $this->entityManager->getRepository(AvailabilitySlot::class)->createQueryBuilder('s')
            ->where('s.isBooked = false')
            ->andWhere('s.professor IS NOT NULL')
            ->andWhere('s.startAt > :now')
            ->setParameter('now', new \DateTime())
            ->orderBy('s.startAt', 'ASC')
            ->getQuery()
            ->getResult();

        // All courses for the booking modal
        $courses = $this->entityManager->getRepository(Course::class)->findBy(
            ['status' => 'published'],
            ['title' => 'ASC']
        );

        return $this->render('rendez_vous/public_index.html.twig', [
            'rendezvous_list' => $rendezvous,
            'available_slots' => $availableSlots,
            'courses' => $courses,
            'selected_status' => $selectedStatus,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total_items' => $totalItems,
                'total_pages' => $totalPages,
                'has_prev' => $page > 1,
                'has_next' => $page < $totalPages,
                'prev_page' => max(1, $page - 1),
                'next_page' => min($totalPages, $page + 1),
            ],
            'count_active' => $activeCount,
            'count_pending' => $pendingCount,
            'count_confirmed' => $confirmedCount,
            'count_refused' => $refusedCount,
        ]);
    }

    #[Route('/rendezvous/{id}', name: 'public_rendezvous_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function publicShow(int $id): Response
    {
        $rdv = $this->entityManager->getRepository(RendezVous::class)
            ->createQueryBuilder('r')
            ->leftJoin('r.slot', 's')->addSelect('s')
            ->leftJoin('r.student', 'st')->addSelect('st')
            ->leftJoin('r.professor', 'p')->addSelect('p')
            ->leftJoin('r.course', 'c')->addSelect('c')
            ->andWhere('r.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$rdv instanceof RendezVous) {
            throw $this->createNotFoundException('Rendez-vous introuvable.');
        }

        /** @var User|null $user */
        $user = $this->getUser();
        $isOwnerStudent = $rdv->getStudent() && $user && $rdv->getStudent()->getId() === $user->getId();
        $isOwnerProfessor = $rdv->getProfessor() && $user && $rdv->getProfessor()->getId() === $user->getId();
        $isAdmin = $this->isGranted('ROLE_ADMIN');

        if (!$isOwnerStudent && !$isOwnerProfessor && !$isAdmin) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('rendez_vous/public_show.html.twig', [
            'rdv' => $rdv,
        ]);
    }

    /**
     * AJAX: Student creates a new RDV by selecting a slot
     */
    #[Route('/rendezvous/api/create', name: 'public_rendezvous_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function apiCreate(Request $request): Response
    {
        $isAjax = $request->isXmlHttpRequest();
        $redirectUrl = $this->generateUrl('public_rendezvous_index');
        $fail = function (int $status, string $message, array $errors = []) use ($isAjax, $redirectUrl): Response {
            if ($isAjax) {
                return $this->json([
                    'success' => false,
                    'message' => $message,
                    'errors' => $errors,
                    'redirectUrl' => $redirectUrl,
                ], $status);
            }

            $this->addFlash('error', $message);
            return $this->redirectToRoute('public_rendezvous_index');
        };

        if ($this->isGranted('ROLE_PROFESSOR') || $this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', 'Seuls les étudiants peuvent créer un rendez-vous.');

            if ($isAjax) {
                return $this->json([
                    'success' => false,
                    'message' => 'Seuls les étudiants peuvent créer un rendez-vous.',
                    'errors' => [],
                    'redirectUrl' => $redirectUrl,
                ], 403);
            }

            return $this->redirectToRoute('public_rendezvous_index');
        }

        /** @var User $student */
        $student = $this->getUser();

        $csrfToken = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('public_rendezvous_create', $csrfToken)) {
            return $fail(403, 'CSRF invalide, rechargez la page.');
        }

        $slotId = $request->request->get('slot_id');
        if (!$slotId) {
            return $fail(400, 'Veuillez sélectionner un créneau.', ['slot_id' => 'Veuillez sélectionner un créneau.']);
        }

        $slot = $this->entityManager->getRepository(AvailabilitySlot::class)->find((int) $slotId);
        if (!$slot) {
            return $fail(404, 'Créneau introuvable.', ['slot_id' => 'Créneau introuvable.']);
        }
        if ($slot->isBooked()) {
            return $fail(409, 'Ce créneau est déjà réservé.', ['slot_id' => 'Ce créneau est déjà réservé.']);
        }
        if ($slot->getStartAt() <= new \DateTime()) {
            return $fail(400, 'Ce créneau est déjà passé.', ['slot_id' => 'Ce créneau est déjà passé.']);
        }

        // DB constraint: one rendez-vous per slot (unique slot_id).
        // If an old rejected rendez-vous still references this slot, remove it before creating a new one.
        $existingRdvOnSlot = $this->entityManager->getRepository(RendezVous::class)->findOneBy(['slot' => $slot]);
        if ($existingRdvOnSlot instanceof RendezVous) {
            if ($existingRdvOnSlot->getStatut() === RendezVous::STATUS_REJETE) {
                $this->entityManager->remove($existingRdvOnSlot);
                $this->entityManager->flush();
                $slot->setIsBooked(false);
            } else {
                return $fail(409, 'Ce créneau est déjà utilisé par un autre rendez-vous.', [
                    'slot_id' => 'Ce créneau est déjà utilisé par un autre rendez-vous.',
                ]);
            }
        }

        // Get professor from the slot
        $professor = $slot->getProfessor();
        if (!$professor) {
            return $fail(400, 'Ce créneau n\'a pas de professeur associé.', ['slot_id' => 'Ce créneau n\'a pas de professeur associé.']);
        }

        // Optional: course selection
        $courseId = $request->request->get('course_id');
        $course = null;
        if ($courseId) {
            $course = $this->entityManager->getRepository(Course::class)->find((int) $courseId);
        }

        // Optional: student message
        $message = $request->request->get('message', '');

        // Meeting type
        $meetingType = $request->request->get('meeting_type');
        if (!$meetingType) {
            return $fail(400, 'Veuillez sélectionner le type de rendez-vous.', ['meeting_type' => 'Veuillez sélectionner le type de rendez-vous.']);
        }
        if (!in_array($meetingType, [RendezVous::TYPE_ONLINE, RendezVous::TYPE_IN_PERSON], true)) {
            $meetingType = RendezVous::TYPE_ONLINE;
        }

        if ($meetingType === RendezVous::TYPE_IN_PERSON && !$slot->getLocationLabel()) {
            return $fail(400, 'Ce créneau n’a pas de lieu défini. Choisissez un autre créneau.', [
                'slot_id' => 'Ce créneau n’a pas de lieu défini. Choisissez un autre créneau.',
            ]);
        }

        /** @var UploadedFile|null $coursePdf */
        $coursePdf = $request->files->get('course_pdf');
        if ($coursePdf instanceof UploadedFile) {
            $violations = $this->validator->validate($coursePdf, [
                new File([
                    'maxSize' => '5M',
                    'mimeTypes' => ['application/pdf'],
                    'mimeTypesMessage' => 'Veuillez uploader un fichier PDF valide.',
                ]),
            ]);

            if (\count($violations) > 0) {
                return $fail(422, (string) $violations[0]->getMessage(), [
                    'course_pdf' => (string) $violations[0]->getMessage(),
                ]);
            }
        }

        $rdv = new RendezVous();
        $rdv->setSlot($slot);
        $rdv->setStudent($student);
        $rdv->setProfessor($professor);
        $rdv->setCourse($course);
        $rdv->setMessage($message ?: null);
        $rdv->setMeetingType($meetingType);
        // Meeting link is provided only by the professor at accept time.
        $rdv->setMeetingLink(null);
        if ($meetingType === RendezVous::TYPE_IN_PERSON) {
            $rdv->setLocationLabel($slot->getLocationLabel());
            $rdv->setLocationLat($slot->getLocationLat());
            $rdv->setLocationLng($slot->getLocationLng());
        } else {
            $rdv->setLocationLabel(null);
            $rdv->setLocationLat(null);
            $rdv->setLocationLng(null);
        }
        $rdv->setStatut(RendezVous::STATUS_EN_ATTENTE);
        $slot->setIsBooked(true);

        if ($coursePdf instanceof UploadedFile) {
            $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/cours';
            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
                return $fail(500, 'Impossible de créer le dossier de destination du PDF.');
            }

            $originalFilename = pathinfo((string) $coursePdf->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $this->slugger->slug($originalFilename)->lower();
            $extension = $coursePdf->guessExtension() ?: 'pdf';
            $newFilename = $safeFilename . '-' . uniqid('', true) . '.' . $extension;

            try {
                $coursePdf->move($uploadDir, $newFilename);
                $rdv->setCoursePdfName($newFilename);
            } catch (FileException $e) {
                $this->logger->error('PDF upload failed for rendezvous', ['error' => $e->getMessage()]);
                return $fail(500, 'Échec de l’upload du PDF.');
            }
        }

        $this->entityManager->persist($rdv);
        try {
            $this->entityManager->flush();
        } catch (\Throwable $e) {
            $this->logger->error('Rendez-vous creation failed', [
                'slot_id' => $slot->getId(),
                'student_id' => $student->getId(),
                'error' => $e->getMessage(),
            ]);
            return $fail(500, 'Impossible de créer le rendez-vous pour le moment. Réessayez.');
        }
        $this->addFlash('success', 'Votre demande de rendez-vous a été envoyée avec succès. Le professeur sera notifié.');

        // Optional: notify professor on new request
        $professorMessage = sprintf(
            'Nouvelle demande de rendez-vous de %s.',
            $student->getFullName()
        );
        $notification = (new Notification())
            ->setUser($professor)
            ->setTitle('Nouvelle demande de rendez-vous')
            ->setMessage($professorMessage)
            ->setLink($this->generateUrl('public_rendezvous_index'));
        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        // Dedicated email service: sends both professor + student emails, without breaking flow on failures.
        $this->rendezVousMailer->sendCreatedRendezVousEmails($rdv);

        if (!$isAjax) {
            return $this->redirectToRoute('public_rendezvous_index');
        }

        return $this->json([
            'success' => true,
            'message' => 'Votre demande de rendez-vous a été envoyée avec succès. Le professeur sera notifié.',
            'redirectUrl' => $redirectUrl,
            'rdv' => [
                'id' => $rdv->getId(),
                'statut' => $rdv->getStatut(),
                'slot_id' => $slot->getId(),
                'slot_start' => $slot->getStartAt()->format('d/m/Y H:i'),
                'slot_end' => $slot->getEndAt()->format('d/m/Y H:i'),
                'professor' => $professor->getFullName(),
                'course' => $course ? $course->getTitle() : null,
                'message' => $rdv->getMessage(),
                'course_pdf_name' => $rdv->getCoursePdfName(),
                'meeting_type' => $rdv->getMeetingType(),
                'created_at' => $rdv->getCreatedAt()->format('d/m/Y H:i'),
            ],
        ]);
    }

    /**
     * AJAX: Student updates RDV slot (only if en_attente)
     */
    #[Route('/rendezvous/api/{id}/update', name: 'public_rendezvous_update', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function apiUpdate(Request $request, RendezVous $rdv): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $isOwnerStudent = $rdv->getStudent() && $user && $rdv->getStudent()->getId() === $user->getId();
        if (!$isOwnerStudent) {
            return $this->json(['success' => false, 'error' => 'Seul l\'étudiant peut modifier le créneau.'], 403);
        }

        if ($rdv->getStatut() !== RendezVous::STATUS_EN_ATTENTE) {
            return $this->json(['success' => false, 'error' => 'Le créneau ne peut plus être modifié après confirmation.'], 400);
        }

        $slotId = $request->request->get('slot_id');
        if (!$slotId) {
            return $this->json(['success' => false, 'error' => 'Veuillez sélectionner un créneau.'], 400);
        }

        $currentSlot = $rdv->getSlot();

        // If same slot, nothing to do
        if ((int) $slotId === ($currentSlot ? $currentSlot->getId() : null)) {
            $slot = $rdv->getSlot();
            return $this->json([
                'success' => true,
                'rdv' => [
                    'id' => $rdv->getId(),
                    'statut' => $rdv->getStatut(),
                    'slot_id' => $slot ? $slot->getId() : null,
                    'slot_start' => $slot ? $slot->getStartAt()->format('d/m/Y H:i') : '',
                    'slot_end' => $slot ? $slot->getEndAt()->format('d/m/Y H:i') : '',
                    'created_at' => $rdv->getCreatedAt()->format('d/m/Y H:i'),
                ],
            ]);
        }

        $newSlot = $this->entityManager->getRepository(AvailabilitySlot::class)->find((int) $slotId);
        if (!$newSlot) {
            return $this->json(['success' => false, 'error' => 'Créneau introuvable.'], 404);
        }
        if ($newSlot->isBooked()) {
            return $this->json(['success' => false, 'error' => 'Ce créneau est déjà réservé.'], 409);
        }
        if ($rdv->getProfessor() && $newSlot->getProfessor() && $rdv->getProfessor()->getId() !== $newSlot->getProfessor()->getId()) {
            return $this->json(['success' => false, 'error' => 'Le créneau sélectionné ne correspond pas au professeur du rendez-vous.'], 400);
        }

        // Free old slot, book new slot.
        if ($currentSlot) {
            $currentSlot->setIsBooked(false);
        }
        $newSlot->setIsBooked(true);
        $rdv->setSlot($newSlot);

        $this->entityManager->flush();

        $slot = $rdv->getSlot();
        return $this->json([
            'success' => true,
            'rdv' => [
                'id' => $rdv->getId(),
                'statut' => $rdv->getStatut(),
                'slot_id' => $slot ? $slot->getId() : null,
                'slot_start' => $slot ? $slot->getStartAt()->format('d/m/Y H:i') : '',
                'slot_end' => $slot ? $slot->getEndAt()->format('d/m/Y H:i') : '',
                'created_at' => $rdv->getCreatedAt()->format('d/m/Y H:i'),
            ],
        ]);
    }

    // =============================================
    //  PROFESSOR ROUTES: manage RDV status + meeting link
    // =============================================

    /**
     * AJAX: Professor confirms/rejects a RDV
     */
    #[Route('/rendezvous/api/{id}/status', name: 'public_rendezvous_status', methods: ['POST'])]
    #[IsGranted('ROLE_PROFESSOR')]
    public function apiUpdateStatus(Request $request, RendezVous $rdv): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$rdv->getProfessor() || $rdv->getProfessor()->getId() !== $user->getId()) {
            return $this->json(['success' => false, 'error' => 'Ce rendez-vous ne vous appartient pas.'], 403);
        }

        $statut = $request->request->get('statut');
        $allowed = [RendezVous::STATUS_CONFIRME, RendezVous::STATUS_REJETE];

        if (!in_array($statut, $allowed, true)) {
            return $this->json(['success' => false, 'error' => 'Statut invalide.'], 400);
        }

        $oldStatut = $rdv->getStatut();
        $rdv->setStatut($statut);

        // If rejected, free the slot
        if ($statut === RendezVous::STATUS_REJETE && $oldStatut !== RendezVous::STATUS_REJETE) {
            $rdv->getSlot()?->setIsBooked(false);
        }
        // If un-rejected, re-book the slot
        if ($oldStatut === RendezVous::STATUS_REJETE && $statut !== RendezVous::STATUS_REJETE) {
            $rdv->getSlot()?->setIsBooked(true);
        }

        $this->entityManager->flush();

        return $this->json(['success' => true, 'statut' => $rdv->getStatut()]);
    }

    #[Route('/rendezvous/{id}/accept', name: 'public_rendezvous_accept', methods: ['POST'])]
    #[IsGranted('ROLE_PROFESSOR')]
    public function accept(Request $request, RendezVous $rdv): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $redirectUrl = (string) ($request->headers->get('referer') ?: $this->generateUrl('public_rendezvous_index'));

        if (!$rdv->getProfessor() || !$user || $rdv->getProfessor()->getId() !== $user->getId()) {
            $this->addFlash('error', 'Ce rendez-vous ne vous est pas assigné.');
            return $this->redirect($redirectUrl);
        }

        if (!$this->isCsrfTokenValid('accept_rdv_' . $rdv->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirect($redirectUrl);
        }

        if ($rdv->getStatut() === RendezVous::STATUS_CONFIRME) {
            $this->addFlash('error', 'Ce rendez-vous est déjà confirmé.');
            return $this->redirect($redirectUrl);
        }

        if ($rdv->getStatut() === RendezVous::STATUS_REJETE) {
            $this->addFlash('info', 'Ce rendez-vous est déjà refusé.');
            return $this->redirect($redirectUrl);
        }

        if ($rdv->getStatut() !== RendezVous::STATUS_EN_ATTENTE) {
            $this->addFlash('info', 'Aucun changement de statut possible.');
            return $this->redirect($redirectUrl);
        }

        if ($rdv->getMeetingLink() !== null) {
            $this->addFlash('error', 'Le lien de réunion est déjà défini et ne peut plus être modifié.');
            return $this->redirect($redirectUrl);
        }

        $slot = $rdv->getSlot();
        $slotHasLocation = $slot && (
            trim((string) $slot->getLocationLabel()) !== '' ||
            $slot->getLocationLat() !== null ||
            $slot->getLocationLng() !== null
        );

        $meetingLink = trim((string) $request->request->get('meeting_link', ''));

        if (!$slotHasLocation) {
            if ($meetingLink === '' || !filter_var($meetingLink, FILTER_VALIDATE_URL)) {
                $this->addFlash('error', "Ce créneau n'a pas de lieu. Veuillez fournir un lien de réunion.");
                return $this->redirect($redirectUrl);
            }
        } elseif ($meetingLink !== '' && !filter_var($meetingLink, FILTER_VALIDATE_URL)) {
            $this->addFlash('error', 'Lien de réunion invalide (URL requise).');
            return $this->redirect($redirectUrl);
        }

        $rdv->setStatut(RendezVous::STATUS_CONFIRME);

        if ($slotHasLocation && $meetingLink === '') {
            $rdv->setMeetingType(RendezVous::TYPE_IN_PERSON);
            $rdv->setMeetingLink(null);
            $rdv->setLocationLabel($slot?->getLocationLabel());
            $rdv->setLocationLat($slot?->getLocationLat());
            $rdv->setLocationLng($slot?->getLocationLng());
            $rdv->setLocation($slot?->getLocationLabel());
        } else {
            $rdv->setMeetingType(RendezVous::TYPE_ONLINE);
            $rdv->setMeetingLink($meetingLink);
            $rdv->setLocationLabel(null);
            $rdv->setLocationLat(null);
            $rdv->setLocationLng(null);
            $rdv->setLocation(null);
        }

        $student = $rdv->getStudent();
        $professor = $rdv->getProfessor();
        $studentName = $student ? $student->getFullName() : 'Étudiant';
        $profName = $professor ? $professor->getFullName() : 'Professeur';
        $dateLabel = $slot ? $slot->getStartAt()->format('d/m/Y') : 'N/A';
        $timeLabel = $slot ? $slot->getStartAt()->format('H:i') . ' → ' . $slot->getEndAt()->format('H:i') : 'N/A';
        $courseLabel = $rdv->getCourse() ? $rdv->getCourse()->getTitle() : 'N/A';
        $meetingTypeLabel = $rdv->getMeetingType() === RendezVous::TYPE_IN_PERSON ? 'En personne' : 'En ligne';
        $locationLabel = $slot ? trim((string) $slot->getLocationLabel()) : '';
        if ($locationLabel === '') {
            $locationLabel = $rdv->getLocationLabel() ?: '';
        }
        $rdvLink = $this->generateUrl('public_rendezvous_index') . '#rdv-' . $rdv->getId();

        if ($student) {
            $messageLines = [
                sprintf('Professeur: %s', $profName),
                sprintf('Date: %s', $dateLabel),
                sprintf('Heure: %s', $timeLabel),
                sprintf('Cours: %s', $courseLabel),
            ];

            if ($rdv->getMeetingType() === RendezVous::TYPE_ONLINE) {
                $messageLines[] = sprintf('Lien: %s', $rdv->getMeetingLink());
            } else {
                $messageLines[] = sprintf('Lieu: %s', $locationLabel !== '' ? $locationLabel : 'Lieu non défini');
            }

            $notification = (new Notification())
                ->setUser($student)
                ->setTitle('Rendez-vous confirmé')
                ->setMessage("Votre rendez-vous est confirmé.\n" . implode("\n", $messageLines))
                ->setLink($rdvLink)
                ->setIsRead(false)
                ->setCreatedAt(new \DateTimeImmutable());
            $this->entityManager->persist($notification);
        }

        $this->entityManager->flush();

        if ($student && $student->getEmail()) {
            try {
                $email = (new Email())
                    ->to($student->getEmail())
                    ->subject('Votre rendez-vous est confirmé')
                    ->html($this->renderView('emails/rendezvous_accepted.html.twig', [
                        'studentName' => $studentName,
                        'profName' => $profName,
                        'rdv' => $rdv,
                        'dateLabel' => $dateLabel,
                        'timeLabel' => $timeLabel,
                        'courseLabel' => $courseLabel,
                        'meetingTypeLabel' => $meetingTypeLabel,
                        'locationLabel' => $locationLabel !== '' ? $locationLabel : null,
                        'rdvLink' => $rdvLink,
                    ]))
                    ->text(sprintf(
                        "Bonjour %s,\n\nVotre rendez-vous avec %s est confirmé.\nDate: %s\nHeure: %s\nCours: %s\nType: %s%s%s\n\nDétails: %s",
                        $studentName,
                        $profName,
                        $dateLabel,
                        $timeLabel,
                        $courseLabel,
                        $meetingTypeLabel,
                        $rdv->getMeetingType() === RendezVous::TYPE_ONLINE ? "\nLien: " . $rdv->getMeetingLink() : '',
                        $rdv->getMeetingType() === RendezVous::TYPE_IN_PERSON ? "\nLieu: " . ($locationLabel !== '' ? $locationLabel : 'Lieu non défini') : '',
                        $rdvLink
                    ));

                $this->mailer->send($email);
            } catch (TransportExceptionInterface|\Throwable $exception) {
                $this->logger->error('Failed to send rendezvous accepted email', [
                    'rdv_id' => $rdv->getId(),
                    'student_id' => $student->getId(),
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $this->addFlash('success', 'Rendez-vous confirmé.');

        return $this->redirect($redirectUrl);
    }

    #[Route('/rendezvous/{id}/refuse', name: 'public_rendezvous_refuse', methods: ['POST'])]
    #[IsGranted('ROLE_PROFESSOR')]
    public function refuse(Request $request, RendezVous $rdv): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$rdv->getProfessor() || $rdv->getProfessor()->getId() !== $user->getId()) {
            $this->addFlash('error', 'Ce rendez-vous ne vous est pas assigné.');
            return $this->redirectToRoute('public_rendezvous_index');
        }

        if (!$this->isCsrfTokenValid('refuse_rdv_' . $rdv->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('public_rendezvous_index');
        }

        $reason = trim((string) $request->request->get('refusal_reason', ''));
        if ($reason === '' || mb_strlen($reason) < 5) {
            $this->addFlash('error', 'La cause de refus est obligatoire (minimum 5 caractères).');
            return $this->redirectToRoute('public_rendezvous_index');
        }

        if (in_array($rdv->getStatut(), [RendezVous::STATUS_EN_ATTENTE, RendezVous::STATUS_CONFIRME], true)) {
            $rdv->setStatut(RendezVous::STATUS_REJETE);
            $rdv->setRefusalReason($reason);
            $rdv->getSlot()?->setIsBooked(false);

            if ($rdv->getStudent()) {
                $studentName = $rdv->getStudent()->getFullName();
                $profName = $rdv->getProfessor() ? $rdv->getProfessor()->getFullName() : $user->getFullName();
                $message = sprintf(
                    "Bonjour M. %s,\n\nM. %s a refusé votre rendez-vous.\n\nMotif du refus : %s",
                    $studentName,
                    $profName,
                    $reason
                );
                $notification = (new Notification())
                    ->setUser($rdv->getStudent())
                    ->setTitle('Rendez-vous refusé')
                    ->setMessage($message)
                    ->setLink($this->generateUrl('public_rendezvous_index') . '#rdv-' . $rdv->getId())
                    ->setIsRead(false)
                    ->setCreatedAt(new \DateTimeImmutable());
                $this->entityManager->persist($notification);
            }

            $this->entityManager->flush();
            $this->addFlash('warning', 'Rendez-vous refusé.');
        } else {
            $this->addFlash('info', 'Ce rendez-vous est déjà refusé.');
        }

        return $this->redirectToRoute('public_rendezvous_index');
    }

    /**
     * Meeting fields are immutable after creation
     */
    #[Route('/rendezvous/api/{id}/meeting', name: 'public_rendezvous_meeting', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function apiUpdateMeeting(RendezVous $rdv): JsonResponse
    {
        return $this->json([
            'success' => false,
            'error' => 'Les informations de réunion ne peuvent pas être modifiées après création.',
            'id' => $rdv->getId(),
            'meeting_link' => $rdv->getMeetingLink(),
            'location' => $rdv->getLocation(),
            'meeting_type' => $rdv->getMeetingType(),
        ], 403);
    }

    // =============================================
    //  ADMIN ROUTES
    // =============================================

    #[Route('/admin/rendezvous/', name: 'admin_rendezvous_index')]
    #[IsGranted('ROLE_ADMIN')]
    public function index(): Response
    {
        $rendezvous = $this->entityManager->getRepository(RendezVous::class)->findBy([], ['createdAt' => 'DESC']);

        return $this->render('rendez_vous/index.html.twig', [
            'rendezvous_list' => $rendezvous,
        ]);
    }

    #[Route('/admin/rendezvous/new', name: 'admin_rendezvous_new')]
    #[IsGranted('ROLE_ADMIN')]
    public function new(Request $request): Response
    {
        $rdv = new RendezVous();
        $errors = [];
        $formData = [
            'slot_id' => null,
        ];

        $availableSlots = $this->entityManager->getRepository(AvailabilitySlot::class)->createQueryBuilder('s')
            ->where('s.isBooked = false')
            ->andWhere('s.startAt > :now')
            ->setParameter('now', new \DateTime())
            ->orderBy('s.startAt', 'ASC')
            ->getQuery()
            ->getResult();

        $slotId = $request->query->get('slot');
        if ($slotId) {
            $slot = $this->entityManager->getRepository(AvailabilitySlot::class)->find((int) $slotId);
            if ($slot instanceof AvailabilitySlot) {
                $rdv->setSlot($slot);
                $formData['slot_id'] = $slot->getId();
            }
        }

        return $this->render('rendez_vous/new.html.twig', [
            'rdv' => $rdv,
            'available_slots' => $availableSlots,
            'form_data' => $formData,
            'errors' => $errors,
        ]);
    }

    #[Route('/admin/rendezvous/{id}', name: 'admin_rendezvous_show')]
    #[IsGranted('ROLE_ADMIN')]
    public function show(int $id): Response
    {
        $rdv = $this->entityManager->getRepository(RendezVous::class)
            ->createQueryBuilder('r')
            ->leftJoin('r.slot', 's')
            ->addSelect('s')
            ->andWhere('r.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$rdv) {
            throw $this->createNotFoundException('Rendez-vous introuvable.');
        }

        return $this->render('rendez_vous/show.html.twig', [
            'rdv' => $rdv,
        ]);
    }

    #[Route('/admin/rendezvous/{id}/edit', name: 'admin_rendezvous_edit')]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(Request $request, RendezVous $rdv): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();
        $isOwnerStudent = $rdv->getStudent() && $user && $rdv->getStudent()->getId() === $user->getId();
        $canEditSlot = $isOwnerStudent && $rdv->getStatut() === RendezVous::STATUS_EN_ATTENTE;

        $availableSlots = $this->entityManager->getRepository(AvailabilitySlot::class)->createQueryBuilder('s')
            ->where('s.isBooked = false')
            ->andWhere('s.startAt > :now')
            ->setParameter('now', new \DateTime())
            ->orderBy('s.startAt', 'ASC')
            ->getQuery()
            ->getResult();

        $currentSlot = $rdv->getSlot();
        $slotInList = false;
        foreach ($availableSlots as $s) {
            if ($currentSlot && $s->getId() === $currentSlot->getId()) {
                $slotInList = true;
                break;
            }
        }
        if ($currentSlot && !$slotInList) {
            array_unshift($availableSlots, $currentSlot);
        }

        $formData = [
            'slot_id' => $request->isMethod('POST') ? $request->request->get('slot_id', (string) ($currentSlot ? $currentSlot->getId() : '')) : ($currentSlot ? $currentSlot->getId() : ''),
            'statut'  => $request->isMethod('POST') ? $request->request->get('statut', '') : $rdv->getStatut(),
        ];
        $errors = [];

        if ($request->isMethod('POST')) {
            $allowedStatuses = [RendezVous::STATUS_EN_ATTENTE, RendezVous::STATUS_CONFIRME, RendezVous::STATUS_REJETE];
            if (!in_array($formData['statut'], $allowedStatuses, true)) {
                $errors['statut'] = 'Statut invalide.';
            }

            $newSlot = $currentSlot;
            $requestedSlotId = (int) ($formData['slot_id'] ?: 0);
            $currentSlotId = $currentSlot ? $currentSlot->getId() : 0;
            $slotChangeRequested = $requestedSlotId > 0 && $requestedSlotId !== $currentSlotId;

            if ($slotChangeRequested && !$canEditSlot) {
                $this->addFlash('error', 'Seul l\'étudiant peut modifier le créneau.');
                $formData['slot_id'] = $currentSlotId;
            } elseif ($canEditSlot && $slotChangeRequested) {
                $candidate = $this->entityManager->getRepository(AvailabilitySlot::class)->find($requestedSlotId);
                if (!$candidate) {
                    $errors['slot_id'] = 'Créneau introuvable.';
                } elseif ($candidate->isBooked()) {
                    $errors['slot_id'] = 'Ce créneau est déjà réservé.';
                } elseif ($rdv->getProfessor() && $candidate->getProfessor() && $rdv->getProfessor()->getId() !== $candidate->getProfessor()->getId()) {
                    $errors['slot_id'] = 'Le créneau sélectionné ne correspond pas au professeur du rendez-vous.';
                } else {
                    $newSlot = $candidate;
                }
            }

            if (!$canEditSlot && $rdv->getStatut() !== RendezVous::STATUS_EN_ATTENTE) {
                $this->addFlash('error', 'Le créneau ne peut plus être modifié après confirmation.');
            }

            if (empty($errors)) {
                $oldStatut = $rdv->getStatut();
                if ($canEditSlot && $currentSlot && $newSlot && $newSlot->getId() !== $currentSlot->getId()) {
                    $currentSlot->setIsBooked(false);
                }
                if ($canEditSlot && $newSlot && (!$currentSlot || $newSlot->getId() !== $currentSlot->getId())) {
                    $newSlot->setIsBooked(true);
                    $rdv->setSlot($newSlot);
                }
                $rdv->setStatut($formData['statut']);

                if ($formData['statut'] === RendezVous::STATUS_REJETE && $oldStatut !== RendezVous::STATUS_REJETE) {
                    $rdv->getSlot()?->setIsBooked(false);
                }
                if ($oldStatut === RendezVous::STATUS_REJETE && $formData['statut'] !== RendezVous::STATUS_REJETE) {
                    $rdv->getSlot()?->setIsBooked(true);
                }

                $this->entityManager->flush();

                $this->addFlash('success', 'Rendez-vous mis à jour avec succès.');
                return $this->redirectToRoute('admin_rendezvous_show', ['id' => $rdv->getId()]);
            }

            $this->addFlash('error', 'Veuillez corriger les erreurs.');
        }

        return $this->render('rendez_vous/edit.html.twig', [
            'rdv'             => $rdv,
            'form_data'       => $formData,
            'errors'          => $errors,
            'available_slots' => $availableSlots,
            'can_edit_slot'   => $canEditSlot,
        ]);
    }

    #[Route('/admin/rendezvous/{id}/delete', name: 'admin_rendezvous_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Request $request, RendezVous $rdv): Response
    {
        if ($this->isCsrfTokenValid('delete' . $rdv->getId(), $request->request->get('_token'))) {
            $slot = $rdv->getSlot();
            if ($slot) {
                $slot->setIsBooked(false);
            }
            $this->entityManager->remove($rdv);
            $this->entityManager->flush();
            $this->addFlash('success', 'Rendez-vous supprimé avec succès.');
        }

        return $this->redirectToRoute('admin_rendezvous_index');
    }
}
