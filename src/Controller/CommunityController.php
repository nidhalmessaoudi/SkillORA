<?php

namespace App\Controller;

use App\DataFixtures\SampleData;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CommunityController extends AbstractController
{
    #[Route('/community', name: 'community_index')]
    public function index(Request $request): Response
    {
        $posts = SampleData::getCommunityPosts();
        $user = SampleData::getCurrentUser();
        $tab = $request->query->get('tab', 'hot');

        // Sort based on tab
        switch ($tab) {
            case 'new':
                break;
            case 'top':
                usort($posts, fn($a, $b) => $b['upvotes'] <=> $a['upvotes']);
                break;
            case 'hot':
            default:
                break;
        }

        return $this->render('pages/community/index.html.twig', [
            'posts' => $posts,
            'user' => $user,
            'current_tab' => $tab,
        ]);
    }

    #[Route('/community/create', name: 'community_create')]
    public function create(): Response
    {
        $user = SampleData::getCurrentUser();

        return $this->render('pages/community/create.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/community/{id}', name: 'community_post', requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $posts = SampleData::getCommunityPosts();
        $user = SampleData::getCurrentUser();
        $post = array_values(array_filter($posts, fn($p) => $p['id'] === $id))[0] ?? null;

        if (!$post) {
            throw $this->createNotFoundException('Post not found');
        }

        return $this->render('pages/community/show.html.twig', [
            'post' => $post,
            'user' => $user,
        ]);
    }
}
