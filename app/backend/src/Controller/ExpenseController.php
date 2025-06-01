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
use App\Repository\ReimbursementRepository;



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
                'receipt' => $e->getReceipt(),
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


        $settlements = [];
        foreach ($debtors as $debtorId => $debtAmount) {
            foreach ($creditors as $creditorId => $creditAmount) {
                if ($debtAmount === 0) break;

                $payment = min($debtAmount, $creditAmount);

                $fromUser = $userRepository->find($debtorId);
                $toUser = $userRepository->find($creditorId);

                $settlements[] = [
                    'id' => uniqid(), // ou un ID réel si tu les enregistres
                    'from' => [
                        'id' => $debtorId,
                        'username' => $fromUser?->getUsername(),
                    ],
                    'to' => [
                        'id' => $creditorId,
                        'username' => $toUser?->getUsername(),
                        // 💡 Inclure le RIB du créancier SEULEMENT si le débiteur est l'utilisateur connecté
                        'rib' => ($debtorId === $user->getId()) ? $toUser?->getRib() : null
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
                'paid_by_rib' => $payer->getRib(),
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

    #[Route('/api/statistics', name: 'user_statistics', methods: ['GET'])]
    public function getUserStatistics(
        ExpenseRepository $repo,
        Security $security
    ): JsonResponse {
        $user = $security->getUser();

        $expenses = $repo->createQueryBuilder('e')
            ->leftJoin('e.group', 'g')
            ->addSelect('g')
            ->where(':user MEMBER OF e.concernedUsers OR e.paidBy = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();

        $total = 0;
        $byCategory = [];
        $byMonth = [];
        $byGroup = [];

        foreach ($expenses as $e) {
            $amount = (float) $e->getAmount();
            $isPayer = $e->getPaidBy()->getId() === $user->getId();

            $userShare = 0;

            if ($isPayer) {
                $userShare = $amount;
            } elseif ($e->getConcernedUsers()->contains($user)) {
                $nbParticipants = count($e->getConcernedUsers());
                if ($nbParticipants > 0) {
                    $userShare = $amount / $nbParticipants;
                }
            }

            if ($userShare === 0) continue;

            $total += $userShare;

            // Par catégorie
            $cat = $e->getCategory();
            $byCategory[$cat] = ($byCategory[$cat] ?? 0) + $userShare;

            // Par mois
            $month = $e->getDate()->format('Y-m');
            $byMonth[$month] = ($byMonth[$month] ?? 0) + $userShare;

            // Par groupe
            $groupName = $e->getGroup()->getName();
            $byGroup[$groupName] = ($byGroup[$groupName] ?? 0) + $userShare;
        }


        return $this->json([
            'total' => $total,
            'byCategory' => $byCategory,
            'byMonth' => $byMonth,
            'byGroup' => $byGroup
        ]);
    }









    #[Route('/api/expenses/{id}/confirm-settlements', name: 'confirm_settlements', methods: ['POST'])]
    public function confirmSettlements(
        Expense $expense,
        Security $security,
        EntityManagerInterface $em,
        UserRepository $userRepo
    ): JsonResponse {
        $user = $security->getUser();
        if ($expense->getPaidBy() !== $user) {
            return $this->json(['error' => 'Seul le créateur peut valider les remboursements.'], 403);
        }

        // 💡 Tu réutilises la logique d'équilibrage déjà faite
        $amount = $expense->getAmount();
        $payer = $expense->getPaidBy();
        $concernedUsers = $expense->getConcernedUsers();
        $shares = $expense->getCustomShares();

        $balances = [];
        foreach ($concernedUsers as $u) $balances[$u->getId()] = 0;
        if (!isset($balances[$payer->getId()])) $balances[$payer->getId()] = 0;

        if ($shares) {
            foreach ($shares as $s) {
                $balances[$s['user_id']] -= $s['amount'];
            }
        } else {
            $share = $amount / count($concernedUsers);
            foreach ($concernedUsers as $u) $balances[$u->getId()] -= $share;
        }

        $balances[$payer->getId()] += $amount;

        $creditors = [];
        $debtors = [];

        foreach ($balances as $uid => $bal) {
            if ($bal > 0) $creditors[$uid] = $bal;
            elseif ($bal < 0) $debtors[$uid] = abs($bal);
        }

        foreach ($debtors as $from => $debt) {
            foreach ($creditors as $to => $credit) {
                if ($debt <= 0) break;

                $pay = min($debt, $credit);
                $debtors[$from] -= $pay;
                $creditors[$to] -= $pay;

                $reimb = new \App\Entity\Reimbursement();
                $reimb->setExpense($expense);
                $reimb->setFromUser($userRepo->find($from));
                $reimb->setToUser($userRepo->find($to));
                $reimb->setAmount($pay);

                $em->persist($reimb);
            }
        }

        $em->flush();
        return $this->json(['message' => 'Remboursements enregistrés.']);
    }



    // ---------- REMBOURSEMENTS ----------
    #[Route('/api/reimbursements/{id}/mark-paid', name: 'mark_reimbursement_paid', methods: ['POST'])]
    public function markReimbursementPaid(
        \App\Entity\Reimbursement $reimb,
        Security $security,
        EntityManagerInterface $em
    ): JsonResponse {
        $user = $security->getUser();
        if ($reimb->getFromUser() !== $user) {
            return $this->json(['error' => 'Non autorisé.'], 403);
        }

        $reimb->setIsPaid(true);
        $em->flush();

        return $this->json(['message' => 'Remboursement confirmé.']);
    }


    #[Route('/api/expenses/{expenseId}/validate/{debtorId}', name: 'validate_reimbursement', methods: ['POST'])]
    public function validateReimbursement(
        int $expenseId,
        int $debtorId,
        ExpenseRepository $expenseRepo,
        UserRepository $userRepo,
        ReimbursementRepository $reimbursementRepo,
        EntityManagerInterface $em,
        Security $security
    ): JsonResponse {
        $expense = $expenseRepo->find($expenseId);
        $payeur = $security->getUser();

        if (!$expense || !$payeur) {
            return new JsonResponse(['error' => 'Expense or user not found'], 404);
        }

        $debtor = $userRepo->find($debtorId);
        if (!$debtor) {
            return new JsonResponse(['error' => 'Debtor not found'], 404);
        }

        $reimbursement = $reimbursementRepo->findOneBy([
            'expense' => $expense,
            'from' => $debtor,
            'to' => $payeur
        ]);

        if (!$reimbursement) {
            return new JsonResponse(['error' => 'Reimbursement not found'], 404);
        }

        if ($reimbursement->isValidated()) {
            return new JsonResponse(['message' => 'Already validated']);
        }

        $reimbursement->setValidated(true);
        $em->flush();

        return new JsonResponse(['message' => 'Remboursement validé']);
    }




}