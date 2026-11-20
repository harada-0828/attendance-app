<?php

namespace App\Http\Requests\Api\V1;

use App\Models\AttendanceRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAttendanceRecordRequest extends FormRequest
{
    /**
     * リクエストの認可を判定する。
     *
     * @return bool
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
        $routeParam = $this->route('attendanceRecord');

        $targetRecord = $routeParam instanceof AttendanceRecord
            ? $routeParam
            : (is_numeric($routeParam) ? AttendanceRecord::find($routeParam) : null);

        $ignoreId = $targetRecord?->id ?? (is_numeric($routeParam) ? (int) $routeParam : null);
        $userId = $this->input('user_id') ?? $targetRecord?->user_id ?? $this->user()?->id;

        return [
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'date' => [
                'sometimes',
                'required',
                'date_format:Y-m-d',
                Rule::unique('attendances')
                    ->where(function ($query) use ($userId) {
                        return $query->where('user_id', $userId);
                    })
                    ->ignore($ignoreId),
            ],
            'clock_in' => ['sometimes', 'required', 'date_format:H:i:s'],
            'clock_out' => ['nullable', 'date_format:H:i:s', 'after:clock_in'],
            'comment' => ['nullable', 'string', 'max:255'],
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
            'date.required' => '勤怠日は必須です。',
            'date.date_format' => '勤怠日は YYYY-MM-DD 形式で指定してください。',
            'date.unique' => 'この日付の勤怠は既に登録されています。',
            'clock_in.required' => '出勤時刻は必須です。',
            'clock_in.date_format' => '出勤時刻は HH:MM:SS 形式で指定してください。',
            'clock_out.date_format' => '退勤時刻は HH:MM:SS 形式で指定してください。',
            'clock_out.after' => '退勤時刻は出勤時刻より後の時刻を指定してください。',
            'comment.string' => '備考は文字列で入力してください。',
            'comment.max' => '備考は 255 文字以内で入力してください。',
        ];
    }
}