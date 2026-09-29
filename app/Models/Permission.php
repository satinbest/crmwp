<?php

namespace App\Models;

class Permission
{
    public ?int $id = null;
    public string $name = '';
    public string $group_name = '';
    public string $display_name = '';
    public ?string $description = null;
    public ?string $created_at = null;

    public static function fromArray(array $data): self
    {
        $perm = new self();
        $perm->id = isset($data['id']) ? (int)$data['id'] : null;
        $perm->name = $data['name'] ?? '';
        $perm->group_name = $data['group_name'] ?? '';
        $perm->display_name = $data['display_name'] ?? '';
        $perm->description = $data['description'] ?? null;
        $perm->created_at = $data['created_at'] ?? null;
        return $perm;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'group_name' => $this->group_name,
            'display_name' => $this->display_name,
            'description' => $this->description,
        ];
    }
}
