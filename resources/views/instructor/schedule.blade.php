<x-layout title="schedule">
<div class="hero"><div><div class="eyebrow"><x-t k="week_label" /></div><h1><x-t k="schedule" /></h1><p>{{ $week->format('Y-m-d') }} — {{ $week->addDays(6)->format('Y-m-d') }} · <x-t k="timezone" />: {{ config('academic.timezone') }}</p></div><div class="row"><a class="button secondary" href="{{ route('instructor.schedule',['week'=>$week->subWeek()->toDateString()]) }}"><x-t k="previous_week" /></a><a class="button secondary" href="{{ route('instructor.schedule',['week'=>$week->addWeek()->toDateString()]) }}"><x-t k="next_week" /></a></div></div>
@if(session('student_conflicts'))<div class="alert"><x-t k="student_conflict_warning" />: {{ session('student_conflicts') }}</div>@endif
<section class="panel calendar-panel"><div class="row between"><div><h2 class="mb-0"><x-t k="calendar_view" /></h2><p class="muted"><x-t k="calendar_help" /></p></div><div class="calendar-legend"><span><i class="draft"></i><x-t k="draft" /></span><span><i class="published"></i><x-t k="published" /></span><span><i class="completed"></i><x-t k="completed" /></span></div></div><div class="alert" data-calendar-status hidden></div><div class="calendar-scroll"><div data-week-calendar></div></div></section>
<div class="grid two">
<details class="panel calendar-create" id="calendar-create-panel" open><summary><x-t k="new_session" /></summary>
@if($offerings->isEmpty())<p class="muted"><x-t k="schedule_requires_open_course" /></p>@else
<form method="POST" action="{{ route('instructor.sessions.store') }}" class="stack" x-data="{offerings:window.weekCalendar.offerings,offeringId:@js((string)old('course_offering_id',$offerings->first()?->id)),groupId:@js((string)old('group_id','')),lessonId:@js((string)old('lesson_id','')),current(){return this.offerings.find(item=>String(item.id)===String(this.offeringId))||{groups:[],lessons:[]}},sync(){const item=this.current();if(!item.groups.some(row=>String(row.id)===String(this.groupId)))this.groupId=String(item.groups[0]?.id||'');if(!item.lessons.some(row=>String(row.id)===String(this.lessonId)))this.lessonId=String(item.lessons[0]?.id||'')}}" x-init="sync()">@csrf
<x-select name="course_offering_id" label="course" required x-model="offeringId" @change="sync()">@foreach($offerings as $offering)<option value="{{ $offering->id }}">{{ $offering->title }}</option>@endforeach</x-select>
<x-select name="group_id" label="group" required x-model="groupId"><template x-for="group in current().groups" :key="group.id"><option :value="group.id" x-text="group.name"></option></template></x-select>
<x-select name="lesson_id" label="lesson" required x-model="lessonId"><template x-for="lesson in current().lessons" :key="lesson.id"><option :value="lesson.id" x-text="`${lesson.position}. ${lesson.title}`"></option></template></x-select>
<div class="grid two"><x-field name="scheduled_start" type="datetime-local" required /><x-field name="scheduled_end" type="datetime-local" required /></div>
<x-field name="location" /><details><summary><x-t k="attendance_window" /></summary><div class="stack"><x-field name="attendance_opens" type="datetime-local" hint="attendance_window_default" /><x-field name="late_after" type="datetime-local" /><x-field name="attendance_closes" type="datetime-local" /></div></details>
<x-field name="conflict_override_reason" label="conflict_override_reason" hint="conflict_override_hint" />
<button class="primary"><x-icon name="plus" :size="18" /><x-t k="create_draft" /></button></form>@endif
</details>
<section class="panel"><h2><x-t k="week_actions" /></h2>
<form method="POST" action="{{ route('instructor.schedule.publish') }}" class="stack">@csrf<input type="hidden" name="week" value="{{ $week->toDateString() }}">
<p class="muted"><x-t k="publish_selected_hint" /></p>@foreach($sessions->where('status','draft') as $session)<label class="check"><input type="checkbox" name="session_ids[]" value="{{ $session->id }}"><span>{{ $session->offering->title }} · {{ $session->scheduled_start_at->timezone($session->timezone)->locale(app()->getLocale())->translatedFormat('D H:i') }} · {{ $session->group->name }}</span></label>@endforeach
<button class="primary"><x-t k="publish_selected_or_week" /></button></form><hr class="divider">
<form method="POST" action="{{ route('instructor.schedule.copy') }}" class="stack">@csrf<input type="hidden" name="week" value="{{ $week->toDateString() }}"><x-select name="course_offering_id" label="course" required>@foreach($offerings as $offering)<option value="{{ $offering->id }}">{{ $offering->title }}</option>@endforeach</x-select><button class="secondary"><x-t k="copy_previous_week" /></button></form>
</section></div>
<div class="row between mb-4"><h2 class="mb-0 section-heading"><span class="heading-icon"><x-icon name="calendar" :size="20" /></span><x-t k="week_sessions" /></h2></div>
<div class="grid two">@forelse($sessions as $session)<article class="card"><div class="row between"><span class="muted">{{ $session->scheduled_start_at->timezone($session->timezone)->locale(app()->getLocale())->translatedFormat('l, Y-m-d H:i') }}</span><x-status :value="$session->status" /></div><h3>{{ $session->offering->title }} · {{ $session->lesson->title }}</h3><p class="muted">{{ $session->group->name }} · {{ $session->location ?: __('ui.location_not_set') }}</p><p class="muted">{{ $session->roster_entries_count }} <x-t k="expected" /> · {{ $session->attendances_count }} <x-t k="attendance_records" /></p>
<div class="row"><a class="button secondary" href="{{ route('instructor.sessions.show',$session) }}"><x-t k="manage_session" /></a>@if(in_array($session->status,['published','in_progress']))<a class="button" href="{{ route('instructor.sessions.qr',$session) }}"><x-t k="show_qr" /></a>@endif</div>
</article>@empty<div class="empty"><h2><x-t k="no_sessions" /></h2><p class="muted"><x-t k="no_sessions_hint" /></p></div>@endforelse</div>
<script>window.weekCalendar = {{ Illuminate\Support\Js::from([
    'initialDate' => $week->toDateString(), 'locale' => app()->getLocale(), 'events' => $calendarEvents,
    'offerings' => $offerings->map(fn ($offering) => [
        'id' => $offering->id, 'groups' => $offering->groups->map->only(['id', 'name'])->values(),
        'lessons' => $offering->lessons->map->only(['id', 'title', 'position'])->values(),
    ])->values(),
    'messages' => [
        'saved' => __('ui.calendar_rescheduled'), 'failed' => __('ui.calendar_move_failed'),
        'moveReason' => __('ui.calendar_move_reason'), 'overridePrompt' => __('ui.calendar_override_prompt'),
        'scheduleConflict' => __('ui.schedule_conflict'), 'studentConflicts' => __('ui.student_conflict_warning'),
    ],
]) }};</script>
</x-layout>
