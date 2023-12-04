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
            'limitPower' => ['required_if:type,upvote,downvote', 'numeric'],
            'isPause' => ['required_if:type,upvote,downvote', 'boolean'],
            'type' => ['required', 'in:upvote,downvote,is_auto_claim_reward,is_enable'],
            
            'isEnable' => ['required_if:type,is_enable', 'boolean'],
            'isAutoClaimReward' => ['required_if:type,is_auto_claim_reward', 'boolean'],
        ];
    }
}
