<?php

namespace App\Http\Requests\Appointment;

use Illuminate\Foundation\Http\FormRequest;

class StoreAppointmentRequest extends FormRequest
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
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'scheduled_at' => ['required', 'date'],
            'note' => ['nullable', 'string'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer_id.required' => 'Thiếu khách hàng.',
            'customer_id.exists' => 'Khách hàng không tồn tại.',
            'scheduled_at.required' => 'Vui lòng chọn thời gian gọi lại.',
            'scheduled_at.date' => 'Thời gian không hợp lệ.',
        ];
    }
}
