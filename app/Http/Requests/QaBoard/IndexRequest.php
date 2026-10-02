<?php

declare(strict_types=1);

namespace App\Http\Requests\QaBoard;

use App\Models\QaThread;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 質問掲示板一覧の絞り込みリクエスト
 */
class IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', QaThread::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'keyword' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:resolved,unresolved'],
            'certification_id' => ['nullable', 'ulid', 'exists:certifications,id'],
        ];
    }
}
