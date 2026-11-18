<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Carbon\Carbon;

class UpdateAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * バリデーション実行前に、'9:00' や '09:00' などの時刻形式を自動で '09:00' (H:i) に整形する
     */
    protected function prepareForValidation()
    {
        $formatTime = function ($time) {
            if (empty($time) || $time === 'null') return $time;
            try {
                return Carbon::parse($time)->format('H:i');
            } catch (\Exception $e) {
                return $time;
            }
        };

        // 出退勤時間の整形
        $this->merge([
            'new_clock_in'  => $formatTime($this->input('new_clock_in')),
            'new_clock_out' => $formatTime($this->input('new_clock_out')),
        ]);

        // 休憩時間の整形（配列対応）
        if ($this->has('new_break_in')) {
            $breakIns = array_map($formatTime, $this->input('new_break_in', []));
            $this->merge(['new_break_in' => $breakIns]);
        }

        if ($this->has('new_break_out')) {
            $breakOuts = array_map($formatTime, $this->input('new_break_out', []));
            $this->merge(['new_break_out' => $breakOuts]);
        }
    }

    public function rules(): array
    {
        return [
            'new_clock_in'   => ['required', 'date_format:H:i'],
            'new_clock_out'  => ['required', 'date_format:H:i', 'after:new_clock_in'],
            
            'new_break_in.*' => ['nullable', 'date_format:H:i', 'after_or_equal:new_clock_in', 'before:new_clock_out'],
            
            'new_break_out.*'=> [
                'nullable', 
                'date_format:H:i', 
                'after:new_break_in.*', 
                'before_or_equal:new_clock_out',
                // ▼ 複数の休憩行同士の時間重複（バッティング）を検知して弾くカスタムルール
                function ($attribute, $value, $fail) {
                    $breakIns = $this->input('new_break_in', []);
                    $breakOuts = $this->input('new_break_out', []);
                    
                    $intervals = [];
                    foreach ($breakIns as $i => $in) {
                        $out = $breakOuts[$i] ?? null;
                        if (!empty($in) && $in !== 'null' && !empty($out) && $out !== 'null') {
                            $intervals[] = ['start' => $in, 'end' => $out];
                        }
                    }

                    // 休憩期間同士が重なっていないか総当たりでチェック
                    for ($i = 0; $i < count($intervals); $i++) {
                        for ($j = $i + 1; $j < count($intervals); $j++) {
                            $b1 = $intervals[$i];
                            $b2 = $intervals[$j];

                            if ($b1['start'] < $b2['end'] && $b2['start'] < $b1['end']) {
                                $fail('休憩時間が重複しています');
                                return;
                            }
                        }
                    }
                },
            ],
            
            'comment'        => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'new_clock_in.required'          => '出勤時間を入力してください',
            'new_clock_in.date_format'       => '出勤時間は「時:分」の形式で入力してください',
            
            'new_clock_out.required'         => '退勤時間を入力してください',
            'new_clock_out.date_format'      => '退勤時間は「時:分」の形式で入力してください',
            'new_clock_out.after'            => '出勤時間もしくは退勤時間が不適切な値です',
            
            'new_break_in.*.date_format'     => '休憩時間が不適切な値です',
            'new_break_in.*.after_or_equal'  => '休憩時間が不適切な値です',
            'new_break_in.*.before'          => '休憩時間が不適切な値です',
            
            'new_break_out.*.date_format'    => '休憩時間が不適切な値です',
            'new_break_out.*.after'          => '休憩時間が不適切な値です',
            'new_break_out.*.before_or_equal'=> '休憩時間もしくは退勤時間が不適切な値です',
            
            'comment.required'               => '備考を記入してください',
            'comment.max'                    => '備考は255文字以内で入力してください',
        ];
    }
}