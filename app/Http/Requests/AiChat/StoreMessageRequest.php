<?php

declare(strict_types=1);

namespace App\Http\Requests\AiChat;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Gemini AI チャットボット回答の新規作成リクエスト。
 */
class StoreMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sendMessage', $this->route('conversation')) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'min:1', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'content' => 'メッセージ',
        ];
    }
}
