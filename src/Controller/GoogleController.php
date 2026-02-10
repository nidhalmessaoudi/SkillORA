<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class GoogleController extends AbstractController
{
    #[Route('/connect/google', name: 'connect_google_start')]
    public function connectAction(ClientRegistry $clientRegistry): Response
    {
        // Redirect to Google OAuth
        return $clientRegistry
            ->getClient('google')
            ->redirect([
                'email', 'profile' // Request access to email and profile info
            ], []);
    }

    #[Route('/connect/google/check', name: 'connect_google_check')]
    public function connectCheckAction(
        Request $request,
        ClientRegistry $clientRegistry,
        EntityManagerInterface $entityManager
    ): Response {
        // This will be handled by the GoogleAuthenticator
        // But we need this route to exist
        return $this->redirectToRoute('app_home');
    }
}
