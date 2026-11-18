@extends('layouts.app')

@section('css')
@vite('resources/css/user/attendance-register.css')
@endsection

@section('content')
<div class="attendance__content">
    <h1 class="attendance__heading">勤怠登録</h1>
    
    {{-- 変数を $user->attendance_status から Controllerから渡された $status に変更 --}}
    <div class="attendance__status">
        <p class="attendance__status--item">{{ $status }}</p>
    </div>

    <form class="attendance__form" action="{{ route('attendance.store') }}" method="post">
        @csrf
        <div class="current-date">
            <input class="current-date__item" type="text" value="{{ $formattedDate }}" readonly>
        </div>
        <div class="current-time">
            <input class="current-time__item" type="text" id="currentTime" value="{{ $formattedTime }}" readonly>
        </div>
        
        <div class="attendance__button">
            {{-- ステータスに応じてボタンを切り替え（Controllerの switch文の case名と value を一致させる） --}}
            @if($status === '勤務外')
                <button class="attendance__button--submit--clock-in" type="submit" name="action" value="clock_in">出勤</button>
            @elseif($status === '出勤中')
                <button class="attendance__button--submit--clock-out" type="submit" name="action" value="clock_out">退勤</button>
                <button class="attendance__button--submit--break-in" type="submit" name="action" value="rest_in">休憩入</button>
            @elseif($status === '休憩中')
                <button class="attendance__button--submit--break-out" type="submit" name="action" value="rest_out">休憩戻</button>
            @elseif($status === '退勤済')
                <p class="attendance__message">お疲れ様でした。</p>
            @endif
        </div>
    </form>
</div>

<script>
    function updateTime() {
        const now = new Date();
        const options = { hour: '2-digit', minute: '2-digit', hour12: false };
        document.getElementById('currentTime').value = now.toLocaleTimeString('ja-JP', options);
    }
    setInterval(updateTime, 1000);
    updateTime();
</script>
@endsection