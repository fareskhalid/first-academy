<x-layout title="lecture_management">
@php
    $selectedOffering = $offeringId ? $offerings->firstWhere('id', $offeringId) : null;
    $hasFilters = $search !== '' || $status || $offeringId || $from || $to || $focus;
@endphp

<section class="lecture-hero">
    <div class="lecture-hero-icon"><x-icon name="book" :size="30" /></div>
    <div class="lecture-hero-copy">
        <div class="eyebrow"><x-t k="lectures" /></div>
        <h1><x-t k="lecture_management" /></h1>
        <p><x-t k="lecture_management_hint" /></p>
    </div>
    <div class="lecture-hero-actions">
        <div><strong>{{ $insights['total'] }}</strong><span><x-t k="total_lectures" /></span></div>
        <a class="button lecture-schedule-button" href="{{ route('instructor.schedule') }}"><x-icon name="calendar" :size="18" /><x-t k="open_schedule" /></a>
    </div>
</section>

<section class="lecture-insight-grid" aria-label="{{ __('ui.lecture_overview') }}">
    @foreach([
        'today' => [$insights['today'], 'today_lectures', 'calendar'],
        'upcoming' => [$insights['upcoming'], 'upcoming_lectures', 'book'],
        'live' => [$insights['live'], 'live_lectures', 'sparkles'],
        'needs_action' => [$insights['needs_action'], 'lectures_need_action', 'clipboard'],
    ] as $focusKey => [$value, $label, $icon])
    <a href="{{ route('instructor.lectures', ['focus' => $focusKey]) }}" class="lecture-insight-card @if($focus === $focusKey) is-active @endif" @if($focus === $focusKey) aria-current="true" @endif>
        <span><x-icon :name="$icon" :size="20" /></span>
        <div><strong>{{ $value }}</strong><small><x-t :k="$label" /></small></div>
        <x-icon name="arrow" :size="15" class="lecture-insight-arrow" />
    </a>
    @endforeach
</section>

@if($featuredSession)
    @php($featuredStart = $featuredSession->scheduled_start_at->timezone($featuredSession->timezone)->locale(app()->getLocale()))
    @php($featuredEnd = $featuredSession->scheduled_end_at->timezone($featuredSession->timezone)->locale(app()->getLocale()))
    @php($featuredIsLive = $featuredSession->display_status === 'in_progress')
    <section class="lecture-focus-card @if($featuredIsLive) is-live @endif">
        <div class="lecture-focus-marker"><x-icon :name="$featuredIsLive ? 'sparkles' : 'calendar'" :size="23" /></div>
        <div class="lecture-focus-copy">
            <div class="lecture-focus-label"><span></span><x-t :k="$featuredIsLive ? 'lecture_live_now' : 'next_lecture'" /></div>
            <h2>{{ $featuredSession->lesson->display_title }}</h2>
            <p>{{ $featuredSession->offering->display_title }} · {{ $featuredSession->group->display_name }}</p>
            <div class="lecture-focus-meta">
                <time datetime="{{ $featuredSession->scheduled_start_at->toIso8601String() }}"><x-icon name="calendar" :size="15" /><bdi>{{ $featuredStart->translatedFormat('D, d M · h:i A') }} — {{ $featuredEnd->translatedFormat('h:i A') }}</bdi></time>
                <span><x-icon name="users" :size="15" />{{ $featuredSession->offering->course->code }}</span>
            </div>
        </div>
        <div class="lecture-focus-side">
            <strong>{{ $featuredIsLive ? __('ui.lecture_live_now') : $featuredStart->diffForHumans() }}</strong>
            <div>
                <a class="button secondary" href="{{ route('instructor.sessions.show', $featuredSession) }}"><x-t k="manage_session" /></a>
                @if(in_array($featuredSession->status, ['published', 'in_progress']))<a class="button" href="{{ route('instructor.sessions.qr', $featuredSession) }}"><x-icon name="qr" :size="17" /><x-t k="show_qr" /></a>@endif
            </div>
        </div>
    </section>
@endif

@if($insights['needs_action'] > 0)
<a class="lecture-action-alert" href="{{ route('instructor.lectures', ['focus' => 'needs_action']) }}">
    <span><x-icon name="clipboard" :size="21" /></span>
    <div><strong><x-t k="lectures_waiting_completion" :params="['count' => $insights['needs_action']]" /></strong><small><x-t k="lectures_waiting_completion_hint" /></small></div>
    <x-icon name="arrow" :size="18" />
