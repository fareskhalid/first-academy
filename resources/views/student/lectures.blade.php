<x-layout title="my_lectures">
<div class="student-page student-lectures-page">
<header class="student-lectures-hero">
    <span class="student-lectures-hero-icon"><x-icon name="calendar" :size="27" /></span>
    <div>
        <p class="eyebrow"><x-t k="learning_space" /></p>
        <h1><x-t k="my_lectures" /></h1>
        <p><x-t k="my_lectures_hint" /></p>
    </div>
    <strong><span>{{ $insights['total'] }}</span><x-t k="lectures_count" /></strong>
</header>

<section class="student-lecture-insights" aria-label="{{ __('ui.student_lecture_overview') }}">
    @foreach([
        ['upcoming_lectures', $insights['upcoming'], 'calendar'],
        ['today_lectures', $insights['today'], 'sparkles'],
        ['attended_lectures', $insights['attended'], 'check'],
    ] as [$label, $value, $icon])
    <article><span><x-icon :name="$icon" :size="19" /></span><div><strong>{{ $value }}</strong><small><x-t :k="$label" /></small></div></article>
    @endforeach
</section>

@if($nextLecture)
    @php($nextStart = $nextLecture->scheduled_start_at->timezone($nextLecture->timezone)->locale(app()->getLocale()))
    @php($nextEnd = $nextLecture->scheduled_end_at->timezone($nextLecture->timezone)->locale(app()->getLocale()))
    <a class="student-next-lecture @if($nextLecture->display_status === 'in_progress') is-live @endif" href="{{ route('student.courses.show', $nextLecture->course_offering_id) }}">
        <span class="student-next-lecture-date"><strong>{{ $nextStart->format('d') }}</strong><small>{{ $nextStart->translatedFormat('M') }}</small></span>
        <span class="student-next-lecture-copy">
            <small><x-t :k="$nextLecture->display_status === 'in_progress' ? 'lecture_live_now' : 'next_lecture'" /></small>
            <strong>{{ $nextLecture->lesson->display_title }}</strong>
            <span>{{ $nextLecture->offering->display_title }} · {{ $nextLecture->group->display_name }}</span>
            <time datetime="{{ $nextLecture->scheduled_start_at->toIso8601String() }}"><bdi>{{ $nextStart->translatedFormat('l، h:i A') }} — {{ $nextEnd->translatedFormat('h:i A') }}</bdi></time>
        </span>
        <x-icon name="arrow" :size="20" />
    </a>
@endif

<nav class="student-lecture-filters" aria-label="{{ __('ui.filter_lectures') }}">
    @foreach(['all' => 'all_lectures', 'upcoming' => 'upcoming_lectures', 'past' => 'past_lectures'] as $value => $label)
        <a href="{{ route('student.lectures', $value === 'all' ? [] : ['period' => $value]) }}" @if($period === $value) aria-current="page" @endif><x-t :k="$label" /></a>
    @endforeach
</nav>

<section class="student-lecture-section">
    <div class="student-section-heading">
        <div><p class="eyebrow"><x-t k="class_schedule" /></p><h2><x-t k="my_lectures" /></h2><span><x-t k="lecture_timeline_hint" /></span></div>
        <span class="student-section-count">{{ $lectures->total() }}</span>
    </div>
    <div class="student-lecture-list">
    @forelse($lectures as $lecture)
        @php($localStart = $lecture->scheduled_start_at->timezone($lecture->timezone)->locale(app()->getLocale()))
        @php($localEnd = $lecture->scheduled_end_at->timezone($lecture->timezone)->locale(app()->getLocale()))
        @php($attendance = $lecture->attendances->first())
        <article class="student-lecture-card @if(in_array($lecture->display_status, ['ended', 'completed'])) is-finished @endif @if($lecture->display_status === 'in_progress') is-live @endif">
            <time class="student-lecture-date" datetime="{{ $lecture->scheduled_start_at->toIso8601String() }}"><span>{{ $localStart->translatedFormat('M') }}</span><strong>{{ $localStart->format('d') }}</strong><small>{{ $localStart->translatedFormat('D') }}</small></time>
            <div class="student-lecture-copy">
                <span class="student-lecture-course">{{ $lecture->offering->course->code }} · {{ $lecture->offering->display_title }}</span>
                <h3>{{ $lecture->lesson->display_title }}</h3>
                <div class="student-lecture-meta">
                    <span><x-icon name="users" :size="14" />{{ $lecture->group->display_name }}</span>
                    <time datetime="{{ $lecture->scheduled_start_at->toIso8601String() }}"><x-icon name="calendar" :size="14" /><bdi>{{ $localStart->translatedFormat('h:i A') }} — {{ $localEnd->translatedFormat('h:i A') }}</bdi></time>
                </div>
            </div>
            <div class="student-lecture-state">
                <x-status :value="$lecture->display_status" />
                @if($attendance)<span><small><x-t k="attendance_record" /></small><x-status :value="$attendance->status" /></span>@endif
            </div>
            <a class="student-lecture-open" href="{{ route('student.courses.show', $lecture->course_offering_id) }}" aria-label="{{ __('ui.view_course') }}"><x-icon name="arrow" :size="18" /></a>
        </article>
    @empty
        <div class="student-lecture-empty"><span><x-icon name="calendar" :size="28" /></span><h2><x-t k="no_student_lectures" /></h2><p><x-t k="no_student_lectures_hint" /></p></div>
    @endforelse
    </div>
    @if($lectures->hasPages())<div class="pagination-wrap">{{ $lectures->links() }}</div>@endif
</section>
</div>
</x-layout>
