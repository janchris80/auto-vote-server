<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFollowerRequest extends FormRequest
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
            'userId' => ['required'],
            'trailerType' => ['required', 'in:curation,downvote,upvote_comment,upvote_post'],
            'weight' => ['required_if:type,upvote_comment,upvote_post', 'numeric'],
        ];
    }
}
