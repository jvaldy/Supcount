<?php

namespace App\Controller;

use App\Entity\Group;
use App\Entity\User;
use App\Repository\GroupRepository;
use App\Repository\ExpenseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;
use App\Repository\UserRepository;

use Dompdf\Dompdf;
use Dompdf\Options;

use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\Response;

// use Symfony\Component\Mercure\PublisherInterface;
use Symfony\Component\Mercure\Update;
use App\Entity\Message;

use Symfony\Component\Mercure\Jwt\JwtTokenFactoryInterface;
use Symfony\Component\HttpFoundation\Cookie;

use Symfony\Component\Mercure\HubInterface;








class GroupController extends AbstractController
{
    #[Route('/api/groups', name: 'create_group', methods: ['POST'])]
    public function createGroup(Request $request, EntityManagerInterface $em, Security $security): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!isset($data['name'])) {
            return $this->json(['error' => 'Le nom du groupe est requis.'], 400);
        }

        $user = $security->getUser();

        $group = new Group();
        $group->setName($data['name']);
        $group->setCreatedBy($user);
        $group->addMember($user);

        $em->persist($group);
        $em->flush();

        return $this->json(['message' => 'Groupe créé avec succès.'], 201);
    }

    #[Route('/api/groups/{id}', name: 'delete_group', methods: ['DELETE'])]
    public function deleteGroup(Group $group, EntityManagerInterface $em, Security $security): JsonResponse
    {
        $user = $security->getUser();

        if ($group->getCreatedBy() !== $user) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        // 🔥 Avant de supprimer, on vide les membres pour nettoyer la table pivot
        foreach ($group->getMembers() as $member) {
            $group->removeMember($member);
        }

        $em->persist($group); // Très important sinon Doctrine ne voit pas les changements
        $em->flush();         // Appliquer la suppression dans la table group_members

        // Ensuite, on peut supprimer le groupe
        $em->remove($group);
        $em->flush();

        return $this->json(['message' => 'Groupe supprimé avec succès.'], 200);
    }


    #[Route('/api/my-groups', name: 'list_user_groups', methods: ['GET'])]
    public function listUserGroups(Security $security, GroupRepository $groupRepository): JsonResponse
    {
        $user = $security->getUser();
        $groups = $groupRepository->findByMember($user);

        $data = array_map(function ($group) {
            return [
                'id' => $group->getId(),
                'name' => $group->getName(),
                'createdAt' => $group->getCreatedAt()->format('Y-m-d H:i:s'),
            ];
        }, $groups);

        return $this->json($data, 200);
    }


    #[Route('/api/groups/{id}', name: 'get_group_details', methods: ['GET'])]
    public function getGroupDetails(Group $group, Security $security): JsonResponse
    {
        $user = $security->getUser();

        if (!$group->getMembers()->contains($user)) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        return $this->json([
            'id' => $group->getId(),
            'name' => $group->getName(),
            'createdAt' => $group->getCreatedAt()->format('Y-m-d H:i:s'),
            'createdBy' => [
                'id' => $group->getCreatedBy()->getId(),
                'email' => $group->getCreatedBy()->getEmail(),
                'username' => method_exists($group->getCreatedBy(), 'getUsername') ? $group->getCreatedBy()->getUsername() : '',
            ],
        ]);
    }






    #[Route('/api/groups/{id}/add-member', name: 'add_member_to_group', methods: ['POST'])]
    public function addMemberToGroup(
        Group $group, 
        Request $request, 
        UserRepository $userRepository, 
        EntityManagerInterface $em, 
        Security $security
    ): JsonResponse {
        $currentUser = $security->getUser();

        if ($group->getCreatedBy() !== $currentUser) {
            return $this->json(['error' => 'Seul le créateur du groupe peut ajouter des membres.'], 403);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['user_id'])) {
            $userToAdd = $userRepository->find($data['user_id']);
        } elseif (isset($data['email'])) {
            $userToAdd = $userRepository->findOneBy(['email' => $data['email']]);
        } else {
            return $this->json(['error' => 'Données manquantes.'], 400);
        }

        if (!$userToAdd) {
            return $this->json(['error' => 'Utilisateur non trouvé.'], 404);
        }

        if ($group->getMembers()->contains($userToAdd)) {
            return $this->json(['message' => 'Utilisateur déjà membre du groupe.'], 200);
        }

        $group->addMember($userToAdd);
        $em->persist($group);
        $em->flush();

        return $this->json(['message' => 'Utilisateur ajouté au groupe avec succès.'], 201);
    }


    #[Route('/api/groups/{id}/remove-member', name: 'remove_member_from_group', methods: ['POST'])]
    public function removeMemberFromGroup(
        Group $group,
        Request $request,
        EntityManagerInterface $em,
        Security $security,
        UserRepository $userRepo,
        ExpenseRepository $expenseRepo
    ): JsonResponse {
        $currentUser = $security->getUser();

        if ($group->getCreatedBy() !== $currentUser) {
            return $this->json(['error' => 'Seul le créateur peut retirer un membre.'], 403);
        }

        $data = json_decode($request->getContent(), true);
        if (!isset($data['user_id'])) {
            return $this->json(['error' => 'ID de l’utilisateur requis.'], 400);
        }

        $userToRemove = $userRepo->find($data['user_id']);
        if (!$userToRemove) {
            return $this->json(['error' => 'Utilisateur introuvable.'], 404);
        }

        if (!$group->getMembers()->contains($userToRemove)) {
            return $this->json(['error' => 'Cet utilisateur ne fait pas partie du groupe.'], 400);
        }

        // ✅ Supprimer de la liste des membres
        $group->removeMember($userToRemove);

        // ✅ Supprimer des concernedUsers dans toutes les dépenses du groupe
        $expenses = $expenseRepo->findBy(['group' => $group]);
        foreach ($expenses as $expense) {
            if ($expense->getConcernedUsers()->contains($userToRemove)) {
                $expense->removeConcernedUser($userToRemove);
            }
        }

        $em->flush();

        return $this->json(['message' => 'Membre supprimé du groupe et des dépenses associées.']);
    }



    #[Route('/api/groups/{id}/members', name: 'list_group_members', methods: ['GET'])]
    public function listGroupMembers(Group $group, Security $security): JsonResponse
    {
        $currentUser = $security->getUser();

        if (!$group->getMembers()->contains($currentUser)) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $creator = $group->getCreatedBy();

        $members = $group->getMembers();

        $data = array_map(function (User $user) use ($creator) {
            return [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'username' => method_exists($user, 'getUsername') ? $user->getUsername() : '',
                'role' => $user === $creator ? 'créateur' : 'membre'
            ];
        }, $members->toArray());

        return $this->json($data, 200);
    }

    #[Route('/api/groups/{id}/role', name: 'check_role_in_group', methods: ['GET'])]
    public function checkRoleInGroup(
        Group $group,
        Security $security
    ): JsonResponse
    {
        $currentUser = $security->getUser();

        if ($group->getCreatedBy() === $currentUser) {
            return $this->json(['role' => 'créateur'], 200);
        }

        if ($group->getMembers()->contains($currentUser)) {
            return $this->json(['role' => 'membre'], 200);
        }

        return $this->json(['role' => 'aucun'], 403);
    }






    #[Route('/api/groups/{id}/balances', name: 'calculate_group_balances', methods: ['GET'])]
    public function calculateGroupBalances(Group $group, Security $security, ExpenseRepository $expenseRepository): JsonResponse
    {
        $user = $security->getUser();
        if (!$group->getMembers()->contains($user)) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $members = $group->getMembers();
        $expenses = $expenseRepository->findBy(['group' => $group]);

        $balances = [];
        foreach ($members as $member) {
            $balances[$member->getId()] = 0;
        }

        foreach ($expenses as $expense) {
            $amount = (float) $expense->getAmount();
            $concernedUsers = $expense->getConcernedUsers();
            $count = count($concernedUsers);

            // 💥 Protection contre division par 0
            if ($count === 0) continue;

            $share = $amount / $count;

            foreach ($concernedUsers as $concernedUser) {
                $balances[$concernedUser->getId()] -= $share;
            }

            $balances[$expense->getPaidBy()->getId()] += $amount;
        }

        return $this->json($balances, 200);
    }



    #[Route('/api/groups/{id}/export/csv', name: 'export_group_csv', methods: ['GET'])]
    public function exportCsv(Group $group, Security $security, ExpenseRepository $repo): Response
    {
        $user = $security->getUser();
        if (!$group->getMembers()->contains($user)) {
            return new Response('Accès refusé', 403);
        }

        $expenses = $repo->findBy(['group' => $group]);

        // Buffer d'écriture
        $output = fopen('php://temp', 'r+');

        // UTF-8 BOM pour Excel
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // En-tête CSV
        fputcsv($output, ['Titre', 'Montant', 'Date', 'Catégorie', 'Payé par', 'Participants'], ';');

        foreach ($expenses as $e) {
            $participants = array_map(fn(User $u) => $u->getUsername(), $e->getConcernedUsers()->toArray());
            $row = [
                $e->getTitle(),
                number_format($e->getAmount(), 2, '.', ''),
                $e->getDate()->format('Y-m-d'),
                $e->getCategory(),
                $e->getPaidBy()->getUsername(),
                implode(', ', $participants)
            ];
            fputcsv($output, $row, ';');
        }

        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        return new Response($csvContent, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="depenses_' . $group->getName() . '.csv"',
        ]);
    }






   

    #[Route('/api/groups/{id}/export/pdf', name: 'export_group_pdf', methods: ['GET'])]
    public function exportGroupPdf(Group $group, Security $security, ExpenseRepository $repo): Response
    {
        $user = $security->getUser();
        if (!$group->getMembers()->contains($user)) {
            return new Response('Accès refusé', 403);
        }

        $expenses = $repo->findBy(['group' => $group]);

        $html = '
            <style>
            body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
            h1 { text-align: center; color: #333; }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 20px;
            }
            th {
                background-color: #f2f2f2;
                border: 1px solid #999;
                padding: 8px;
                text-align: left;
            }
            td {
                border: 1px solid #ccc;
                padding: 8px;
            }
            tr:nth-child(even) {
                background-color: #f9f9f9;
            }
            </style>

            <h1>Dépenses du groupe "' . htmlspecialchars($group->getName()) . '"</h1>

            <table>
            <thead>
                <tr>
                <th>Titre</th>
                <th>Montant</th>
                <th>Date</th>
                <th>Catégorie</th>
                <th>Payé par</th>
                <th>Participants</th>
                </tr>
            </thead>
            <tbody>';

            foreach ($expenses as $e) {
                $participants = array_map(fn(User $u) => $u->getUsername(), $e->getConcernedUsers()->toArray());
                $participantStr = implode(', ', $participants);

                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($e->getTitle()) . '</td>';
                $html .= '<td>' . number_format($e->getAmount(), 2, ',', ' ') . ' €</td>';
                $html .= '<td>' . $e->getDate()->format('d/m/Y') . '</td>';
                $html .= '<td>' . htmlspecialchars($e->getCategory()) . '</td>';
                $html .= '<td>' . htmlspecialchars($e->getPaidBy()->getUsername()) . '</td>';
                $html .= '<td>' . htmlspecialchars($participantStr) . '</td>';
                $html .= '</tr>';
            }

            $html .= '</tbody></table>';

            $date = new \DateTime('now', new \DateTimeZone('Europe/Paris'));

            $html .= '<p style="margin-top: 40px; font-size: 10px; color: #666; text-align: right;">
            Exporté le ' . $date->format('d/m/Y H:i') . ' sur SUPCOUNT
            </p>';



        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="depenses '. htmlspecialchars($group->getName()) .'.pdf"',
        ]);
    }


    #[Route('/api/groups/{group}/messages', name: 'send_group_message', methods: ['POST'])]
    public function sendGroupMessage(
        Group $group, 
        Request $request, 
        Security $security,
        EntityManagerInterface $em,
        HubInterface $hub
    ): JsonResponse {
        $user = $security->getUser();

        // Vérifier si l'utilisateur est membre du groupe
        if (!$group->getMembers()->contains($user)) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $data = json_decode($request->getContent(), true);
        $content = trim($data['content'] ?? '');

        if (!$content) {
            return $this->json(['error' => 'Message vide.'], 400);
        }

        $msg = new Message();
        $msg->setContent($content);
        $msg->setAuthor($user);
        $msg->setGroup($group);
        $msg->setCreatedAt(new \DateTimeImmutable()); // Assure que le champ est bien défini

        $em->persist($msg);
        $em->flush();

        // Publier via Mercure
        $update = new Update(
            sprintf("/groups/%d/messages", $group->getId()),
            json_encode([
                'id'        => $msg->getId(),
                'content'   => $msg->getContent(),
                'author'    => method_exists($user, 'getUsername')
                                ? $user->getUsername()
                                : (method_exists($user, 'getUserIdentifier')
                                    ? $user->getUserIdentifier()
                                    : 'utilisateur inconnu'),
                'createdAt' => $msg->getCreatedAt()->format('Y-m-d H:i:s')
            ])
        );

        $hub->publish($update);

        return $this->json(['message' => 'Envoyé.']);
    }

    #[Route('/api/groups/{group}/messages', name: 'get_group_messages', methods: ['GET'])]
    public function getGroupMessages(
        Group $group,
        Security $security,
        EntityManagerInterface $em
    ): JsonResponse {
        $user = $security->getUser();

        if (!$group->getMembers()->contains($user)) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $messages = $em->getRepository(Message::class)
            ->findBy(['group' => $group], ['createdAt' => 'ASC']);

        $data = [];
        foreach ($messages as $msg) {
            $data[] = [
                'id' => $msg->getId(),
                'author' => $msg->getAuthor()->getUsername(),
                'content' => $msg->getContent(),
                'createdAt' => $msg->getCreatedAt()->format('Y-m-d H:i:s'),
            ];
        }

        return $this->json($data);
    }

    #[Route('/groupe/{group}', name: 'group_show')]
    public function showGroup(Group $group, JwtTokenFactoryInterface $jwtFactory): Response
    {
        $jwt = $jwtFactory->create([
            'subscribe' => ['/groups/' . $group->getId() . '/messages']
        ]);

        $response = $this->render('group/show.html.twig', [
            'groupId' => $group->getId()
        ]);

        $response->headers->setCookie(
            Cookie::create('mercureAuthorization', $jwt, 0, '/', null, false, true, false, 'Strict')
        );

        return $response;
    }





}
