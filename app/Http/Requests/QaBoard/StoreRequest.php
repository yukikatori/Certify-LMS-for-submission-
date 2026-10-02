<?php

declare(strict_types=1);

namespace App\Http\Requests\QaBoard;

use App\Enums\CertificationStatus;
use App\Models\QaThread;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 質問掲示板の質問新規作成リクエスト。受講生が資格名、タイトル、内容を入力する。
 */
class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', QaThread::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'certification_id' => [
                'required',
                'ulid',
                Rule::exists('certifications', 'id')->where('status', CertificationStatus::Published),
            ],
            'title' => ['required', 'string', 'max:200', 'not_regex:/\x{3000}/u'],
            'body' => ['required', 'string', 'max:5000', 'not_regex:/\x{3000}/u'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'certification_id' => '資格',
            'title' => 'タイトル',
            'body' => '本文',
        ];
    }
}
