<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'username' => $this->username,
            'clinic' => $this->whenLoaded('clinic', fn () => [
                'id' => $this->clinic?->id,
                'name' => $this->clinic?->name,
            ]),
            'is_active' => (bool) $this->is_active,
            'is_super_admin' => (bool) $this->is_super_admin,
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')),
            'role_names' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')->toArray()),
            'two_factor_enabled' => $this->two_factor_secret !== null,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
