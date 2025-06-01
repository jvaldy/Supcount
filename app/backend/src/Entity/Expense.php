<?php

namespace App\Entity;

use App\Repository\ExpenseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\User;
use App\Entity\Group;
use Doctrine\DBAL\Types\Types;

#[ORM\Entity(repositoryClass: ExpenseRepository::class)]
class Expense
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $amount = null;

    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $date = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $paidBy = null;

    #[ORM\ManyToOne(targetEntity: Group::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Group $group = null;

    #[ORM\Column(length: 255)]
    private ?string $category = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $receipt = null;

    #[ORM\ManyToMany(targetEntity: User::class)]
    #[ORM\JoinTable(name: 'expense_concerned_users')]
    private Collection $concernedUsers;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $customShares = null;

    #[ORM\Column(type: 'boolean')]
    private bool $fullyReimbursed = false;


    #[ORM\OneToMany(mappedBy: 'expense', targetEntity: Reimbursement::class, cascade: ['persist', 'remove'])]
    private Collection $reimbursements;


    public function __construct()
    {
        $this->concernedUsers = new ArrayCollection();
        $this->reimbursements = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): self
    {
        $this->amount = $amount;
        return $this;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): self
    {
        $this->date = $date;
        return $this;
    }

    public function getPaidBy(): ?User
    {
        return $this->paidBy;
    }

    public function setPaidBy(User $paidBy): self
    {
        $this->paidBy = $paidBy;
        return $this;
    }

    public function getGroup(): ?Group
    {
        return $this->group;
    }

    public function setGroup(Group $group): self
    {
        $this->group = $group;
        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(string $category): self
    {
        $this->category = $category;
        return $this;
    }

    public function getReceipt(): ?string
    {
        return $this->receipt;
    }

    public function setReceipt(?string $receipt): self
    {
        $this->receipt = $receipt;
        return $this;
    }

    public function getConcernedUsers(): Collection
    {
        return $this->concernedUsers;
    }

    public function addConcernedUser(User $user): self
    {
        if (!$this->concernedUsers->contains($user)) {
            $this->concernedUsers->add($user);
        }
        return $this;
    }

    public function removeConcernedUser(User $user): self
    {
        $this->concernedUsers->removeElement($user);
        return $this;
    }

    public function getCustomShares(): ?array
    {
        return $this->customShares;
    }

    public function setCustomShares(?array $shares): self
    {
        $this->customShares = $shares;
        return $this;
    }

    public function isFullyReimbursed(): bool
    {
        return $this->fullyReimbursed;
    }

    public function setFullyReimbursed(bool $status): self
    {
        $this->fullyReimbursed = $status;
        return $this;
    }

    public function getReimbursements(): Collection
    {
        return $this->reimbursements;
    }

    // Optionnel : tu peux garder cette méthode si elle sert dans le code existant (mais elle ne dépend plus de validation manuelle)
    public function isReimbursedBy(int $userId): bool
    {
        foreach ($this->reimbursements as $reimb) {
            if ($reimb->getFromUser()->getId() === $userId && $reimb->isPaid()) {
                return true;
            }
        }
        return false;
    }






}
