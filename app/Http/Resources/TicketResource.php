<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    public static $wrap = null;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $slaStatus = $this->slaStatus();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            'priority' => [
                'value' => $this->priority->value,
                'label' => $this->priority->label(),
            ],
            'sla_due_at' => $this->sla_due_at?->toIso8601String(),
            'sla_status' => $slaStatus !== null
                ? ['value' => $slaStatus->value, 'label' => $slaStatus->label()]
                : null,
            'organization' => $this->whenLoaded('organization', fn () => new OrganizationResource($this->organization)),
            'created_by' => $this->whenLoaded('createdBy', fn () => new UserResource($this->createdBy)),
            'assigned_to' => $this->whenLoaded('assignedTo', fn () => new UserResource($this->assignedTo)),
            'messages' => $this->whenLoaded('messages', fn () => TicketMessageResource::collection($this->messages)),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
