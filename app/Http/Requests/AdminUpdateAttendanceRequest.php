<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class AdminUpdateAttendanceRequest extends FormRequest
{
    /**
     * リクエストの認可判定を行う。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * バリデーション前に出退勤・休憩の時刻文字列を「H:i」形式へ正規化する。
     */
    protected function prepareForValidation(): void
    {
        $formatTime = static function (mixed $time): mixed {
            if (! is_string($time) || $time === '' || $time === 'null') {
                return $time;
            }

            try {
                return Carbon::parse($time)->format('H:i');
            } catch (\Throwable) {
                return $time;
            }
        };

        $this->merge([
            'new_clock_in' => $formatTime($this->input('new_clock_in')),
            'new_clock_out' => $formatTime($this->input('new_clock_out')),
        ]);

        if ($this->has('new_break_in')) {
            $breakIns = collect($this->input('new_break_in', []))
                ->map($formatTime)
                ->all();

            $this->merge(['new_break_in' => $breakIns]);
        }

        if ($this->has('new_break_out')) {
            $breakOuts = collect($this->input('new_break_out', []))
                ->map($formatTime)
                ->all();

            $this->merge(['new_break_out' => $breakOuts]);
        }
    }

    /**
     * 入力値に対するバリデーションルールを取得する。
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'new_clock_in' => ['required', 'date_format:H:i'],
            'new_clock_out' => ['required', 'date_format:H:i', 'after:new_clock_in'],
            'new_break_in.*' => [
                'nullable',
                'date_format:H:i',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (empty($value) || ! is_string($value)) {
                        return;
                    }

                    $clockIn = $this->input('new_clock_in');
                    $clockOut = $this->input('new_clock_out');

                    if (! empty($clockIn) && $value < $clockIn) {
                        $fail('休憩時間が不適切な値です');

                        return;
                    }

                    if (! empty($clockOut) && $value >= $clockOut) {
                        $fail('休憩時間が不適切な値です');
                    }
                },
            ],
            'new_break_out.*' => [
                'nullable',
                'date_format:H:i',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (empty($value) || ! is_string($value)) {
                        return;
                    }

                    preg_match('/\.(\d+)$/', $attribute, $matches);
                    $index = $matches[1] ?? null;
                    $breakIn = $index !== null ? $this->input("new_break_in.{$index}") : null;
                    $clockOut = $this->input('new_clock_out');

                    if (! empty($breakIn) && is_string($breakIn) && $value <= $breakIn) {
                        $fail('休憩時間が不適切な値です');

                        return;
                    }

                    if (! empty($clockOut) && is_string($clockOut) && $value > $clockOut) {
                        $fail('休憩時間もしくは退勤時間が不適切な値です');

                        return;
                    }

                    $breakIns = $this->input('new_break_in', []);
                    $breakOuts = $this->input('new_break_out', []);

                    $intervals = collect($breakIns)
                        ->map(function (mixed $in, int|string $i) use ($breakOuts): ?array {
                            $out = $breakOuts[$i] ?? null;

                            if (
                                empty($in) || $in === 'null' || ! is_string($in)
                                || empty($out) || $out === 'null' || ! is_string($out)
                            ) {
                                return null;
                            }

                            return [
                                'start' => $in,
                                'end' => $out,
                            ];
                        })
                        ->filter()
                        ->values();

                    $hasOverlap = $intervals->search(function (array $b1, int $i) use ($intervals): bool {
                        return $intervals
                            ->slice($i + 1)
                            ->contains(
                                fn (array $b2): bool => $b1['start'] < $b2['end'] && $b2['start'] < $b1['end']
                            );
                    }) !== false;

                    if ($hasOverlap) {
                        $fail('休憩時間が重複しています');
                    }
                },
            ],
            'comment' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * バリデーションエラーメッセージを取得する。
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'new_clock_in.required' => '出勤時間を入力してください',
            'new_clock_in.date_format' => '出勤時間は「時:分」の形式で入力してください',
            'new_clock_out.required' => '退勤時間を入力してください',
            'new_clock_out.date_format' => '退勤時間は「時:分」の形式で入力してください',
            'new_clock_out.after' => '出勤時間もしくは退勤時間が不適切な値です',
            'new_break_in.*.date_format' => '休憩時間が不適切な値です',
            'new_break_out.*.date_format' => '休憩時間もしくは退勤時間が不適切な値です',
            'comment.required' => '備考を記入してください',
            'comment.max' => '備考は255文字以内で入力してください',
        ];
    }
}
