<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/professor')]
#[IsGranted('ROLE_PROFESSOR')]
class ProfessorController extends AbstractController
{
    #[Route('', name: 'professor_dashboard')]
    public function dashboard(): Response
    {
        $user = $this->getUser();

        return $this->render('professor/dashboard.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/courses', name: 'professor_courses')]
    public function courses(): Response
    {
        return $this->render('professor/courses.html.twig');
    }

    #[Route('/students', name: 'professor_students')]
    public function students(): Response
    {
        return $this->render('professor/students.html.twig');
    }

    #[Route('/analytics', name: 'professor_analytics')]
    public function analytics(): Response
    {
        return $this->render('professor/analytics.html.twig');
    }

    #[Route('/profile', name: 'professor_profile')]
    public function profile(): Response
    {
        $user = $this->getUser();

        return $this->render('professor/profile.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/settings', name: 'professor_settings')]
    public function settings(): Response
    {
        return $this->render('professor/settings.html.twig');
    }
}
