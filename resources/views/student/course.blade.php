<x-layout title="course">
<div class="student-page">
<header class="student-course-header">
    <div class="student-course-header-top"><a class="student-back" href="{{ route('dashboard') }}" aria-label="{{ __('ui.back') }}"><x-icon name="arrow" :size="19" /></a><x-status :value="$enrollment->status" /></div>
    <div class="student-course-header-copy"><span><x-icon name="book" :size="24" /></span><div><p>{{ $offering->semester->display_name }} · {{ $offering->course->code }}</p><h1>{{ $offering->display_title }}</h1><small>{{ $offering->instructor->name }}</small></div></div>
</header>

<nav class="student-course-jump" aria-label="{{ __('ui.quick_actions') }}">
    <a href="#course-materials"><x-icon name="files" :size="18" /><x-t k="materials" /></a>
    <a href="#course-schedule"><x-icon name="calendar" :size="18" /><x-t k="class_schedule" /></a>
    <a href="#attendance-history"><x-icon name="check" :size="18" /><x-t k="attendance_history" /></a>
    <a href="{{ route('student.payments.index') }}#enrollment-{{ $enrollment->id }}"><x-icon name="wallet" :size="18" /><x-t k="my_payments" /></a>
</nav>

<section class="student-course-insights" aria-label="{{ __('ui.course_overview') }}">
    <article><span><x-t k="group" /></span><strong>{{ $enrollment->group?->display_name }}</strong></article>
    <article><span><x-t k="attendance_rate" /></span><strong>{{ $attendanceRate === null ? '—' : $attendanceRate.'%' }}</strong></article>
    <article><span><x-t k="classes_attended" /></span><strong>{{ $attendanceRecorded }}</strong></article>
    <article><span><x-t k="absences" /></span><strong>{{ $absenceCount }}</strong></article>
</section>

@if($nextSession)
<a class="student-course-next" href="#course-schedule">
    <span><x-icon name="calendar" :size="22" /></span>
    <div><small><x-t k="next_class" /></small><strong>{{ $nextSession->lesson->display_title }}</strong><p>{{ $nextSession->scheduled_start_at->timezone($offering->timezone)->locale(app()->getLocale())->translatedFormat('l، j F · h:i A') }}</p></div>
    <x-icon name="arrow" :size="19" />
</a>
@endif

<section class="student-action-card student-checkin-card">
    <div class="student-action-heading"><span><x-icon name="qr" :size="23" /></span><div><h2><x-t k="class_code" /></h2><p><x-t k="class_code_hint" /></p></div></div>
    <form method="POST" action="{{ route('attendance.code') }}" class="student-code-form" x-data="{ submitting: false }" @submit="submitting = true">@csrf
        <x-field name="code" label="short_code" required minlength="6" maxlength="6" pattern="[A-Za-z0-9]{6}" dir="ltr" inputmode="text" autocomplete="off" autocapitalize="characters" autocorrect="off" spellcheck="false" />
        <button class="primary" :disabled="submitting"><span x-show="!submitting"><x-icon name="check" :size="18" /><x-t k="continue_checkin" /></span><span x-cloak x-show="submitting"><x-t k="please_wait" /></span></button>
    </form>
</section>

<a class="student-payment-summary" href="{{ route('student.payments.index') }}#enrollment-{{ $enrollment->id }}">
    <span><x-icon name="wallet" :size="22" /></span><div><small><x-t k="course_access" /></small><strong><x-t :k="$paymentState" /></strong><p>@if($remainingFree > 0)<x-t k="free_attendance_remaining" />: {{ $remainingFree }}@else<x-t :k="$paymentState.'_hint'" />@endif</p></div><x-icon name="arrow" :size="18" />
</a>

@if($offering->course->display_description)
<section class="student-info-card student-course-about"><div><h2><x-t k="about_course" /></h2><p>{{ $offering->course->display_description }}</p></div></section>
@endif

