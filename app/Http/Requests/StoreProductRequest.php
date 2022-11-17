<?php

namespace App\Http\Requests;

use App\Models\Types\ProductType;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        // TODO autorização
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
            'name' => 'required|unique:App\Models\Product,name',
            'type' => 'required|in:' . ProductType::toRule(),
            'description' => 'required',
            'photo_url' => 'required|file|image',
            'price' => 'required|numeric'
        ];

        return $rules;
    }

    public function messages()
    {
        return [
            'type.in' => "The selected type is invalid. One is required: " . ProductType::toString()
        ];
    }
}
