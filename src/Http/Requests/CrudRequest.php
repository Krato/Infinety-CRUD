<?php

namespace Infinety\CRUD\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CrudRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        // only allow creates if the user is logged in
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            // 'name' => 'required|min:3|max:255'
        ];
    }
}
