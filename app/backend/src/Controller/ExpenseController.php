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

use App\Repository\UserRepository;


class ExpenseController extends AbstractController
{
    #[Route('/api/expenses', name: 'create_expense', methods: ['POST'])]
    public function createExpense(
        Request $request,
        EntityManagerInterface $em,
        UserRepository $userRepo,
        Security $security
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $user = $security->getUser();

        foreach (['title', 'amount', 'date', 'category', 'group_id', 'concerned_user_ids'] as $field) {
            if (!isset($data[$field])) {
                return $this->json(['error' => "Champ manquant : $field"], 400);
            }
        }

        $group = $em->getRepository(Group::class)->find($data['group_id']);
        if (!$group || !$group->getMembers()->contains($user)) {
            return $this->json(['error' => 'Accès au groupe refusé.'], 403);
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

        foreach ($data['concerned_user_ids'] as $id) {
            $concerned = $userRepo->find($id);
            if ($concerned && $group->getMembers()->contains($concerned)) {
                $expense->addConcernedUser($concerned);
            }
        }

        $em->persist($expense);
        $em->flush();

        return $this->json(['message' => 'Dépense ajoutée.'], 201);
    }

    #[Route('/api/groups/{id}/expenses', name: 'list_group_expenses', methods: ['GET'])]
    public function listGroupExpenses(Group $group, Security $security, ExpenseRepository $repo): JsonResponse
    {
        $user = $security->getUser();
        if (!$group->getMembers()->contains($user)) {
            return $this->json(['error' => 'Accès refusé'], 403);
        }

        $expenses = $repo->findBy(['group' => $group]);

        $data = array_map(function (Expense $e) {
            return [
                'id' => $e->getId(),
                'title' => $e->getTitle(),
                'amount' => $e->getAmount(),
                'date' => $e->getDate()->format('Y-m-d'),
                'category' => $e->getCategory(),
                'paidBy' => $e->getPaidBy()->getUsername(),
                'concernedUsers' => array_map(fn(User $u) => $u->getUsername(), $e->getConcernedUsers()->toArray()),
            ];
        }, $expenses);

        return $this->json($data);
    }

    #[Route('/api/expenses/{id}', name: 'get_expense', methods: ['GET'])]
    public function getExpense(Expense $expense, Security $security): JsonResponse
    {
        $user = $security->getUser();
        if (!$expense->getGroup()->getMembers()->contains($user)) {
            return $this->json(['error' => 'Accès refusé'], 403);
        }

        return $this->json([
            'id' => $expense->getId(),
            'title' => $expense->getTitle(),
            'amount' => $expense->getAmount(),
            'date' => $expense->getDate()->format('Y-m-d'),
            'category' => $expense->getCategory(),
            'receipt' => $expense->getReceipt(),
            'paidBy' => $expense->getPaidBy()->getUsername(),
            'concernedUsers' => array_map(fn(User $u) => $u->getUsername(), $expense->getConcernedUsers()->toArray()),
        ]);
    }

    #[Route('/api/expenses/{id}', name: 'delete_expense', methods: ['DELETE'])]
    public function deleteExpense(Expense $expense, EntityManagerInterface $em, Security $security): JsonResponse
    {
        $user = $security->getUser();
        if ($expense->getPaidBy() !== $user) {
            return $this->json(['error' => 'Seul le créateur de la dépense peut la supprimer.'], 403);
        }

        $em->remove($expense);
        $em->flush();

        return $this->json(['message' => 'Dépense supprimée.']);
    }

    #[Route('/api/expenses/{id}', name: 'update_expense', methods: ['PUT'])]
    public function updateExpense(
        Expense $expense,
        Request $request,
        EntityManagerInterface $em,
        Security $security,
        UserRepository $userRepo
    ): JsonResponse {
        $user = $security->getUser();
        if ($expense->getPaidBy() !== $user) {
            return $this->json(['error' => 'Seul le créateur peut modifier la dépense.'], 403);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['title'])) $expense->setTitle($data['title']);
        if (isset($data['amount'])) $expense->setAmount($data['amount']);
        if (isset($data['date'])) $expense->setDate(new \DateTime($data['date']));
        if (isset($data['category'])) $expense->setCategory($data['category']);
        if (isset($data['receipt'])) $expense->setReceipt($data['receipt']);

        if (isset($data['concerned_user_ids'])) {
            $expense->getConcernedUsers()->clear();
            foreach ($data['concerned_user_ids'] as $id) {
                $u = $userRepo->find($id);
                if ($u && $expense->getGroup()->getMembers()->contains($u)) {
                    $expense->addConcernedUser($u);
                }
            }
        }

        $em->persist($expense);
        $em->flush();

        return $this->json(['message' => 'Dépense mise à jour.']);
    }
}
