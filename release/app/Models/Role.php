<?php

namespace App\Models;

class Role
{
    public ?int $id = null;
    public string $name = '';
    public string $slug = '';
    public string $display_name = '';
    public ?string $description = null;
    public string $status = 'active';
    public ?string $created_at = null;
    public ?string $updated_at = null;
    public array $permissions = [];
    public int $users_count = 0;

    public static function fromArray(array $data): self
    {
        $role = new self();
        $role->id = isset($data['id']) ? (int)$data['id'] : null;
        $role->name = $data['name'] ?? '';
        $role->slug = $data['slug'] ?? strtolower(str_replace(' ', '_', $data['name'] ?? ''));
        $role->display_name = $data['display_name'] ?? ($data['name'] ?? '');
        $role->description = $data['description'] ?? null;
        $role->status = $data['status'] ?? 'active';
        $role->created_at = $data['created_at'] ?? null;
        $role->updated_at = $data['updated_at'] ?? null;
        $role->permissions = $data['permissions'] ?? [];
        $role->users_count = isset($data['users_count']) ? (int)$data['users_count'] : 0;
        return $role;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'display_name' => $this->display_name,
            'description' => $this->description,
            'status' => $this->status,
            'permissions' => $this->permissions,
            'permissions_count' => count($this->permissions),
            'users_count' => $this->users_count,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
