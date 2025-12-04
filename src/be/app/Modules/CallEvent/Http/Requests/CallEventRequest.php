<?php

declare(strict_types=1);

namespace App\Modules\CallEvent\Http\Requests;

use App\Modules\CallEvent\Enums\CallEventTypeEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CallEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'call_id' => ['required', 'string', 'max:255'],
            'caller_number' => ['required', 'string', 'regex:/^\+?[0-9]{10,15}$/'],
            'callee_number' => ['required', 'string', 'regex:/^\+?[0-9]{10,15}$/'],
            'event_type' => ['required', 'string', Rule::in(CallEventTypeEnum::values())],
            'timestamp' => ['required', 'date', 'date_format:Y-m-d H:i:s'],
            'duration' => [
                Rule::requiredIf(fn () => $this->input('event_type') === CallEventTypeEnum::CALL_ENDED->value),
                'nullable',
                'integer',
                'min:0',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'call_id.required' => 'Call ID is required.',
            'caller_number.required' => 'Caller number is required.',
            'caller_number.regex' => 'Caller number must be a valid phone number.',
            'callee_number.required' => 'Callee number is required.',
            'callee_number.regex' => 'Callee number must be a valid phone number.',
            'event_type.required' => 'Event type is required.',
            'event_type.in' => 'Invalid event type. Allowed values: '.implode(', ', CallEventTypeEnum::values()),
            'timestamp.required' => 'Timestamp is required.',
            'timestamp.date_format' => 'Timestamp must be in Y-m-d H:i:s format.',
            'duration.required_if' => 'Duration is required when event type is call_ended.',
            'duration.min' => 'Duration must be at least 0.',
        ];
    }
}