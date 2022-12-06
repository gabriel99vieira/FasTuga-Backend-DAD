<?php

namespace App\Http\Requests;

use App\Models\Types\UserType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        $rules = [
            'name' => 'required',
            'email' => ['required', 'email', Rule::unique('users', 'email')],
            'password' => 'required',
            'image' => 'imageable'
        ];

        if ($this->user('api') && $this->user('api')->isCustomer()) {
            $rules = array_merge($rules, [
                'type' => 'required|in:' . UserType::toRule()
            ]);
        }

        return $rules;
    }

    public function messages()
    {
        $messages = [
            'type.in' => "The selected user type is invalid. One is required: " . UserType::toString()
        ];

        $messages = array_merge($messages, (new StoreImageRequest())->messages());

        return $messages;
    }
}
