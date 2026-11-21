@extends('layouts.admin-app')

@section('css')
@vite('resources/css/admin/staff-attendance-list.css')
@endsection

@section('content')
<div class="attendance-list__content">
    <div class="content__header">
        <h1 class="content__header--item">{{ $targetUser->name }}さんの勤怠</h1>
    </div>
    <div class="content__menu">
        <a class="previous-month" href="?date={{ $previousMonth }}">前月</a>
        <p class="current-month">{{ $date->format('Y/m') }}</p>
        <a class="next-month" href="?date={{ $nextMonth }}">翌月</a>
    </div>
    <table class="table">
        <tr class="table__row">
            <th class="table__header">
                <p class="table__header--item">日付</p>
            </th>
            <th class="table__header">
                <p class="table__header--item">出勤</p>
            </th>
            <th class="table__header">
                <p class="table__header--item">退勤</p>
            </th>
            <th class="table__header">
                <p class="table__header--item">休憩</p>
            </th>
            <th class="table__header">
                <p class="table__header--item">合計</p>
            </th>
            <th class="table__header">
                <p class="table__header--item">詳細</p>
            </th>
        </tr>
        @foreach ($formattedAttendanceRecords as $attendanceRecords)
        <tr class="table__row">
            <td class="table__description">
                <p class="table__description--item">{{ $attendanceRecords['date'] }}</p>
            </td>
            <td class="table__description">
                <p class="table__description--item">{{ $attendanceRecords['clock_in'] }}</p>
            </td>
            <td class="table__description">
                <p class="table__description--item">{{ $attendanceRecords['clock_out'] }}</p>
            </td>
            <td class="table__description">
                {{-- コントローラー側で整型済みの文字列をそのまま表示 --}}
                <p class="table__description--item">{{ $attendanceRecords['total_break_time'] }}</p>
            </td>
            <td class="table__description">
                {{-- コントローラー側で整型済みの文字列をそのまま表示 --}}
                <p class="table__description--item">{{ $attendanceRecords['total_time'] }}</p>
            </td>
            <td class="table__description">
                @if (!empty($attendanceRecords['id']))
                {{-- ★ 管理者用の詳細画面ルート（/admin/attendance/{id}）に変更 --}}
                <a class="table__item--detail-link" href="{{ url('/admin/attendance/' . $attendanceRecords['id']) }}">詳細</a>
                @endif
            </td>
        </tr>
        @endforeach
    </table>
    <div class="csv-button">
        <form action="/export" method="post">
        @csrf
            <input type="hidden" name="user_id" value="{{ $targetUser->id }}">
            <input type="hidden" name="year_month" value="{{ $date->format('Y-m') }}">
            <input class="csv-button__submit" type="submit" value="CSV出力">
        </form>
    </div>
</div>
@endsection