</a>
@endif

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
            @if($hasFilters)<a class="button secondary" href="{{ route('instructor.lectures') }}"><x-t k="clear_filters" /></a>@endif
        </div>
    </form>
    @if($hasFilters)
    <div class="lecture-active-filters" aria-label="{{ __('ui.active_filters') }}">
        <strong><x-t k="active_filters" /></strong>
        @if($focus)<span><x-t :k="'focus_'.$focus" /></span>@endif
        @if($search !== '')<span><x-icon name="search" :size="13" /><bdi>{{ $search }}</bdi></span>@endif
        @if($selectedOffering)<span>{{ $selectedOffering->display_title }}</span>@endif
        @if($status)<span><x-t :k="$status" /></span>@endif
        @if($from)<span><x-t k="from_date" />: <bdi>{{ $from->toDateString() }}</bdi></span>@endif
        @if($to)<span><x-t k="to_date" />: <bdi>{{ $to->toDateString() }}</bdi></span>@endif
    </div>
    @endif
</section>

<div class="lecture-list-heading">
    <div><div class="eyebrow"><x-t k="lecture_timeline" /></div><h2><x-t k="all_lectures" /></h2><p><x-t k="lecture_list_hint" /></p></div>
    <span>{{ $sessions->total() }} <x-t k="lecture_results" /></span>
</div>

<div class="lecture-list">
@forelse($sessions as $session)
    @php($localStart = $session->scheduled_start_at->timezone($session->timezone)->locale(app()->getLocale()))
    @php($localEnd = $session->scheduled_end_at->timezone($session->timezone)->locale(app()->getLocale()))
    @php($attendanceRate = $session->roster_entries_count > 0 ? min(100, (int) round(($session->checked_in_count / $session->roster_entries_count) * 100)) : 0)
    @php($displayStatus = $session->display_status)
    <article class="lecture-card @if(in_array($displayStatus, ['ended','completed','cancelled'])) is-finished @endif @if($displayStatus === 'ended') needs-action @endif" data-status="{{ $displayStatus }}">
        <div class="lecture-status-rail" aria-hidden="true"></div>
        <time class="lecture-date" datetime="{{ $session->scheduled_start_at->toIso8601String() }}"><span>{{ $localStart->translatedFormat('M') }}</span><strong>{{ $localStart->format('d') }}</strong><small>{{ $localStart->translatedFormat('D') }}</small></time>
        <div class="lecture-copy">
            <div class="lecture-title-row">
                <div><span class="lecture-course">{{ $session->offering->course->code }} · {{ $session->offering->display_title }}</span><h2>{{ $session->lesson->display_title }}</h2></div>
                <div class="lecture-status-stack"><x-status :value="$displayStatus" /><small>{{ $localStart->diffForHumans() }}</small></div>
            </div>
            <div class="lecture-meta">
                <span><x-icon name="calendar" :size="16" /><bdi>{{ $localStart->translatedFormat('h:i A') }} — {{ $localEnd->translatedFormat('h:i A') }}</bdi></span>
                <span><x-icon name="users" :size="16" />{{ $session->group->display_name }}</span>
                <span><x-icon name="clipboard" :size="16" />{{ (int) round($localStart->diffInMinutes($localEnd)) }} <x-t k="minutes_short" /></span>
            </div>
        </div>
        @if($session->roster_entries_count > 0)
        <div class="lecture-attendance">
            <div class="lecture-attendance-heading"><span><x-t k="attendance_progress" /></span><strong>{{ $attendanceRate }}%</strong></div>
            <span class="lecture-progress"><i style="width: {{ $attendanceRate }}%"></i></span>
            <div class="lecture-attendance-stats">
                <span><strong>{{ $session->roster_entries_count }}</strong><x-t k="expected" /></span>
                <span><strong>{{ $session->checked_in_count }}</strong><x-t k="checked_in" /></span>
                <span><strong>{{ $session->late_count }}</strong><x-t k="late" /></span>
                <span><strong>{{ $session->absent_count }}</strong><x-t k="absences" /></span>
            </div>
        </div>
        @else
        <div class="lecture-attendance is-pending"><span><x-icon name="users" :size="19" /></span><div><strong><x-t k="attendance_pending" /></strong><small><x-t k="attendance_pending_hint" /></small></div></div>
        @endif
        <div class="lecture-actions">
            <a class="button secondary" href="{{ route('instructor.sessions.show', $session) }}"><x-icon name="settings" :size="17" /><x-t :k="$displayStatus === 'ended' ? 'review_lecture' : 'manage_session'" /></a>
            @if(!$session->has_ended && in_array($session->status, ['published','in_progress']))<a class="button" href="{{ route('instructor.sessions.qr', $session) }}"><x-icon name="qr" :size="18" /><x-t k="show_qr" /></a>@endif
        </div>
    </article>
@empty
    <div class="empty lecture-empty"><h2><x-t k="no_lectures_found" /></h2><p class="muted"><x-t k="no_lectures_found_hint" /></p><a class="button" href="{{ route('instructor.schedule') }}"><x-icon name="calendar" :size="18" /><x-t k="open_schedule" /></a></div>
@endforelse
</div>
<div class="mt-5">{{ $sessions->links() }}</div>
</x-layout>
