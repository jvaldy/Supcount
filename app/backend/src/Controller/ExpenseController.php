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
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\File\Exception\FileException;



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
        $user = $security->getUser();

        $title = $request->get('title');
        $amount = $request->get('amount');
        $date = $request->get('date');
        $category = $request->get('category');
        $groupId = $request->get('group_id');
        $concernedIds = json_decode($request->get('concerned_user_ids'), true);
        /** @var UploadedFile|null $receipt */
        $receipt = $request->files->get('receipt');

        if (!$title || !$amount || !$date || !$category || !$groupId || !$concernedIds) {
            return $this->json(['error' => 'Tous les champs sont requis.'], 400);
        }

        $group = $em->getRepository(Group::class)->find($groupId);
        if (!$group || !$group->getMembers()->contains($user)) {
            return $this->json(['error' => 'Accès au groupe refusé.'], 403);
        }

        $expense = new Expense();
        $expense->setTitle($title);
        $expense->setAmount($amount);
        $expense->setDate(new \DateTime($date));
        $expense->setCategory($category);
        $expense->setGroup($group);
        $expense->setPaidBy($user);

        if ($receipt) {
            $filename = uniqid() . '.' . $receipt->guessExtension();
            try {
                $receipt->move($this->getParameter('receipts_dir'), $filename);
                $expense->setReceipt($filename);
            } catch (FileException $e) {
                return $this->json(['error' => 'Erreur lors de l’upload.'], 500);
            }
        }

        foreach ($concernedIds as $id) {
            $concerned = $userRepo->find($id);
            if ($concerned && $group->getMembers()->contains($concerned)) {
                $expense->addConcernedUser($concerned);
            }
        }

        $em->persist($expense);
        $em->flush();

        return $this->json(['message' => 'Dépense ajoutée avec justificatif.'], 201);
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

    #[Route('/api/expenses/{id}/settlements', name: 'expense_settlements', methods: ['GET'])]
    public function getExpenseSettlements(Expense $expense, Security $security, UserRepository $userRepository): JsonResponse
    {
        $user = $security->getUser();
        $group = $expense->getGroup();

        if (!$group->getMembers()->contains($user)) {
            return $this->json(['error' => 'Accès refusé'], 403);
        }

        $amount = (float)$expense->getAmount();
        $payer = $expense->getPaidBy();
        $concernedUsers = $expense->getConcernedUsers()->toArray();

        if (empty($concernedUsers)) {
            return $this->json(['error' => 'Aucun utilisateur concerné par cette dépense.'], 400);
        }

        // Initialisation précise des soldes
        $balances = [];

        foreach ($concernedUsers as $userConcerned) {
            $balances[$userConcerned->getId()] = 0.0;
        }

        // Inclure automatiquement le payeur s'il n’est pas dans la liste des concernés
        if (!isset($balances[$payer->getId()])) {
            $balances[$payer->getId()] = 0.0;
        }

        $individualShare = round($amount / count($concernedUsers), 2);

        // Attribution des dettes à chaque utilisateur concerné
        foreach ($concernedUsers as $userConcerned) {
            $balances[$userConcerned->getId()] -= $individualShare;
        }

        // Le payeur récupère la totalité du montant dépensé
        $balances[$payer->getId()] += $amount;

        // Séparation claire des créditeurs et débiteurs
        $creditors = [];
        $debtors = [];

        foreach ($balances as $userId => $balance) {
            if ($balance > 0) {
                $creditors[$userId] = $balance;
            } elseif ($balance < 0) {
                $debtors[$userId] = abs($balance);
            }
        }

        // Calcul optimisé des remboursements
        $settlements = [];
        foreach ($debtors as $debtorId => $debtAmount) {
            foreach ($creditors as $creditorId => $creditAmount) {
                if ($debtAmount === 0) break;

                $payment = min($debtAmount, $creditAmount);

                $settlements[] = [
                    'from' => [
                        'id' => $debtorId,
                        'username' => $userRepository->find($debtorId)?->getUsername() ?? "Utilisateur #$debtorId"
                    ],
                    'to' => [
                        'id' => $creditorId,
                        'username' => $userRepository->find($creditorId)?->getUsername() ?? "Utilisateur #$creditorId"
                    ],
                    'amount' => round($payment, 2),
                ];


                $debtors[$debtorId] -= $payment;
                $creditors[$creditorId] -= $payment;

                if ($creditors[$creditorId] === 0.0) {
                    unset($creditors[$creditorId]);
                }
            }
        }

        return $this->json([
            'expense' => [
                'id' => $expense->getId(),
                'title' => $expense->getTitle(),
                'amount' => $amount,
                'paid_by' => $payer->getUsername(),
            ],
            'settlements' => $settlements,
        ]);
    }



    #[Route('/api/expenses/{id}/participants', name: 'update_expense_participants', methods: ['PUT'])]
    public function updateConcernedUsers(
        Expense $expense,
        Request $request,
        EntityManagerInterface $em,
        Security $security,
        UserRepository $userRepository
    ): JsonResponse {
        $user = $security->getUser();
        $group = $expense->getGroup();

        if (!$group->getMembers()->contains($user)) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['concerned_user_ids']) || !is_array($data['concerned_user_ids'])) {
            return $this->json(['error' => 'Liste des utilisateurs requise.'], 400);
        }

        $expense->getConcernedUsers()->clear();

        foreach ($data['concerned_user_ids'] as $userId) {
            $participant = $userRepository->find($userId);
            if ($participant && $group->getMembers()->contains($participant)) {
                $expense->addConcernedUser($participant);
            }
        }

        $em->flush();

        return $this->json(['message' => 'Participants mis à jour avec succès.'], 200);
    }




    #[Route('/api/expenses/{id}/edit', name: 'edit_expense_details', methods: ['PUT'])]
    public function editExpenseDetails(
        Expense $expense,
        Request $request,
        Security $security,
        EntityManagerInterface $em
    ): JsonResponse {
        $user = $security->getUser();

        if ($expense->getPaidBy() !== $user && !$expense->getGroup()->getMembers()->contains($user)) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['title'])) {
            $expense->setTitle($data['title']);
        }

        if (isset($data['amount'])) {
            $expense->setAmount($data['amount']);
        }

        if (isset($data['category'])) {
            $expense->setCategory($data['category']);
        }

        $em->flush();

        return $this->json(['message' => 'Dépense mise à jour avec succès.']);
    }




}
