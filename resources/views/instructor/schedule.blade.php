<x-layout title="schedule">
<div class="schedule-page">
    <section class="schedule-hero">
        <span class="schedule-hero-icon"><x-icon name="calendar" :size="31" /></span>
        <div class="schedule-hero-copy">
            <div class="eyebrow"><x-t k="weekly_planner" /></div>
            <h1><x-t k="schedule" /></h1>
            <p><x-t k="weekly_planner_hint" /></p>
        </div>
        <div class="schedule-week-card">
            <small><x-t :k="$isCurrentWeek ? 'current_week' : 'selected_week'" /></small>
            <strong><bdi>{{ $week->locale(app()->getLocale())->translatedFormat('d M') }} — {{ $week->addDays(6)->locale(app()->getLocale())->translatedFormat('d M Y') }}</bdi></strong>
            <span><x-t k="week_starts_saturday" /></span>
        </div>
    </section>

    <nav class="schedule-week-nav" aria-label="{{ __('ui.week_navigation') }}">
        <a class="button secondary" href="{{ route('instructor.schedule', ['week' => $week->subWeek()->toDateString()]) }}">
            <x-icon name="arrow" :size="17" class="schedule-back-arrow" /><x-t k="previous_week" />
        </a>
        @unless($isCurrentWeek)
            <a class="button schedule-current-week" href="{{ route('instructor.schedule') }}"><x-icon name="calendar" :size="17" /><x-t k="go_to_current_week" /></a>
        @endunless
        <a class="button secondary" href="{{ route('instructor.schedule', ['week' => $week->addWeek()->toDateString()]) }}">
            <x-t k="next_week" /><x-icon name="arrow" :size="17" />
        </a>
    </nav>

    <section class="schedule-insights" aria-label="{{ __('ui.week_overview') }}">
        @foreach([
            ['total_sessions', $weekInsights['total'], 'calendar', 'total'],
            ['draft_sessions', $weekInsights['draft'], 'clipboard', 'draft'],
            ['published_sessions', $weekInsights['published'], 'check', 'published'],
            ['action_required', $weekInsights['needs_action'], 'bell', 'action'],
        ] as [$label, $value, $icon, $type])
            <article class="schedule-insight is-{{ $type }}">
                <span><x-icon :name="$icon" :size="20" /></span>
                <div><strong>{{ $value }}</strong><small><x-t :k="$label" /></small></div>
            </article>
        @endforeach
    </section>

    @if($weekInsights['needs_action'] > 0)
        <div class="schedule-action-alert">
            <span><x-icon name="clipboard" :size="20" /></span>
            <div><strong><x-t k="ended_sessions_need_action" :params="['count' => $weekInsights['needs_action']]" /></strong><small><x-t k="ended_sessions_need_action_hint" /></small></div>
            <a class="button secondary" href="{{ route('instructor.lectures', ['focus' => 'needs_action']) }}"><x-t k="review_lectures" /><x-icon name="arrow" :size="16" /></a>
        </div>
    @endif

    <section class="panel schedule-calendar-panel">
        <div class="schedule-section-heading">
            <span><x-icon name="calendar" :size="22" /></span>
            <div><h2><x-t k="calendar_view" /></h2><p><x-t k="calendar_help" /></p></div>
            <div class="calendar-legend" aria-label="{{ __('ui.status_legend') }}">
                <span><i class="draft"></i><x-t k="draft" /></span>
                <span><i class="published"></i><x-t k="published" /></span>
                <span><i class="in-progress"></i><x-t k="in_progress" /></span>
                <span><i class="completed"></i><x-t k="completed" /></span>
                <span><i class="ended"></i><x-t k="ended" /></span>
            </div>
        </div>
        <div class="schedule-calendar-tip"><x-icon name="sparkles" :size="17" /><span><x-t k="calendar_quick_tip" /></span></div>
        <div class="calendar-scroll"><div data-week-calendar></div></div>
    </section>

    <div class="schedule-workspace">
        <details class="panel calendar-create schedule-create-card" id="calendar-create-panel" open>
            <summary>
                <span class="schedule-summary-icon"><x-icon name="plus" :size="20" /></span>
                <span><strong><x-t k="create_lecture" /></strong><small><x-t k="create_lecture_hint" /></small></span>
                <i aria-hidden="true"></i>
            </summary>
            @if($offerings->isEmpty())
                <div class="schedule-empty-state"><span><x-icon name="book" :size="24" /></span><p><x-t k="schedule_requires_open_course" /></p></div>
            @else
                <form method="POST" action="{{ route('instructor.sessions.store') }}" class="schedule-create-form" x-data="{offerings:window.weekCalendar.offerings,offeringId:@js((string)old('course_offering_id',$offerings->first()?->id)),groupId:@js((string)old('group_id','')),lessonId:@js((string)old('lesson_id','')),current(){return this.offerings.find(item=>String(item.id)===String(this.offeringId))||{groups:[],lessons:[]}},sync(){const item=this.current();if(!item.groups.some(row=>String(row.id)===String(this.groupId)))this.groupId=String(item.groups[0]?.id||'');if(!item.lessons.some(row=>String(row.id)===String(this.lessonId)))this.lessonId=String(item.lessons[0]?.id||'')}}" x-init="sync()">
                    @csrf
                    <div class="schedule-form-section">
                        <div class="schedule-form-label"><span>1</span><div><strong><x-t k="lecture_content" /></strong><small><x-t k="lecture_content_hint" /></small></div></div>
                        <div class="grid two">
                            <x-select name="course_offering_id" label="course" required x-model="offeringId" @change="sync()">@foreach($offerings as $offering)<option value="{{ $offering->id }}">{{ $offering->display_title }}</option>@endforeach</x-select>
                            <x-select name="group_id" label="group" required x-model="groupId"><template x-for="group in current().groups" :key="group.id"><option :value="group.id" x-text="group.name"></option></template></x-select>
                        </div>
                        <x-select name="lesson_id" label="lesson" required x-model="lessonId"><template x-for="lesson in current().lessons" :key="lesson.id"><option :value="lesson.id" x-text="`${lesson.position}. ${lesson.title}`"></option></template></x-select>
                    </div>
                    <div class="schedule-form-section">
                        <div class="schedule-form-label"><span>2</span><div><strong><x-t k="lecture_time" /></strong><small><x-t k="lecture_time_hint" /></small></div></div>
                        <div class="grid two"><x-field name="scheduled_start" type="datetime-local" required /><x-field name="scheduled_end" type="datetime-local" required /></div>
                    </div>
                    <details class="schedule-advanced-settings">
                        <summary><span><x-icon name="settings" :size="17" /><x-t k="advanced_attendance_settings" /></span><small><x-t k="attendance_window_default" /></small></summary>
                        <div class="stack"><x-field name="attendance_opens" type="datetime-local" /><x-field name="late_after" type="datetime-local" /><x-field name="attendance_closes" type="datetime-local" /></div>
                    </details>
                    <x-field name="conflict_override_reason" label="conflict_override_reason" hint="conflict_override_hint" maxlength="500" />
                    <button class="primary schedule-create-submit"><x-icon name="plus" :size="18" /><x-t k="create_draft" /></button>
                </form>
            @endif
        </details>

        <section class="panel schedule-publish-card">
            <div class="schedule-section-heading compact">
                <span><x-icon name="check" :size="20" /></span>
                <div><h2><x-t k="publishing_center" /></h2><p><x-t k="publishing_center_hint" /></p></div>
            </div>
            <form method="POST" action="{{ route('instructor.schedule.publish') }}" class="schedule-publish-form">
                @csrf
                <input type="hidden" name="week" value="{{ $week->toDateString() }}">
                <div class="schedule-draft-list">
                    @forelse($publishableDrafts as $session)
                        <label class="schedule-draft-option">
                            <input type="checkbox" name="session_ids[]" value="{{ $session->id }}">
                            <span><strong>{{ $session->lesson->display_title }}</strong><small>{{ $session->offering->display_title }} · {{ $session->group->display_name }}</small></span>
                            <time datetime="{{ $session->scheduled_start_at->toIso8601String() }}"><bdi>{{ $session->scheduled_start_at->timezone($session->timezone)->locale(app()->getLocale())->translatedFormat('D') }}</bdi><strong><bdi>{{ $session->scheduled_start_at->timezone($session->timezone)->locale(app()->getLocale())->translatedFormat('h:i A') }}</bdi></strong></time>
                        </label>
                    @empty
                        <div class="schedule-empty-state compact"><span><x-icon name="check" :size="22" /></span><div><strong><x-t k="no_drafts" /></strong><p><x-t k="no_drafts_hint" /></p></div></div>
                    @endforelse
                </div>
                <p class="schedule-form-note"><x-icon name="bell" :size="15" /><x-t k="publish_selected_hint" /></p>
                <button class="primary" @disabled($publishableDrafts->isEmpty())><x-icon name="check" :size="17" /><x-t k="publish_selected_or_week" /></button>
            </form>

            <div class="schedule-copy-block">
                <div><strong><x-t k="copy_week" /></strong><small><x-t k="copy_week_hint" /></small></div>
                <form method="POST" action="{{ route('instructor.schedule.copy') }}" class="schedule-copy-form">
                    @csrf
                    <input type="hidden" name="week" value="{{ $week->toDateString() }}">
                    <x-select name="course_offering_id" label="course" required>@foreach($offerings as $offering)<option value="{{ $offering->id }}">{{ $offering->display_title }}</option>@endforeach</x-select>
                    <button class="secondary" @disabled($offerings->isEmpty())><x-icon name="calendar" :size="17" /><x-t k="copy_previous_week" /></button>
                </form>
            </div>
        </section>
    </div>

    <div class="schedule-agenda-heading">
        <div><div class="eyebrow"><x-t k="weekly_agenda" /></div><h2><x-t k="week_sessions" /></h2><p><x-t k="weekly_agenda_hint" /></p></div>
        <span>{{ $sessions->count() }} <x-t k="lectures_count" /></span>
    </div>

    <div class="schedule-agenda">
        @forelse($sessions as $session)
            <article class="schedule-agenda-card @if(in_array($session->display_status, ['ended', 'completed', 'cancelled'])) is-finished @endif" data-status="{{ $session->display_status }}">
                <time class="schedule-agenda-date" datetime="{{ $session->scheduled_start_at->toIso8601String() }}"><span>{{ $session->scheduled_start_at->timezone($session->timezone)->locale(app()->getLocale())->translatedFormat('M') }}</span><strong>{{ $session->scheduled_start_at->timezone($session->timezone)->format('d') }}</strong><small>{{ $session->scheduled_start_at->timezone($session->timezone)->locale(app()->getLocale())->translatedFormat('D') }}</small></time>
                <div class="schedule-agenda-copy">
                    <div class="schedule-agenda-title"><div><span>{{ $session->offering->course->code }} · {{ $session->offering->display_title }}</span><h3>{{ $session->lesson->display_title }}</h3></div><x-status :value="$session->display_status" /></div>
                    <div class="schedule-agenda-meta">
                        <span><x-icon name="calendar" :size="16" /><bdi>{{ $session->scheduled_start_at->timezone($session->timezone)->locale(app()->getLocale())->translatedFormat('h:i A') }} — {{ $session->scheduled_end_at->timezone($session->timezone)->locale(app()->getLocale())->translatedFormat('h:i A') }}</bdi></span>
                        <span><x-icon name="users" :size="16" />{{ $session->group->display_name }}</span>
                        <span><x-icon name="clipboard" :size="16" />{{ $session->roster_entries_count }} <x-t k="expected" /> · {{ $session->attendances_count }} <x-t k="attendance_records" /></span>
                    </div>
                </div>
                <div class="schedule-agenda-actions">
                    <a class="button secondary" href="{{ route('instructor.sessions.show', $session) }}"><x-icon name="settings" :size="17" /><x-t k="manage_session" /></a>
                    @if(!$session->has_ended && in_array($session->status, ['published', 'in_progress']))
                        <a class="button" href="{{ route('instructor.sessions.qr', $session) }}"><x-icon name="qr" :size="17" /><x-t k="show_qr" /></a>
                    @endif
                </div>
            </article>
        @empty
            <div class="empty schedule-agenda-empty"><h2><x-t k="no_sessions" /></h2><p class="muted"><x-t k="no_sessions_hint" /></p><button class="primary" type="button" onclick="document.getElementById('calendar-create-panel').scrollIntoView({behavior:'smooth'})"><x-icon name="plus" :size="17" /><x-t k="create_lecture" /></button></div>
        @endforelse
    </div>
</div>

<script>window.weekCalendar = {{ Illuminate\Support\Js::from([
    'initialDate' => $week->toDateString(), 'locale' => app()->getLocale(), 'events' => $calendarEvents,
    'offerings' => $offerings->map(fn ($offering) => [
        'id' => $offering->id,
        'groups' => $offering->groups->map(fn ($group) => ['id' => $group->id, 'name' => $group->display_name])->values(),
        'lessons' => $offering->lessons->map(fn ($lesson) => ['id' => $lesson->id, 'title' => $lesson->display_title, 'position' => $lesson->position])->values(),
    ])->values(),
    'messages' => [
        'saved' => __('ui.calendar_rescheduled'), 'failed' => __('ui.calendar_move_failed'),
        'moveReason' => __('ui.calendar_move_reason'), 'overridePrompt' => __('ui.calendar_override_prompt'),
        'scheduleConflict' => __('ui.schedule_conflict'), 'studentConflicts' => __('ui.student_conflict_warning'),
    ],
]) }};</script>
</x-layout>
