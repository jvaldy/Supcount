<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class GroupController extends AbstractController
{
    #[Route('/group/{id}', name: 'app_group')]
    public function show(int $id): Response
    {
        return $this->render('group/index.html.twig', [
            'groupId' => $id
        ]);
    }
}