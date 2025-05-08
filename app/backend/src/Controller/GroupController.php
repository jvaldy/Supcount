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
    ): JsonResponse
    {
        $currentUser = $security->getUser();

        if ($group->getCreatedBy() !== $currentUser) {
            return $this->json(['error' => 'Seul le créateur du groupe peut ajouter des membres.'], 403);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['user_id'])) {
            return $this->json(['error' => 'ID de l\'utilisateur requis.'], 400);
        }

        $userToAdd = $userRepository->find($data['user_id']);

        if (!$userToAdd) {
            return $this->json(['error' => 'Utilisateur non trouvé.'], 404);
        }

        if ($group->getMembers()->contains($userToAdd)) {
            return $this->json(['message' => 'Utilisateur déjà membre du groupe.'], 200);
        }

        $group->addMember($userToAdd);
        $em->persist($group);
        $em->flush();

        return $this->json(['message' => 'Utilisateur ajouté avec succès au groupe.'], 201);
    }

    #[Route('/api/groups/{id}/remove-member', name: 'remove_member_from_group', methods: ['POST'])]
    public function removeMemberFromGroup(
        Group $group,
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $em,
        Security $security
    ): JsonResponse
    {
        $currentUser = $security->getUser();

        if ($group->getCreatedBy() !== $currentUser) {
            return $this->json(['error' => 'Seul le créateur peut retirer des membres.'], 403);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['user_id'])) {
            return $this->json(['error' => 'ID de l\'utilisateur requis.'], 400);
        }

        $userToRemove = $userRepository->find($data['user_id']);

        if (!$userToRemove || !$group->getMembers()->contains($userToRemove)) {
            return $this->json(['error' => 'Utilisateur non trouvé ou pas membre.'], 404);
        }

        $group->removeMember($userToRemove);
        $em->persist($group);
        $em->flush();

        return $this->json(['message' => 'Utilisateur retiré du groupe.'], 200);
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

}
