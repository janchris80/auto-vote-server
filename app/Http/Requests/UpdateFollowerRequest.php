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

    protected function prepareForValidation()
    {
        $this->merge([
            'votingType' => strtolower($this->input('votingType')),
        ]);
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
            'isEnable' => ['required', 'boolean'], // enable
            'votingType' => ['required', 'in:scaled,fixed'], // voting_type
            'type' => ['required', 'in:fanbase,curation,downvote'], // follower_type
            'weight' => ['required'], // weight
            'afterMin' => ['required'], // after_min
            'dailyLimit' => ['required'],
            'limitLeft' => ['required'],
        ];
    }
}
