<?php

namespace App\Models;

class User
{
    public ?int $id = null;
    public string $username = '';
    public string $email = '';
    public string $password_hash = '';
    public ?string $first_name = null;
    public ?string $last_name = null;
    public ?string $avatar = null;
    public bool $is_active = true;
    public ?string $last_login_at = null;
    public ?string $last_login_ip = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;
    public array $roles = [];
    public array $stores = [];
    public array $permissions = [];

    public static function fromArray(array $data): self
    {
        $user = new self();
        $user->id = isset($data['id']) ? (int)$data['id'] : null;
        $user->username = $data['username'] ?? '';
        $user->email = $data['email'] ?? '';
        $user->password_hash = $data['password_hash'] ?? '';
        $user->first_name = $data['first_name'] ?? null;
        $user->last_name = $data['last_name'] ?? null;
        $user->avatar = $data['avatar'] ?? null;
        $user->is_active = (bool)($data['is_active'] ?? true);
        $user->last_login_at = $data['last_login_at'] ?? null;
        $user->last_login_ip = $data['last_login_ip'] ?? null;
        $user->created_at = $data['created_at'] ?? null;
        $user->updated_at = $data['updated_at'] ?? null;
        $user->roles = $data['roles'] ?? [];
        $user->stores = $data['stores'] ?? [];
        $user->permissions = $data['permissions'] ?? [];
        return $user;
    }

    public function getFullName(): string
    {
        $name = trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
        return !empty($name) ? $name : $this->username;
    }

    public function getStatus(): string
    {
        return $this->is_active ? 'active' : 'inactive';
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->getFullName(),
            'avatar' => $this->avatar,
            'is_active' => $this->is_active,
            'status' => $this->getStatus(),
            'last_login_at' => $this->last_login_at,
            'roles' => $this->roles,
            'stores' => $this->stores,
            'permissions' => $this->permissions,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