<section class="student-content-section student-materials-section" id="course-materials">
    <div class="student-section-heading"><div><p class="eyebrow"><x-t k="learning_resources" /></p><h2><x-t k="course_materials" /></h2></div><span class="student-section-count">{{ $materials->count() }}</span></div>
    @forelse($materials->groupBy(fn ($material) => $material->lesson_id ?: 'general') as $section => $sectionMaterials)
        <div class="student-material-group">
            <h3>@if($section === 'general')<x-t k="general_materials" />@else{{ $sectionMaterials->first()->lesson->display_title }}@endif</h3>
            <div class="student-material-list">
                @foreach($sectionMaterials as $material)
                    <article class="student-material-card is-{{ $material->type }}">
                        <header>
                            <span><x-icon :name="$material->type === 'youtube' ? 'play' : ($material->type === 'image' ? 'image' : 'file')" :size="21" /></span>
                            <div><small><x-t :k="$material->type" /></small><h4>{{ $material->display_title }}</h4></div>
                        </header>
                        @if($material->display_description)<p>{{ $material->display_description }}</p>@endif
                        @if($material->type === 'youtube')
                            <div class="student-video-frame">
                                <iframe src="{{ $material->youtube_embed_url }}" title="{{ $material->display_title }}" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
                            </div>
                        @elseif($material->type === 'image')
                            <a class="student-material-image" href="{{ route('course-materials.file', $material) }}" target="_blank" rel="noopener"><img src="{{ route('course-materials.file', $material) }}" alt="{{ $material->display_title }}" loading="lazy"></a>
                        @else
                            <a class="button secondary student-material-action" href="{{ route('course-materials.file', $material) }}" target="_blank" rel="noopener"><x-icon name="file" :size="17" /><x-t k="open_pdf" />@if($material->file_size)<small>{{ \Illuminate\Support\Number::fileSize($material->file_size) }}</small>@endif</a>
                        @endif
                    </article>
                @endforeach
            </div>
        </div>
    @empty
        <div class="student-empty-state"><span><x-icon name="files" :size="26" /></span><strong><x-t k="no_materials" /></strong><small><x-t k="no_materials_hint" /></small></div>
    @endforelse
</section>

<section class="student-content-section" id="course-schedule">
    <div class="student-section-heading"><div><p class="eyebrow"><x-t k="class_schedule" /></p><h2><x-t k="published_schedule" /></h2></div><span class="student-section-count">{{ $sessions->count() }}</span></div>
    <div class="student-session-list">
    @forelse($sessions as $session)
    <article class="student-session-card {{ $session->has_ended ? 'is-ended' : '' }}">
        <div class="student-session-date"><strong>{{ $session->scheduled_start_at->timezone($offering->timezone)->format('d') }}</strong><span>{{ $session->scheduled_start_at->timezone($offering->timezone)->locale(app()->getLocale())->translatedFormat('M') }}</span></div>
        <div><h3>{{ $session->lesson->display_title }}</h3><p><x-icon name="calendar" :size="14" />{{ $session->scheduled_start_at->timezone($offering->timezone)->locale(app()->getLocale())->translatedFormat('l') }} · <bdi>{{ $session->scheduled_start_at->timezone($offering->timezone)->locale(app()->getLocale())->translatedFormat('h:i A') }}–{{ $session->scheduled_end_at->timezone($offering->timezone)->locale(app()->getLocale())->translatedFormat('h:i A') }}</bdi></p><small>{{ $session->group->display_name }}</small></div>
        <x-status :value="$session->display_status" />
    </article>
    @empty<div class="student-empty-state"><span><x-icon name="calendar" :size="26" /></span><strong><x-t k="no_published_sessions" /></strong></div>@endforelse
    </div>
</section>

<section class="student-info-card student-group-card"><details><summary><span><x-icon name="users" :size="19" /><x-t k="group_history" /></span><x-icon name="arrow" :size="17" /></summary>
<div class="student-group-history">@foreach($enrollment->memberships as $membership)<p>{{ $membership->group->display_name }} <small>{{ $membership->starts_at->timezone($offering->timezone)->format('Y-m-d') }} — @if($membership->ends_at){{ $membership->ends_at->timezone($offering->timezone)->format('Y-m-d') }}@else<x-t k="current" />@endif</small></p>@endforeach</div>
@if($enrollment->status === 'enrolled' && $offering->status === 'open' && $offering->groups->whereNull('archived_at')->count() > 1)
<details class="student-transfer"><summary><x-t k="request_transfer" /></summary><form method="POST" action="{{ route('student.transfer',$enrollment) }}" class="stack">@csrf<x-select name="group_id" label="group" required>@foreach($offering->groups->whereNull('archived_at')->where('id','!=',$enrollment->group_id) as $group)<option value="{{ $group->id }}">{{ $group->display_name }}</option>@endforeach</x-select><x-field name="reason" required maxlength="500" /><button class="primary"><x-t k="request_transfer" /></button></form></details>
@endif
@foreach($enrollment->transferRequests as $transfer)<p class="muted">{{ $transfer->group->display_name }} · <x-status :value="$transfer->status" /></p>@endforeach
</details></section>

<section class="student-content-section" id="attendance-history"><div class="student-section-heading"><div><p class="eyebrow"><x-t k="your_progress" /></p><h2><x-t k="attendance_history" /></h2></div></div>
<div class="student-attendance-list">@forelse($enrollment->attendances as $attendance)<div><span class="student-attendance-dot status-{{ $attendance->status }}"><x-icon name="check" :size="15" /></span><span><strong>{{ $attendance->lesson->display_title }}</strong><small>{{ $attendance->session->scheduled_start_at->timezone($offering->timezone)->locale(app()->getLocale())->translatedFormat('j F Y') }}</small></span><x-status :value="$attendance->status" /></div>@empty<div class="student-empty-state"><span><x-icon name="clipboard" :size="26" /></span><strong><x-t k="no_attendance" /></strong></div>@endforelse</div>
</section>
</div>
</x-layout>
