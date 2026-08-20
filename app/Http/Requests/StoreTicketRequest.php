<?php

namespace App\Http\Requests;

use App\Enums\TicketPriority;
use App\Enums\UserRole;
use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return $user->can('create', Ticket::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'priority' => ['required', Rule::enum(TicketPriority::class)],
            'organization_id' => [
                Rule::when($this->user()?->role === UserRole::Agent, ['required', 'exists:organizations,id']),
            ],
        ];
    }
}
