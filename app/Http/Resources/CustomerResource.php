<?php

namespace App\Http\Resources;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Customer */
class CustomerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'company_name' => $this->company_name,
            'phone' => $this->phone,
            'email' => $this->email,
            'contact_name' => $this->contact_name,
            'address' => $this->address,
            'lead_source' => $this->lead_source
                ? ['value' => $this->lead_source->value, 'label' => $this->lead_source->label()]
                : null,
            'initial_note' => $this->initial_note,
            'marketing_note' => $this->marketing_note,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            'lost_reason' => $this->lost_reason
                ? ['value' => $this->lost_reason->value, 'label' => $this->lost_reason->label()]
                : null,
            'source' => [
                'value' => $this->source->value,
                'label' => $this->source->label(),
            ],
            'assigned_at' => $this->assigned_at,
            'next_appointment_at' => $this->next_appointment_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'services' => ServiceResource::collection($this->whenLoaded('services')),
            'creator' => new UserBriefResource($this->whenLoaded('creator')),
            'assignee' => new UserBriefResource($this->whenLoaded('assignee')),
            'appointments' => AppointmentResource::collection($this->whenLoaded('appointments')),
            'activities' => CustomerActivityResource::collection($this->whenLoaded('activities')),
        ];
    }
}
