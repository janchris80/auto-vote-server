<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFollowerRequest extends FormRequest
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
        return [
            'id' => ['required'],
            'status' => ['required'], // enable
            'method' => ['required'], // voting_type
            'type' => ['required'], // follower_type
            'weight' => ['required'], // weight
            'waitTime' => ['required'], // after_min
            'dailyLeft' => ['required'],
            'limitLeft' => ['required'],
        ];
    }
}
