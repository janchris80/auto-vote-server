<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
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
            'limitPower' => ['required_if:requestType,upvote,downvote', 'numeric'],
            'isPause' => ['required_if:requestType,upvote,downvote', 'boolean'],
            'requestType' => ['required', 'in:upvote,downvote,is_auto_claim_reward,is_enable'],

            'isEnable' => ['required_if:requestType,is_enable', 'boolean'],
            'isAutoClaimReward' => ['required_if:requestType,is_auto_claim_reward', 'boolean'],
        ];
    }
}
