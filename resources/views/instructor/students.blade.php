<x-layout title="student_management">
<div class="hero">
    <div><div class="eyebrow"><x-t k="students" /></div><h1><x-t k="student_management" /></h1><p><x-t k="student_management_hint" /></p></div>
    <div class="hero-side"><strong class="stat">{{ $students->total() }}</strong><span><x-t k="students" /></span></div>
</div>

<section class="panel student-admin-filter">
    <form method="GET" action="{{ route('instructor.students') }}" class="student-filter-form">
        <x-field name="search" label="search_students" type="search" :value="$search" maxlength="100" autocomplete="off" />
        <x-select name="status" label="status">
            <option value="">{{ __('ui.all_statuses') }}</option>
            @foreach(['active','suspended'] as $value)<option value="{{ $value }}" @selected($status === $value)>{{ __('ui.'.$value) }}</option>@endforeach
        </x-select>
        <div class="filter-actions"><button class="primary"><x-icon name="search" :size="18" /><x-t k="search_action" /></button>@if($search !== '' || $status)<a class="button secondary" href="{{ route('instructor.students') }}"><x-t k="clear_filters" /></a>@endif</div>
    </form>
</section>

<section class="student-table-panel">
    <div class="student-table-scroll">
        <table class="student-table">
            <thead><tr><th><x-t k="student" /></th><th><x-t k="contact" /></th><th><x-t k="enrolled_courses" /></th><th><x-t k="status" /></th><th><x-t k="account_actions" /></th></tr></thead>
            <tbody>
            @forelse($students as $student)
                <tr>
                    <td>
                        <div class="student-table-person">
                            <div class="student-admin-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($student->name, 0, 1)) }}</div>
                            <span><strong>{{ $student->name }}</strong><small><x-t k="student_code" /> · <bdi>{{ $student->student_code }}</bdi></small></span>
                        </div>
                    </td>
                    <td><div class="student-table-contact"><span><x-icon name="user" :size="16" /><bdi>{{ $student->phone }}</bdi></span><span><x-icon name="message" :size="16" /><bdi>{{ $student->profile?->whatsapp_phone ?: $student->phone }}</bdi></span></div></td>
                    <td>
                        <div class="student-table-courses">
                        @foreach($student->enrollments as $enrollment)
                            <a href="{{ route('instructor.offerings.show', $enrollment->offering) }}"><span>{{ $enrollment->offering->display_title }}</span><small>{{ $enrollment->group?->display_name }}</small></a>
                        @endforeach
                        </div>
                    </td>
                    <td><x-status :value="$student->status" /></td>
                    <td class="student-table-action-cell">
                        <details class="student-account-actions">
                            <summary><x-icon name="settings" :size="17" /><x-t k="manage" /></summary>
                            <form method="POST" action="{{ route('instructor.students.account', $student) }}" class="student-row-action-form" @submit="if (!confirm($store.i18n.t('confirm_account'))) $event.preventDefault()">@csrf
                                <x-select name="action" :id="'student-action-'.$student->id" label="action">@foreach(['reset','suspend','reactivate'] as $action)<option value="{{ $action }}">{{ __('ui.'.$action) }}</option>@endforeach</x-select>
                                <x-field name="reason" :id="'student-reason-'.$student->id" required maxlength="500" />
                                <button class="secondary"><x-t k="apply_action" /></button>
                            </form>
                        </details>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5"><div class="empty"><h2><x-t k="no_students" /></h2><p class="muted"><x-t k="no_students_hint" /></p></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>
<div class="mt-5">{{ $students->links() }}</div>
</x-layout>
