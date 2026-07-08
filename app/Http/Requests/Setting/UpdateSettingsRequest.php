<?php

namespace App\Http\Requests\Setting;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reminder_lead_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reminder_lead_minutes.required' => 'Vui lòng nhập số phút nhắc trước.',
            'reminder_lead_minutes.integer' => 'Số phút phải là số nguyên.',
            'reminder_lead_minutes.min' => 'Số phút không được âm.',
            'reminder_lead_minutes.max' => 'Số phút tối đa là 1440 (24 giờ).',
        ];
    }
}
