<?php

namespace App\Entity;

use App\Repository\ReimbursementRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: ReimbursementRepository::class)]
class Reimbursement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['expense:read', 'reimbursement:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['expense:read', 'reimbursement:read'])]
    private ?User $from = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['expense:read', 'reimbursement:read'])]
    private ?User $to = null;

    #[ORM\Column(type: 'float')]
    #[Groups(['expense:read', 'reimbursement:read'])]
    private float $amount;

    #[ORM\Column(type: 'boolean')]
    #[Groups(['expense:read', 'reimbursement:read'])]
    private bool $validated = false;

    #[ORM\ManyToOne(targetEntity: Expense::class, inversedBy: 'reimbursements')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Expense $expense = null;




    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $fromUser = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $toUser = null;





    #[ORM\Column(type: 'boolean')]
    private bool $isPaid = false;




    // === GETTERS & SETTERS ===

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFrom(): ?User
    {
        return $this->from;
    }

    public function setFrom(?User $from): self
    {
        $this->from = $from;
        return $this;
    }

    public function getTo(): ?User
    {
        return $this->to;
    }

    public function setTo(?User $to): self
    {
        $this->to = $to;
        return $this;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): self
    {
        $this->amount = $amount;
        return $this;
    }

    public function isValidated(): bool
    {
        return $this->validated;
    }

    public function setValidated(bool $validated): self
    {
        $this->validated = $validated;
        return $this;
    }

    public function getExpense(): ?Expense
    {
        return $this->expense;
    }

    public function setExpense(?Expense $expense): self
    {
        $this->expense = $expense;
        return $this;
    }




    public function getFromUser(): ?User
    {
        return $this->fromUser;
    }

    public function setFromUser(?User $user): self
    {
        $this->fromUser = $user;
        return $this;
    }

    public function getToUser(): ?User
    {
        return $this->toUser;
    }

    public function setToUser(?User $user): self
    {
        $this->toUser = $user;
        return $this;
    }



    public function getIsPaid(): bool
    {
        return $this->isPaid;
    }

    public function setIsPaid(bool $isPaid): self
    {
        $this->isPaid = $isPaid;
        return $this;
    }





}
