@extends('layouts.admin-app')

@section('css')
    @vite('resources/css/admin/admin-detail.css')
@endsection

@section('content')
    <div class="detail__content">
        <div class="detail__header">
            <h1 class="content__header--item">勤怠詳細</h1>
        </div>
        
        <form class="form" action="{{ url('/admin/attendance/' . $attendanceRecord['id']) }}" method="post">
            @csrf
                <div class="form__content">
                    <div class="form__group">
                        <label class="form__header" for="name">名前</label>
                        <div class="form__input-group">
                            <input class="form__input form__input--name" id="name" type="text" name="name" value="{{ $user->name }}"
                                readonly>
                        </div>
                    </div>
                    <div class="form__group">
                        <label class="form__header">日付</label>
                        <div class="form__input-group">
                            <input class="form__input form__input--date" type="text" value="{{ $attendanceRecord['year'] }}" readonly>
                            <input class="form__input form__input--date" type="text" name="new_date" value="{{ $attendanceRecord['date'] }}" readonly>
                        </div>
                    </div>

                    <div class="form__group">
                        <label class="form__header" for="new_clock_in">出勤・退勤</label>
                        <div class="form__input-group">
                            <input class="form__input" id="new_clock_in" type="text" name="new_clock_in"
                                value="{{ old('new_clock_in', $attendanceRecord['clock_in']) }}">
                            <p>〜</p>
                            <input class="form__input" type="text" name="new_clock_out"
                                value="{{ old('new_clock_out', $attendanceRecord['clock_out']) }}">
                        </div>
                    </div>

                    <div class="error-message">
                        <div></div>
                        <div class="error-message__item">
                            @error('new_clock_in')
                                {{ $message }}
                            @enderror
                            @error('new_clock_out')
                                {{ $message }}
                            @enderror
                        </div>
                    </div>

                    @php
                        $oldBreakIns = old('new_break_in');
                        $oldBreakOuts = old('new_break_out');
                        
                        $dbBreaks = (isset($attendanceRecord['breaks']) && is_array($attendanceRecord['breaks']))
                            ? $attendanceRecord['breaks'] : [];

                        // ループ回数：oldデータがある場合はその数、なければDBの数。ただし必ず「DBの数 ＋ 1行（新規追加用空き欄）」または最低1行を確保する
                        if (!is_null($oldBreakIns)) {
                            $loopCount = count($oldBreakIns);
                        } else {
                            $loopCount = count($dbBreaks) + 1;
                        }
                    @endphp

                    {{-- 休憩入力欄のループ（既存データ行 ＋ 新規追加用の空き1行を常に担保） --}}
                    @for($index = 0; $index < $loopCount; $index++)
                        @php
                            if (!is_null($oldBreakIns)) {
                                $breakInVal = $oldBreakIns[$index] ?? '';
                                $breakOutVal = $oldBreakOuts[$index] ?? '';
                            } else {
                                $breakInVal = $dbBreaks[$index]['break_in'] ?? '';
                                $breakOutVal = $dbBreaks[$index]['break_out'] ?? '';
                            }
                        @endphp
                        <div class="form__group">
                            <label class="form__header">{{ $index === 0 ? '休憩' : '休憩' . ($index + 1) }}</label>
                            <div class="form__input-group">
                                <input class="form__input" type="text" name="new_break_in[{{ $index }}]" value="{{ $breakInVal }}">
                                <p>〜</p>
                                <input class="form__input" type="text" name="new_break_out[{{ $index }}]" value="{{ $breakOutVal }}">
                            </div>
                        </div>
                        <div class="error-message">
                            <div></div>
                            <div class="error-message__item">
                                @error('new_break_in.' . $index)<p>{{ $message }}</p>@enderror
                                @error('new_break_out.' . $index)<p>{{ $message }}</p>@enderror
                            </div>
                        </div>
                    @endfor

                    <div class="form__group">
                        <label class="form__header" for="comment">備考</label>
                        <div class="form__input-group">
                            <textarea class="form__textarea" name="comment" id="comment">{{ old('comment', $attendanceRecord['comment']) }}</textarea>
                        </div>
                    </div>
                    
                    <div class="error-message">
                        <div></div>
                        <div class="error-message__item">
                            @error('comment')
                                {{ $message }}
                            @enderror
                            @error('error')
                                {{ $message }}
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="form__button" style="display: flex; justify-content: flex-end; align-items: center;">
                    @if (!empty($attendanceRecord['application']))
                        <span style="color: red; font-size: 14px; font-weight: bold;">*承認待ちのため修正できません。</span>
                    @else
                        <button class="form__button--submit" type="submit">修正</button>
                    @endif
                </div>
        </form>
    </div>
@endsection