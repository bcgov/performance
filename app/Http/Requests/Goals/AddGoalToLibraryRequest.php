<?php

namespace App\Http\Requests\Goals;

use Illuminate\Foundation\Http\FormRequest;

class AddGoalToLibraryRequest extends FormRequest
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
     * @return array
     */
    public function rules()
    {
        return [
            'title' => 'required',
            'what' => 'required',
            'why' => 'required',
            'how' => 'required',
            'measure_of_success' => 'required',
            'goal_type_id' => 'required|exists:goal_types,id',
            'share_with' => 'required|array',
            'share_with.*' => 'exists:users,id'
        ];
    }

    public function messages() {
        return [
            'share_with.required' => 'please select at least one employee'
        ];
    }
}
