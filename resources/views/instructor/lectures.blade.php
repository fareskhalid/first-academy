<x-layout title="lecture_management">
<div class="hero">
    <div>
        <div class="eyebrow"><x-t k="lectures" /></div>
        <h1><x-t k="lecture_management" /></h1>
        <p><x-t k="lecture_management_hint" /></p>
    </div>
    <div class="hero-actions">
        <div class="hero-side"><strong class="stat">{{ $sessions->total() }}</strong><span><x-t k="lectures" /></span></div>
        <a class="button secondary" href="{{ route('instructor.schedule') }}"><x-icon name="calendar" :size="18" /><x-t k="open_schedule" /></a>
    </div>
</div>

<section class="panel lecture-filter-panel">
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

<div class="lecture-list">
@forelse($sessions as $session)
    @php($localStart = $session->scheduled_start_at->timezone($session->timezone)->locale(app()->getLocale()))
    @php($localEnd = $session->scheduled_end_at->timezone($session->timezone)->locale(app()->getLocale()))
    <article class="lecture-card @if(in_array($session->display_status, ['ended','completed','cancelled'])) is-finished @endif">
        <div class="lecture-date" aria-hidden="true"><span>{{ $localStart->translatedFormat('M') }}</span><strong>{{ $localStart->format('d') }}</strong><small>{{ $localStart->translatedFormat('D') }}</small></div>
        <div class="lecture-copy">
            <div class="row between"><div><span class="lecture-course">{{ $session->offering->display_title }}</span><h2>{{ $session->lesson->display_title }}</h2></div><x-status :value="$session->display_status" /></div>
            <div class="lecture-meta"><span><x-icon name="calendar" :size="17" /><bdi>{{ $localStart->translatedFormat('h:i A') }} — {{ $localEnd->translatedFormat('h:i A') }}</bdi></span><span><x-icon name="users" :size="17" />{{ $session->group->display_name }}</span></div>
        </div>
        <div class="lecture-metrics"><span><strong>{{ $session->roster_entries_count }}</strong><x-t k="expected" /></span><span><strong>{{ $session->attendances_count }}</strong><x-t k="attendance_records" /></span></div>
        <div class="lecture-actions"><a class="button secondary" href="{{ route('instructor.sessions.show', $session) }}"><x-t k="manage_session" /></a>@if(!$session->has_ended && in_array($session->status, ['published','in_progress']))<a class="button" href="{{ route('instructor.sessions.qr', $session) }}"><x-icon name="qr" :size="18" /><x-t k="show_qr" /></a>@endif</div>
    </article>
@empty
    <div class="empty"><h2><x-t k="no_lectures_found" /></h2><p class="muted"><x-t k="no_lectures_found_hint" /></p><a class="button" href="{{ route('instructor.schedule') }}"><x-t k="open_schedule" /></a></div>
@endforelse
</div>
<div class="mt-5">{{ $sessions->links() }}</div>
</x-layout>
