<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;

class ProfileController extends AbstractController
{
    #[Route('/api/profile', name: 'api_profile', methods: ['GET'])]
    public function profile(UserInterface $user): JsonResponse
    {
        return new JsonResponse([
            'email' => $user->getUserIdentifier(),
            'username' => method_exists($user, 'getUsername') ? $user->getUsername() : '',
            'roles' => $user->getRoles(),
        ]);
    }
}
