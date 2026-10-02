<?php

declare(strict_types=1);

namespace App\Http\Requests\QaBoard;

use App\Models\QaReply;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 質問掲示板の質問への回答の新規作成リクエスト。受講生/コーチが内容を入力する。
 */
class StoreReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', [QaReply::class, $this->route('thread')]) ?? false;
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
