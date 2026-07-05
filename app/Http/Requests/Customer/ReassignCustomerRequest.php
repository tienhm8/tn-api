<?php

namespace App\Http\Requests\Customer;

use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReassignCustomerRequest extends FormRequest
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
            'sale_id' => ['required', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $user = User::find($this->input('sale_id'));
                if ($user && ! $user->hasRole('sale')) {
                    $validator->errors()->add('sale_id', 'Người dùng được chọn không phải là sale.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sale_id.required' => 'Vui lòng chọn sale.',
            'sale_id.exists' => 'Sale không tồn tại hoặc đã bị vô hiệu hóa.',
        ];
    }
}
