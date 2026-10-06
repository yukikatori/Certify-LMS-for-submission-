<?php

declare(strict_types=1);

namespace App\Http\Requests\AiChat;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Gemini AI チャットボットの会話タイトル更新リクエスト。
 */
class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('conversation')) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'タイトル',
        ];
    }
}
