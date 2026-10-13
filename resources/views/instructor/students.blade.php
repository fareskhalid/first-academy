<x-layout title="student_management">
<div class="hero"><div><div class="eyebrow"><x-t k="students" /></div><h1><x-t k="student_management" /></h1><p><x-t k="student_management_hint" /></p></div><div class="hero-side"><strong class="stat">{{ $students->total() }}</strong><span><x-t k="students" /></span></div></div>

<section class="panel">
<form method="GET" action="{{ route('instructor.students') }}" class="grid two">
    <x-field name="search" label="search_students" type="search" :value="$search" maxlength="100" autocomplete="off" />
    <x-select name="status" label="status">
        <option value="">{{ __('ui.all_statuses') }}</option>
        @foreach(['active','suspended'] as $value)<option value="{{ $value }}" @selected($status === $value)>{{ __('ui.'.$value) }}</option>@endforeach
    </x-select>
    <div class="row"><button class="primary"><x-icon name="users" :size="18" /><x-t k="search_action" /></button>@if($search !== '' || $status)<a class="button secondary" href="{{ route('instructor.students') }}"><x-t k="clear_filters" /></a>@endif</div>
</form>
</section>

<section class="panel"><div class="responsive-list">
@forelse($students as $student)
<details class="list-item">
    <summary><span class="row between"><span><strong>{{ $student->name }}</strong> · <bdi>{{ $student->student_code }}</bdi></span><x-status :value="$student->status" /></span></summary>
    <div class="grid two mt-5">
        <div><h3><x-t k="contact" /></h3><p><bdi>{{ $student->phone }}</bdi></p><p class="muted"><x-t k="whatsapp_phone" />: <bdi>{{ $student->profile?->whatsapp_phone }}</bdi></p>@if($student->profile?->university)<p class="muted">{{ $student->profile->university }}@if($student->profile->university_student_id) · <bdi>{{ $student->profile->university_student_id }}</bdi>@endif</p>@endif</div>
        <div><h3><x-t k="enrolled_courses" /></h3>@foreach($student->enrollments as $enrollment)<p><a href="{{ route('instructor.offerings.show',$enrollment->offering) }}">{{ $enrollment->offering->display_title }}</a> · {{ $enrollment->group?->display_name }} · <x-status :value="$enrollment->status" /></p>@endforeach</div>
    </div>
    <form method="POST" action="{{ route('instructor.students.account',$student) }}" class="grid two mt-5" @submit="if (!confirm($store.i18n.t('confirm_account'))) $event.preventDefault()">@csrf
        <x-select name="action" :id="'student-action-'.$student->id" label="action">@foreach(['reset','suspend','reactivate'] as $action)<option value="{{ $action }}">{{ __('ui.'.$action) }}</option>@endforeach</x-select>
        <x-field name="reason" :id="'student-reason-'.$student->id" required maxlength="500" />
        <div><button class="secondary"><x-t k="apply_action" /></button></div>
    </form>
</details>
@empty<div class="empty"><h2><x-t k="no_students" /></h2><p class="muted"><x-t k="no_students_hint" /></p></div>@endforelse
</div><div class="mt-5">{{ $students->links() }}</div></section>
</x-layout>
