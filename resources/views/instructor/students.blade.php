<x-layout title="student_management">
<main class="student-management-page">
    <header class="student-management-hero">
        <div class="student-management-hero-icon"><x-icon name="users" :size="31" /></div>
        <div>
            <div class="eyebrow"><x-t k="students" /></div>
            <h1><x-t k="student_management" /></h1>
            <p><x-t k="student_management_hint" /></p>
        </div>
        <div class="student-management-total"><strong>{{ $insights['total'] }}</strong><span><x-t k="students" /></span></div>
    </header>

    <section class="student-management-insights" aria-label="{{ __('ui.student_overview') }}">
        <article><span><x-icon name="users" :size="20" /></span><div><strong>{{ $insights['total'] }}</strong><small><x-t k="total_students" /></small></div></article>
        <article><span><x-icon name="check" :size="20" /></span><div><strong>{{ $insights['active'] }}</strong><small><x-t k="active_accounts" /></small></div></article>
        <article><span><x-icon name="warning" :size="20" /></span><div><strong>{{ $insights['suspended'] }}</strong><small><x-t k="suspended_accounts" /></small></div></article>
        <article><span><x-icon name="sparkles" :size="20" /></span><div><strong>{{ $insights['new'] }}</strong><small><x-t k="joined_this_month" /></small></div></article>
    </section>

    <section class="panel student-admin-filter">
        <div class="student-filter-heading">
            <span><x-icon name="search" :size="19" /></span>
            <div><h2><x-t k="find_students" /></h2><p><x-t k="student_filters_hint" /></p></div>
        </div>
        <form method="GET" action="{{ route('instructor.students') }}" class="student-filter-form">
            <x-field name="search" label="search_students" type="search" :value="$search" maxlength="100" autocomplete="off" />
            <x-select name="status" label="account_status">
                <option value="">{{ __('ui.all_statuses') }}</option>
                @foreach(['active','suspended'] as $value)<option value="{{ $value }}" @selected($status === $value)>{{ __('ui.'.$value) }}</option>@endforeach
            </x-select>
            <x-select name="sort" label="sort_by">
                @foreach(['name_asc', 'name_desc', 'newest', 'oldest'] as $value)<option value="{{ $value }}" @selected($sort === $value)>{{ __('ui.'.$value) }}</option>@endforeach
            </x-select>
            <div class="filter-actions">
                <button class="primary"><x-icon name="search" :size="18" /><x-t k="apply_filters" /></button>
                @if($search !== '' || $status !== '' || $sort !== 'name_asc')<a class="button secondary" href="{{ route('instructor.students') }}"><x-t k="clear_filters" /></a>@endif
            </div>
        </form>
    </section>

    <section class="student-directory-section" aria-labelledby="student-directory-title">
        <div class="student-directory-heading">
            <div><h2 id="student-directory-title"><x-t k="student_directory" /></h2><p>{{ __('ui.matching_students', ['count' => $students->total()]) }}</p></div>
            <span><x-icon name="users" :size="17" />{{ $students->total() }}</span>
        </div>

        <div class="student-table-panel">
            <div class="student-table-scroll">
                <table class="student-table">
                    <thead><tr><th><x-t k="student" /></th><th><x-t k="contact" /></th><th><x-t k="joined_on" /></th><th><x-t k="status" /></th><th><x-t k="account_actions" /></th></tr></thead>
                    <tbody>
                    @forelse($students as $student)
                        <tr>
                            <td data-label="{{ __('ui.student') }}">
                                <div class="student-table-person">
                                    <div class="student-admin-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($student->name, 0, 1)) }}</div>
                                    <span><strong>{{ $student->name }}</strong><small><x-t k="student_code" /> · <bdi>{{ $student->student_code }}</bdi></small></span>
                                </div>
                            </td>
                            <td data-label="{{ __('ui.contact') }}"><div class="student-table-contact"><span><x-icon name="phone" :size="16" /><bdi>{{ $student->phone }}</bdi></span><span><x-icon name="message" :size="16" /><bdi>{{ $student->profile?->whatsapp_phone ?: $student->phone }}</bdi></span></div></td>
                            <td data-label="{{ __('ui.joined_on') }}"><time class="student-joined-date" datetime="{{ $student->created_at->toDateString() }}">{{ $student->created_at->format('Y-m-d') }}</time></td>
                            <td data-label="{{ __('ui.status') }}"><x-status :value="$student->status" /></td>
                            <td data-label="{{ __('ui.account_actions') }}" class="student-table-action-cell">
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
                        <tr><td colspan="5"><div class="empty"><span><x-icon name="users" :size="28" /></span><h2><x-t k="no_students" /></h2><p class="muted"><x-t k="no_students_hint" /></p></div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($students->hasPages())<div class="student-directory-pagination">{{ $students->links() }}</div>@endif
    </section>
</main>
</x-layout>
