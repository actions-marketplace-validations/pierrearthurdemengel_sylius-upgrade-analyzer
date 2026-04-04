<?php

declare(strict_types=1);

namespace App\Entity\User;

use Sylius\Component\Core\Model\ShopUser as BaseShopUser;

class ShopUser extends BaseShopUser implements \Serializable
{
    private bool $locked = false;
    private ?\DateTimeInterface $expiresAt = null;

    public function isLocked(): bool
    {
        return $this->locked;
    }

    public function setLocked(bool $locked): void
    {
        $this->locked = $locked;
    }

    public function getExpiresAt(): ?\DateTimeInterface
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeInterface $expiresAt): void
    {
        $this->expiresAt = $expiresAt;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt !== null && $this->expiresAt < new \DateTime();
    }

    public function serialize(): string
    {
        return serialize([$this->id, $this->username]);
    }

    public function unserialize(string $data): void
    {
        [$this->id, $this->username] = unserialize($data);
    }
}
