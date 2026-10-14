<x-layout title="lecture_management">
<section class="lecture-hero">
    <div class="lecture-hero-icon"><x-icon name="book" :size="30" /></div>
    <div class="lecture-hero-copy"><div class="eyebrow"><x-t k="lectures" /></div><h1><x-t k="lecture_management" /></h1><p><x-t k="lecture_management_hint" /></p></div>
    <div class="lecture-hero-actions"><div><strong>{{ $sessions->total() }}</strong><span><x-t k="lecture_results" /></span></div><a class="button lecture-schedule-button" href="{{ route('instructor.schedule') }}"><x-icon name="calendar" :size="18" /><x-t k="open_schedule" /></a></div>
</section>

<section class="lecture-insight-grid" aria-label="{{ __('ui.lecture_overview') }}">
    @foreach([
        'today_lectures' => [$insights['today'], 'calendar'],
        'upcoming_lectures' => [$insights['upcoming'], 'book'],
        'live_lectures' => [$insights['live'], 'sparkles'],
        'lectures_need_action' => [$insights['needs_action'], 'clipboard'],
    ] as $key => [$value, $icon])
    <article><span><x-icon :name="$icon" :size="20" /></span><div><strong>{{ $value }}</strong><small><x-t :k="$key" /></small></div></article>
    @endforeach
</section>

<section class="panel lecture-filter-panel">
    <div class="lecture-filter-heading"><span><x-icon name="search" :size="19" /></span><div><h2><x-t k="filter_lectures" /></h2><p><x-t k="filter_lectures_hint" /></p></div></div>
    <form method="GET" action="{{ route('instructor.lectures') }}" class="lecture-filters">
        <x-field name="search" label="search_lectures" type="search" :value="$search" maxlength="100" autocomplete="off" />
        <x-select name="offering_id" label="course">
            <option value=""><x-t k="all_courses" /></option>
            @foreach($offerings as $offering)<option value="{{ $offering->id }}" @selected($offeringId === $offering->id)>{{ $offering->display_title }}</option>@endforeach
        </x-select>
        <x-select name="status" label="status">
            <option value=""><x-t k="all_statuses" /></option>
            @foreach(['draft','published','in_progress','ended','completed','cancelled'] as $value)<option value="{{ $value }}" @selected($status === $value)>{{ __('ui.'.$value) }}</option>@endforeach
        </x-select>
        <x-field name="from" label="from_date" type="date" :value="$from?->toDateString()" />
        <x-field name="to" label="to_date" type="date" :value="$to?->toDateString()" />
        <div class="filter-actions">
            <button class="primary"><x-icon name="search" :size="18" /><x-t k="search_action" /></button>
            @if($search !== '' || $status || $offeringId || $from || $to)<a class="button secondary" href="{{ route('instructor.lectures') }}"><x-t k="clear_filters" /></a>@endif
        </div>
    </form>
</section>

<div class="lecture-list-heading"><div><h2><x-t k="all_lectures" /></h2><p><x-t k="lecture_list_hint" /></p></div><span>{{ $sessions->total() }} <x-t k="lecture_results" /></span></div>

<div class="lecture-list">
@forelse($sessions as $session)
    @php($localStart = $session->scheduled_start_at->timezone($session->timezone)->locale(app()->getLocale()))
    @php($localEnd = $session->scheduled_end_at->timezone($session->timezone)->locale(app()->getLocale()))
    @php($attendanceRate = $session->roster_entries_count > 0 ? min(100, (int) round(($session->checked_in_count / $session->roster_entries_count) * 100)) : 0)
    @php($displayStatus = $session->display_status)
    <article class="lecture-card @if(in_array($displayStatus, ['ended','completed','cancelled'])) is-finished @endif" data-status="{{ $displayStatus }}">
        <div class="lecture-status-rail" aria-hidden="true"></div>
        <time class="lecture-date" datetime="{{ $session->scheduled_start_at->toIso8601String() }}"><span>{{ $localStart->translatedFormat('M') }}</span><strong>{{ $localStart->format('d') }}</strong><small>{{ $localStart->translatedFormat('D') }}</small></time>
        <div class="lecture-copy">
            <div class="lecture-title-row"><div><span class="lecture-course">{{ $session->offering->display_title }}</span><h2>{{ $session->lesson->display_title }}</h2></div><x-status :value="$displayStatus" /></div>
            <div class="lecture-meta">
                <span><x-icon name="calendar" :size="16" /><bdi>{{ $localStart->translatedFormat('h:i A') }} — {{ $localEnd->translatedFormat('h:i A') }}</bdi></span>
                <span><x-icon name="users" :size="16" />{{ $session->group->display_name }}</span>
                <span><x-icon name="clipboard" :size="16" />{{ (int) round($localStart->diffInMinutes($localEnd)) }} <x-t k="minutes_short" /></span>
            </div>
        </div>
        <div class="lecture-attendance">
            <div class="lecture-attendance-heading"><span><x-t k="attendance_progress" /></span><strong>{{ $attendanceRate }}%</strong></div>
            <span class="lecture-progress"><i style="width: {{ $attendanceRate }}%"></i></span>
            <div class="lecture-attendance-stats"><span><strong>{{ $session->roster_entries_count }}</strong><x-t k="expected" /></span><span><strong>{{ $session->checked_in_count }}</strong><x-t k="checked_in" /></span><span><strong>{{ $session->absent_count }}</strong><x-t k="absences" /></span></div>
        </div>
        <div class="lecture-actions"><a class="button secondary" href="{{ route('instructor.sessions.show', $session) }}"><x-icon name="settings" :size="17" /><x-t k="manage_session" /></a>@if(!$session->has_ended && in_array($session->status, ['published','in_progress']))<a class="button" href="{{ route('instructor.sessions.qr', $session) }}"><x-icon name="qr" :size="18" /><x-t k="show_qr" /></a>@endif</div>
    </article>
@empty
    <div class="empty lecture-empty"><h2><x-t k="no_lectures_found" /></h2><p class="muted"><x-t k="no_lectures_found_hint" /></p><a class="button" href="{{ route('instructor.schedule') }}"><x-icon name="calendar" :size="18" /><x-t k="open_schedule" /></a></div>
@endforelse
</div>
<div class="mt-5">{{ $sessions->links() }}</div>
</x-layout>
