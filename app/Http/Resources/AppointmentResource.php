<?php

namespace App\Http\Resources;

use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Appointment */
class AppointmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'scheduled_at' => $this->scheduled_at,
            'note' => $this->note,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            'outcome' => $this->outcome,
            'reminder_sent_at' => $this->reminder_sent_at,
            'completed_at' => $this->completed_at,
            'user' => new UserBriefResource($this->whenLoaded('user')),
            'customer' => $this->whenLoaded('customer', fn (): array => [
                'id' => $this->customer->id,
                'code' => $this->customer->code,
                'company_name' => $this->customer->company_name,
                'phone' => $this->customer->phone,
                'status' => [
                    'value' => $this->customer->status->value,
                    'label' => $this->customer->status->label(),
                ],
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
