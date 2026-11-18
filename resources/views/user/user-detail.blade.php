@extends('layouts.app')

@section('css')
    @vite('resources/css/user/user-detail.css')
@endsection

@section('content')
    <div class="detail__content">
        <div class="detail__header">
            <h1 class="content__header--item">勤怠詳細</h1>
        </div>
        <form class="form" action="{{ route('attendance.update', $data['id']) }}" method="post">
            @csrf

            @if (is_null($data['application']))
                {{-- 承認待ちが無い場合：修正フォーム --}}
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
                            <input class="form__input form__input--date" type="text" value="{{ $data['year'] }}" readonly>
                            <input class="form__input form__input--date" type="text" name="new_date" value="{{ $data['date'] }}" readonly>
                        </div>
                    </div>

                    <div class="form__group">
                        <label class="form__header" for="new_clock_in">出勤・退勤</label>
                        <div class="form__input-group">
                            {{-- ▼ old() を追加して入力値を保持 --}}
                            <input class="form__input" id="new_clock_in" type="text" name="new_clock_in" value="{{ old('new_clock_in', $data['clock_in']) }}">
                            <p>〜</p>
                            <input class="form__input" type="text" name="new_clock_out" value="{{ old('new_clock_out', $data['clock_out']) }}">
                        </div>
                    </div>

                    <div class="error-message">
                        <div></div>
                        <div class="error-message__item">
                            @error('new_clock_in') {{ $message }} @enderror
                            @error('new_clock_out') {{ $message }} @enderror
                        </div>
                    </div>

                    {{-- ▼ 休憩：old入力とDBデータを考慮してループ描画 --}}
                    @php
                        $oldBreakIns = old('new_break_in');
                        $oldBreakOuts = old('new_break_out');
                        
                        $dbBreaks = (isset($data['breaks']) && is_array($data['breaks']))
                            ? $data['breaks'] : [];

                        // oldデータがある場合はその数、なければDBの数 + 1行（新規追加用空き欄）を確保
                        if (!is_null($oldBreakIns)) {
                            $loopCount = count($oldBreakIns);
                        } else {
                            $loopCount = count($dbBreaks) + 1;
                        }
                    @endphp

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
                            {{-- ▼ 備考欄も old() で保持（inputタグになっているためそのまま適用） --}}
                            <input class="form__textarea" id="comment" name="comment" value="{{ old('comment', $data['comment']) }}">
                        </div>
                    </div>

                    <div class="error-message">
                        <div></div>
                        <div class="error-message__item">
                            @error('comment') {{ $message }} @enderror
                        </div>
                    </div>
                </div>

                <div class="form__button">
                    <button class="form__button--submit" type="submit">修正</button>
                </div>

            @else
                {{-- 承認待ちあり：閲覧のみ --}}
                <div class="form__content">
                    <div class="form__group">
                        <label class="form__header">名前</label>
                        <div class="form__input-group">
                            <input class="form__input form__input--name readonly" type="text" value="{{ $user->name }}" readonly>
                        </div>
                    </div>

                    <div class="form__group">
                        <label class="form__header">日付</label>
                        <div class="form__input-group">
                            <input class="form__input readonly" type="text" value="{{ $data['year'] }}" readonly>
                            <input class="form__input readonly" type="text" value="{{ $data['date'] }}" readonly>
                        </div>
                    </div>

                    <div class="form__group">
                        <label class="form__header">出勤・退勤</label>
                        <div class="form__input-group">
                            <input class="form__input readonly" type="text" value="{{ $data['clock_in'] }}" readonly>
                            <p>〜</p>
                            <input class="form__input readonly" type="text" value="{{ $data['clock_out'] }}" readonly>
                        </div>
                    </div>

                    @foreach($data['breaks'] as $index => $break)
                        <div class="form__group">
                            <label class="form__header">{{ $index === 0 ? '休憩' : '休憩' . ($index + 1) }}</label>
                            <div class="form__input-group">
                                <input class="form__input readonly" type="text" value="{{ $break['break_in'] }}" readonly>
                                <p>〜</p>
                                <input class="form__input readonly" type="text" value="{{ $break['break_out'] }}" readonly>
                            </div>
                        </div>
                    @endforeach

                    <div class="form__group">
                        <label class="form__header">備考</label>
                        <div class="form__input-group">
                            <textarea class="form__textarea readonly" name="comment" readonly>{{ $data['comment'] }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="form__button">
                    <p class="readonly-message">承認待ちのため修正できません。</p>
                </div>
            @endif

        </form>
    </div>
@endsection