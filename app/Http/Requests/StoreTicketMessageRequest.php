<?php

namespace App\Http\Requests;

use App\Enums\TicketMessageType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketMessageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $type = $this->enum('type', TicketMessageType::class) ?? TicketMessageType::Public;

        return $type === TicketMessageType::Internal
            ? $this->user()->can('addInternalNote', $this->route('ticket'))
            : $this->user()->can('reply', $this->route('ticket'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:10000'],
            'type' => ['nullable', Rule::enum(TicketMessageType::class)],
        ];
    }
}
