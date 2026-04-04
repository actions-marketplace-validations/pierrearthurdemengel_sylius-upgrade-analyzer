<?php

declare(strict_types=1);

namespace App\Entity\User;

use Sylius\Component\Core\Model\AdminUser as BaseAdminUser;

class AdminUser extends BaseAdminUser implements \Serializable
{
    private bool $locked = false;
    private ?\DateTimeInterface $expiresAt = null;
    private ?\DateTimeInterface $credentialsExpireAt = null;

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

    public function getCredentialsExpireAt(): ?\DateTimeInterface
    {
        return $this->credentialsExpireAt;
    }

    public function setCredentialsExpireAt(?\DateTimeInterface $credentialsExpireAt): void
    {
        $this->credentialsExpireAt = $credentialsExpireAt;
    }

    public function isCredentialsExpired(): bool
    {
        return $this->credentialsExpireAt !== null && $this->credentialsExpireAt < new \DateTime();
    }

    public function getSalt(): ?string
    {
        return null;
    }

    public function serialize(): string
    {
        return serialize([$this->id, $this->username, $this->locked]);
    }

    public function unserialize(string $data): void
    {
        [$this->id, $this->username, $this->locked] = unserialize($data);
    }
}
