<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class NotificationsController extends AbstractController
{
    public function __construct(
        private readonly NotificationRepository $notificationRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    #[Route('/notifications/widget', name: 'notifications_widget', methods: ['GET'])]
    public function headerWidget(): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return new Response('');
        }

        $unreadCount = $this->notificationRepository->countUnreadByUser($user);
        $latestUnread = $this->notificationRepository->findUnreadByUser($user, 5);

        return $this->render('components/_notifications_bell.html.twig', [
            'notifications_unread_count' => $unreadCount,
            'notifications_latest_unread' => $latestUnread,
        ]);
    }

    #[Route('/notifications', name: 'notifications_index', methods: ['GET'])]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $notifications = $this->notificationRepository->createQueryBuilder('n')
            ->andWhere('n.user = :user')
            ->setParameter('user', $user)
            ->orderBy('n.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('notifications/index.html.twig', [
            'notifications' => $notifications,
            'unread_count' => $this->notificationRepository->countUnreadByUser($user),
        ]);
    }

    #[Route('/notifications/{id}/read', name: 'notifications_read', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function read(Request $request, Notification $notification): RedirectResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$notification->getUser() || $notification->getUser()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Accès refusé.');
        }

        if (!$this->isCsrfTokenValid('notification_read_' . $notification->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('notifications_index');
        }

        if (!$notification->isRead()) {
            $notification->setIsRead(true);
            $this->entityManager->flush();
        }

        if ($notification->getLink()) {
            return $this->redirect($notification->getLink());
        }

        return $this->redirectToRoute('notifications_index');
    }

    #[Route('/notifications/read-all', name: 'notifications_read_all', methods: ['POST'])]
    public function readAll(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('notifications_read_all', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('notifications_index');
        }

        $this->notificationRepository->markAllAsReadForUser($user);
        $this->addFlash('success', 'Toutes les notifications ont été marquées comme lues.');

        return $this->redirectToRoute('notifications_index');
    }
}
