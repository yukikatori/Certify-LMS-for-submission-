<?php

declare(strict_types=1);

namespace App\Http\Requests\QaBoard;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 質問掲示板の質問への回答の更新リクエスト。投稿者が内容を更新する。
 */
class UpdateReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('reply')) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000', 'not_regex:/\x{3000}/u'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'body' => '本文',
        ];
    }
}
