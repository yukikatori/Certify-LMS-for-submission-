<?php

declare(strict_types=1);

namespace App\Http\Requests\Announcement;

use App\Models\Announcement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * お知らせ新規作成リクエスト。
 */
class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Announcement::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:5000'],
            'target_type' => ['required', 'string', Rule::in(['all', 'certification', 'user'])],
            'target_certification_id' => [
                'required_if:target_type,certification',
                'prohibited_unless:target_type,certification',
                'nullable',
                'ulid',
                'exists:certifications,id',
            ],
            'target_user_id' => [
                'required_if:target_type,user',
                'prohibited_unless:target_type,user',
                'nullable',
                'ulid',
                Rule::exists('users', 'id')->where('role', 'student'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'タイトル',
            'body' => '本文',
            'target_type' => '配信対象',
            'target_certification_id' => '対象資格',
            'target_user_id' => '対象受講生',
        ];
    }
}
