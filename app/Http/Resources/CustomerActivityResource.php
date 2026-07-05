<?php

namespace App\Http\Resources;

use App\Models\CustomerActivity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CustomerActivity */
class CustomerActivityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => [
                'value' => $this->type->value,
                'label' => $this->type->label(),
            ],
            'content' => $this->content,
            'user' => new UserBriefResource($this->whenLoaded('user')),
            'created_at' => $this->created_at,
        ];
    }
}
