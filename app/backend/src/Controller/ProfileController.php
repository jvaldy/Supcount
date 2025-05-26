<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Security;

class ProfileController extends AbstractController
{
    #[Route('/api/profile', name: 'api_profile', methods: ['GET'])]
    public function profile(Security $security): JsonResponse
    {
        /** @var User $user */
        $user = $security->getUser();

        return new JsonResponse([
            'id' => $user->getId(),
            'email' => $user->getUserIdentifier(),
            'username' => $user->getUsername(),
            'roles' => $user->getRoles(),
            'rib' => $user->getRib(), 
        ]);
    }

    #[Route('/api/user/upload-rib', name: 'upload_rib', methods: ['POST'])]
    public function uploadRib(
        Request $request,
        Security $security,
        EntityManagerInterface $em
    ): JsonResponse {
        /** @var User $user */
        $user = $security->getUser();
        $data = json_decode($request->getContent(), true);

        $rib = $data['rib'] ?? null;

        if (!$rib) {
            return $this->json(['error' => 'RIB requis'], 400);
        }

        $user->setRib($rib);
        $em->flush();

        return $this->json(['message' => 'RIB enregistré.']);
    }

}
