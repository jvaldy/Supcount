<?php

namespace App\Controller;

use App\Entity\Expense;
use App\Entity\Group;
use App\Entity\User;
use App\Repository\ExpenseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;

class ExpenseController extends AbstractController
{
    #[Route('/api/expenses', name: 'create_expense', methods: ['POST'])]
    public function createExpense(Request $request, EntityManagerInterface $em, Security $security): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $requiredFields = ['title', 'amount', 'date', 'category', 'group_id', 'concerned_user_ids'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                return $this->json(['error' => "Le champ '$field' est requis."], 400);
            }
        }

        $user = $security->getUser();
        $group = $em->getRepository(Group::class)->find($data['group_id']);
        if (!$group || !$group->getMembers()->contains($user)) {
            return $this->json(['error' => 'Accès refusé ou groupe introuvable.'], 403);
        }

        $expense = new Expense();
        $expense->setTitle($data['title']);
        $expense->setAmount($data['amount']);
        $expense->setDate(new \DateTime($data['date']));
        $expense->setCategory($data['category']);
        $expense->setGroup($group);
        $expense->setPaidBy($user);

        if (isset($data['receipt'])) {
            $expense->setReceipt($data['receipt']);
        }

        foreach ($data['concerned_user_ids'] as $userId) {
            $concernedUser = $em->getRepository(User::class)->find($userId);
            if ($concernedUser && $group->getMembers()->contains($concernedUser)) {
                $expense->addConcernedUser($concernedUser);
            }
        }

        $em->persist($expense);
        $em->flush();

        return $this->json(['message' => 'Dépense créée avec succès.'], 201);
    }

    #[Route('/api/expenses/{id}', name: 'delete_expense', methods: ['DELETE'])]
    public function deleteExpense(Expense $expense, EntityManagerInterface $em, Security $security): JsonResponse
    {
        $user = $security->getUser();
        if ($expense->getPaidBy() !== $user) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $em->remove($expense);
        $em->flush();

        return $this->json(['message' => 'Dépense supprimée avec succès.'], 200);
    }

    #[Route('/api/groups/{id}/expenses', name: 'list_group_expenses', methods: ['GET'])]
    public function listGroupExpenses(Group $group, Security $security, ExpenseRepository $expenseRepository): JsonResponse
    {
        $user = $security->getUser();
        if (!$group->getMembers()->contains($user)) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $expenses = $expenseRepository->findBy(['group' => $group]);

        $data = array_map(function ($expense) {
            return [
                'id' => $expense->getId(),
                'title' => $expense->getTitle(),
                'amount' => $expense->getAmount(),
                'date' => $expense->getDate()->format('Y-m-d'),
                'category' => $expense->getCategory(),
                'paidBy' => $expense->getPaidBy()->getId(),
                'concernedUsers' => array_map(fn($user) => $user->getId(), $expense->getConcernedUsers()->toArray()),
            ];
        }, $expenses);

        return $this->json($data, 200);
    }
}
