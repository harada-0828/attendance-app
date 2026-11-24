<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class IndexAttendanceRecordRequest extends FormRequest
{
    /**
     * リクエストの認可を判定する。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * バリデーションルールを定義する。
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'month' => ['nullable', 'date_format:Y-m', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * バリデーションエラーメッセージを定義する。
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_id.integer' => 'ユーザーIDは整数で指定してください。',
            'user_id.exists' => '指定されたユーザーが存在しません。',
            'date.date_format' => '日付はYYYY-MM-DD形式で指定してください。',
            'month.date_format' => '月はYYYY-MM形式で指定してください。',
            'month.regex' => '月はYYYY-MM形式（01〜12）で指定してください。',
            'page.integer' => 'ページ番号は整数で指定してください。',
            'page.min' => 'ページ番号は1以上で指定してください。',
            'per_page.integer' => '取得件数は整数で指定してください。',
            'per_page.min' => '取得件数は1以上で指定してください。',
            'per_page.max' => '取得件数は100以下で指定してください。',
        ];
    }
}
