<?php

namespace Tests\Fixtures;

use Illuminate\Contracts\Auth\Authenticatable;

class User implements Authenticatable
{
    public function __construct(public int $id, public string $name = 'Test User')
    {
    }

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): int
    {
        return $this->id;
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getRememberToken(): null
    {
        return null;
    }

    public function setRememberToken($value)
    {
    }

    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }
}
