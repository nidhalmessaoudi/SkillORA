<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AuthController extends AbstractController
{
    #[Route('/login', name: 'auth_login')]
    public function login(): Response
    {
        return $this->render('pages/auth/login.html.twig');
    }

    #[Route('/register', name: 'auth_register')]
    public function register(): Response
    {
        return $this->render('pages/auth/register.html.twig');
    }

    #[Route('/forgot-password', name: 'auth_forgot_password')]
    public function forgotPassword(): Response
    {
        return $this->render('pages/auth/forgot-password.html.twig');
    }

    #[Route('/verify-email', name: 'auth_verify_email')]
    public function verifyEmail(): Response
    {
        return $this->render('pages/auth/verify-email.html.twig');
    }

    #[Route('/onboarding', name: 'auth_onboarding')]
    public function onboarding(): Response
    {
        return $this->render('pages/auth/onboarding.html.twig');
    }
}
