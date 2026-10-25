<x-layout title="course">
    @php
        $activeGroups = $offering->groups->whereNull('archived_at');
        $activeLessons = $offering->lessons->whereNull('archived_at');
        $activeEnrollments = $offering->enrollments->where('status', 'enrolled');
        $activeInvitations = $offering->invitations->whereNull('revoked_at')->filter(fn ($invitation) => $invitation->expires_at->isFuture());
        $pendingTransfers = $offering->enrollments->sum(fn ($enrollment) => $enrollment->transferRequests->where('status', 'pending')->count());
    @endphp

    <main class="offering-detail-page">
        <a class="offering-back-link" href="{{ route('instructor.offerings') }}">
            <x-icon name="arrow" :size="16" />
            <x-t k="back_to_offerings" />
        </a>

        <header class="offering-detail-hero" data-status="{{ $offering->status }}">
            <span class="offering-detail-icon"><x-icon name="graduation" :size="31" /></span>
            <div class="offering-detail-copy">
                <div class="eyebrow">{{ $offering->semester->display_name }} · <bdi>{{ $offering->course->code }}</bdi></div>
                <h1>{{ $offering->display_title }}</h1>
                <p>{{ $offering->course->display_name }}</p>
            </div>
            <div class="offering-detail-state">
                <x-status :value="$offering->status" />
                <small><x-t k="course_status" /></small>
            </div>
        </header>

        <nav class="offering-quick-actions" aria-label="{{ __('ui.quick_actions') }}">
            <a href="{{ route('instructor.schedule') }}"><x-icon name="calendar" :size="17" /><x-t k="open_schedule" /></a>
            <a href="{{ route('instructor.lectures', ['offering' => $offering->id]) }}"><x-icon name="clipboard" :size="17" /><x-t k="lectures" /></a>
            <a href="{{ route('instructor.payments.index', ['offering' => $offering->id]) }}"><x-icon name="wallet" :size="17" /><x-t k="payments" /></a>
            <a href="#course-roster"><x-icon name="users" :size="17" /><x-t k="roster" /></a>
        </nav>

        <section class="offering-detail-insights" aria-label="{{ __('ui.course_overview') }}">
            <article><span><x-icon name="users" :size="20" /></span><div><strong>{{ $activeEnrollments->count() }}</strong><small><x-t k="active_students" /></small></div></article>
            <article><span><x-icon name="graduation" :size="20" /></span><div><strong>{{ $activeGroups->count() }}</strong><small><x-t k="active_groups" /></small></div></article>
            <article><span><x-icon name="clipboard" :size="20" /></span><div><strong>{{ $activeLessons->count() }}</strong><small><x-t k="active_lessons" /></small></div></article>
            <article><span><x-icon name="calendar" :size="20" /></span><div><strong>{{ $offering->class_sessions_count }}</strong><small><x-t k="total_lectures" /></small></div></article>
        </section>

        @if($pendingTransfers > 0)
            <div class="offering-action-alert">
                <span><x-icon name="warning" :size="20" /></span>
                <div><strong><x-t k="transfer_requests" /></strong><small>{{ __('ui.pending_transfer_count', ['count' => $pendingTransfers]) }}</small></div>
                <a class="button secondary" href="#course-roster"><x-t k="review" /></a>
            </div>
        @endif

        <details class="panel offering-settings-card">
            <summary>
                <span><x-icon name="settings" :size="20" /></span>
                <div><strong><x-t k="course_settings" /></strong><small><x-t k="course_settings_hint" /></small></div>
                <i aria-hidden="true"></i>
            </summary>
            <div class="offering-settings-grid">
                <form method="POST" action="{{ route('instructor.offerings.update', $offering) }}" class="stack offering-form-box">
                    @csrf
                    @method('PUT')
                    <div class="offering-form-title"><x-icon name="book" :size="18" /><strong><x-t k="course_identity" /></strong></div>
                    <x-field name="title" label="title_en" :value="$offering->title" required maxlength="150" dir="ltr" />
                    <x-field name="title_ar" label="title_ar" :value="$offering->title_ar" required maxlength="150" dir="rtl" />
                    <button class="primary"><x-icon name="check" :size="17" /><x-t k="save" /></button>
                </form>

                <form method="POST" action="{{ route('instructor.offerings.status', $offering) }}" class="stack offering-form-box" @submit="if (!confirm($store.i18n.t('confirm_status'))) $event.preventDefault()">
                    @csrf
                    <div class="offering-form-title"><x-icon name="shield" :size="18" /><strong><x-t k="status_management" /></strong></div>
                    <p><x-t k="status_management_hint" /></p>
                    <x-select name="status" label="status">
                        @foreach(['draft', 'open', 'completed', 'archived'] as $status)
                            <option value="{{ $status }}" @selected($status === $offering->status) x-text="$store.i18n.t(@js($status))">{{ __('ui.'.$status) }}</option>
                        @endforeach
                    </x-select>
                    <x-field name="reason" id="status-reason" required maxlength="500" />
                    <button class="secondary"><x-icon name="check" :size="17" /><x-t k="save" /></button>
                </form>
            </div>
        </details>

        <section class="offering-section-block" aria-labelledby="course-structure-title">
            <div class="offering-section-heading">
                <span><x-icon name="book" :size="20" /></span>
                <div><h2 id="course-structure-title"><x-t k="course_structure" /></h2><p><x-t k="course_structure_hint" /></p></div>
            </div>

            <div class="offering-structure-grid">
                @foreach(['groups' => $offering->groups, 'lessons' => $offering->lessons] as $kind => $items)
                    <article class="panel offering-structure-card">
                        <header>
                            <span><x-icon :name="$kind === 'groups' ? 'users' : 'clipboard'" :size="20" /></span>
                            <div><h3><x-t :k="$kind" /></h3><small>{{ $items->whereNull('archived_at')->count() }} <x-t k="active" /></small></div>
                        </header>

                        <div class="offering-manage-list">
                            @forelse($items as $item)
                                <details class="offering-manage-item" @if($item->archived_at) data-archived="true" @endif>
                                    <summary>
                                        <span class="offering-item-number">{{ $kind === 'groups' ? mb_substr($item->display_name, 0, 1) : $item->position }}</span>
                                        <span class="offering-item-copy">
                                            <strong>{{ $kind === 'groups' ? $item->display_name : $item->display_title }}</strong>
                                            <small>
                                                @if($kind === 'groups')
                                                    {{ $item->active_enrollments_count }} <x-t k="students" />
                                                    @if($item->capacity) · {{ $item->capacity }} <x-t k="capacity_short" /> @endif
                                                @else
                                                    {{ $item->class_sessions_count }} <x-t k="lectures" />
                                                @endif
                                            </small>
                                        </span>
                                        @if($item->archived_at)<x-status value="archived" />@else<span class="offering-edit-label"><x-t k="edit" /></span>@endif
                                    </summary>

                                    @if(!$item->archived_at && $offering->status !== 'archived')
                                        <div class="offering-item-editor">
                                            <form method="POST" action="{{ route('instructor.'.$kind.'.update', [$offering, $item]) }}" class="stack">
                                                @csrf
                                                @method('PUT')
                                                @if($kind === 'groups')
                                                    <div class="grid two">
                                                        <x-field name="name" :id="'group-name-'.$item->id" label="name_en" :value="$item->name" required maxlength="80" dir="ltr" />
                                                        <x-field name="name_ar" :id="'group-name-ar-'.$item->id" label="name_ar" :value="$item->name_ar" required maxlength="80" dir="rtl" />
                                                    </div>
                                                    <x-field name="capacity" :id="'capacity-'.$item->id" type="number" min="1" max="10000" :value="$item->capacity" />
                                                @else
                                                    <div class="grid two">
                                                        <x-field name="title" :id="'lesson-title-'.$item->id" label="title_en" :value="$item->title" required maxlength="150" dir="ltr" />
                                                        <x-field name="title_ar" :id="'lesson-title-ar-'.$item->id" label="title_ar" :value="$item->title_ar" required maxlength="150" dir="rtl" />
                                                    </div>
                                                    <x-field name="position" :id="'position-'.$item->id" type="number" min="1" max="1000" :value="$item->position" required />
                                                @endif
                                                <button class="primary"><x-icon name="check" :size="17" /><x-t k="save" /></button>
                                            </form>
                                            <form method="POST" action="{{ route('instructor.children.archive', [$offering, $kind, $item->id]) }}" @submit="if (!confirm($store.i18n.t('confirm_archive'))) $event.preventDefault()">
                                                @csrf
                                                <button class="danger"><x-icon name="close" :size="16" /><x-t k="archive" /></button>
                                            </form>
                                        </div>
                                    @endif
                                </details>
                            @empty
                                <div class="offering-list-empty"><x-icon :name="$kind === 'groups' ? 'users' : 'clipboard'" :size="22" /><span><x-t k="empty" /></span></div>
                            @endforelse
                        </div>

                        @if($offering->status !== 'archived' && ($kind === 'lessons' || $offering->uses_groups))
                            <details class="offering-add-item">
                                <summary><x-icon name="plus" :size="17" /><span><x-t :k="$kind === 'groups' ? 'add_group' : 'add_lesson'" /></span></summary>
                                <form method="POST" action="{{ route('instructor.'.$kind.'.store', $offering) }}" class="stack">
                                    @csrf
                                    @if($kind === 'groups')
                                        <div class="grid two">
                                            <x-field name="name" id="new-group-name" label="name_en" required maxlength="80" dir="ltr" />
                                            <x-field name="name_ar" id="new-group-name-ar" label="name_ar" required maxlength="80" dir="rtl" />
                                        </div>
                                        <x-field name="capacity" type="number" min="1" max="10000" />
                                    @else
                                        <div class="grid two">
                                            <x-field name="title" id="new-lesson-title" label="title_en" required maxlength="150" dir="ltr" />
                                            <x-field name="title_ar" id="new-lesson-title-ar" label="title_ar" required maxlength="150" dir="rtl" />
                                        </div>
                                        <x-field name="position" type="number" min="1" max="1000" :value="($offering->lessons->max('position') ?? 0) + 1" required />
                                    @endif
                                    <button class="primary"><x-icon name="plus" :size="17" /><x-t k="create" /></button>
                                </form>
                            </details>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>

        <section id="course-materials" class="offering-section-block" aria-labelledby="course-materials-title">
            <div class="offering-section-heading">
                <span><x-icon name="files" :size="20" /></span>
                <div><h2 id="course-materials-title"><x-t k="course_materials" /></h2><p><x-t k="materials_management_hint" /></p></div>
                <strong>{{ $offering->materials->where('status', 'published')->count() }}</strong>
            </div>

            <div class="material-management-layout">
                <article class="panel material-library-card">
                    <header class="material-library-summary">
                        <div><strong><x-t k="material_library" /></strong><small>{{ $offering->materials->count() }} <x-t k="materials" /></small></div>
                    </header>
                    <div class="material-manage-list">
                        @forelse($offering->materials as $material)
                            <details class="material-manage-card" data-status="{{ $material->status }}">
                                <summary>
                                    <span class="material-type-icon"><x-icon :name="$material->type === 'youtube' ? 'play' : ($material->type === 'image' ? 'image' : 'file')" :size="20" /></span>
                                    <span class="material-manage-copy">
                                        <strong>{{ $material->display_title }}</strong>
                                        <small><x-t :k="$material->type" /> · {{ $material->groups->pluck('display_name')->join('، ') }}</small>
                                    </span>
                                    <x-status :value="$material->status" />
                                </summary>
                                <div class="material-manage-details">
                                    @if($material->display_description)<p>{{ $material->display_description }}</p>@endif
                                    @if($material->lesson)<div class="material-lesson-tag"><x-icon name="clipboard" :size="14" />{{ $material->lesson->display_title }}</div>@endif
                                    @if($material->type !== 'youtube' && $material->file_path)
                                        <a class="button secondary" href="{{ route('course-materials.file', $material) }}" target="_blank" rel="noopener"><x-icon name="view" :size="16" /><x-t k="open_file" /></a>
                                    @endif
                                    @if($material->status !== 'archived' && $offering->status !== 'archived')
                                        @include('instructor.partials.material-form', ['material' => $material])
                                        <form method="POST" action="{{ route('instructor.materials.archive', [$offering, $material]) }}" @submit="if (!confirm($store.i18n.t('confirm_archive'))) $event.preventDefault()">
                                            @csrf
                                            <button class="danger"><x-icon name="close" :size="16" /><x-t k="archive" /></button>
                                        </form>
                                    @endif
                                </div>
                            </details>
                        @empty
                            <div class="offering-list-empty"><x-icon name="files" :size="24" /><span><x-t k="no_materials" /></span></div>
                        @endforelse
                    </div>
                </article>

                @if($offering->status !== 'archived')
                    <details class="panel material-create-card" open>
                        <summary><span><x-icon name="plus" :size="18" /></span><div><strong><x-t k="add_material" /></strong><small><x-t k="add_material_hint" /></small></div></summary>
                        @include('instructor.partials.material-form', ['material' => null])
                    </details>
                @endif
            </div>
        </section>

        @if($offering->status === 'open')
            <section class="offering-section-block" aria-labelledby="enrollment-tools-title">
                <div class="offering-section-heading">
                    <span><x-icon name="users" :size="20" /></span>
                    <div><h2 id="enrollment-tools-title"><x-t k="enrollment_tools" /></h2><p><x-t k="enrollment_tools_hint" /></p></div>
                </div>

                <div class="offering-enrollment-grid">
                    <article class="panel offering-tool-card">
                        <header><span><x-icon name="user" :size="20" /></span><div><h3><x-t k="manual_enroll" /></h3><small><x-t k="manual_enroll_hint" /></small></div></header>
                        <form method="POST" action="{{ route('instructor.enrollments.store', $offering) }}" class="stack">
                            @csrf
                            <x-field name="identifier" required maxlength="80" dir="ltr" />
                            <x-select name="group_id" label="group" required>
                                @foreach($activeGroups as $group)<option value="{{ $group->id }}">{{ $group->display_name }}</option>@endforeach
                            </x-select>
                            <button class="primary"><x-icon name="plus" :size="17" /><x-t k="enroll" /></button>
                        </form>
                    </article>

                    <article class="panel offering-tool-card">
                        <header><span><x-icon name="message" :size="20" /></span><div><h3><x-t k="invitations" /></h3><small><x-t k="invitation_hint" /></small></div></header>
                        <form method="POST" action="{{ route('instructor.invitations.store', $offering) }}" class="offering-invite-form">
                            @csrf
                            <x-select name="group_id" id="invitation-group" label="group" required>
                                @foreach($activeGroups as $group)<option value="{{ $group->id }}">{{ $group->display_name }}</option>@endforeach
                            </x-select>
                            <button class="primary"><x-icon name="plus" :size="17" /><x-t k="create_invitation" /></button>
                        </form>

                        <div class="offering-invite-list">
                            @forelse($activeInvitations as $invite)
                                <div>
                                    <span><strong>{{ $invite->group->display_name }}</strong><small><x-t k="expires" /> {{ $invite->expires_at->format('Y-m-d') }}</small></span>
                                    <form method="POST" action="{{ route('instructor.invitations.revoke', $invite) }}">@csrf<button class="secondary"><x-t k="revoke" /></button></form>
                                </div>
                            @empty
                                <p><x-t k="no_active_invitations" /></p>
                            @endforelse
                        </div>
                    </article>
                </div>
            </section>
        @endif

        <section id="course-roster" class="panel offering-roster-panel">
            <div class="offering-section-heading">
                <span><x-icon name="users" :size="20" /></span>
                <div><h2><x-t k="roster" /></h2><p><x-t k="course_roster_hint" /></p></div>
                <strong>{{ $offering->enrollments->count() }}</strong>
            </div>

            <div class="offering-roster-list">
                @forelse($offering->enrollments as $enrollment)
                    <details class="offering-roster-item">
                        <summary>
                            <span class="offering-student-avatar">{{ mb_substr($enrollment->student->name, 0, 1) }}</span>
                            <span class="offering-student-copy">
                                <strong>{{ $enrollment->student->name }}</strong>
                                <small><bdi>{{ $enrollment->student->student_code }}</bdi> · {{ $enrollment->group?->display_name }}</small>
                            </span>
                            <x-status :value="$enrollment->status" />
                        </summary>

                        <div class="offering-roster-details">
                            <div class="offering-contact-strip">
                                <span><x-icon name="user" :size="16" /><bdi>{{ $enrollment->student->phone }}</bdi></span>
                                <span><x-icon name="message" :size="16" /><bdi>{{ $enrollment->student->profile?->whatsapp_phone }}</bdi></span>
                            </div>

                            <div class="grid two">
                                <form method="POST" action="{{ route('instructor.enrollments.update', $enrollment) }}" class="stack offering-form-box" @submit="if (!confirm($store.i18n.t('confirm_enrollment'))) $event.preventDefault()">
                                    @csrf
                                    @method('PUT')
                                    <div class="offering-form-title"><x-icon name="graduation" :size="18" /><strong><x-t k="enrollment_actions" /></strong></div>
                                    <x-select name="action" :id="'enrollment-action-'.$enrollment->id" label="action">
                                        @foreach($enrollment->status === 'enrolled' ? ['transfer', 'withdraw'] : ['restore'] as $action)
                                            <option value="{{ $action }}" x-text="$store.i18n.t(@js($action))">{{ __('ui.'.$action) }}</option>
                                        @endforeach
                                    </x-select>
                                    <x-select name="group_id" :id="'enrollment-group-'.$enrollment->id" label="group">
                                        @foreach($activeGroups as $group)<option value="{{ $group->id }}" @selected($group->id === $enrollment->group_id)>{{ $group->display_name }}</option>@endforeach
                                    </x-select>
                                    <x-field name="reason" :id="'enrollment-reason-'.$enrollment->id" required maxlength="500" />
                                    <button class="primary"><x-icon name="check" :size="17" /><x-t k="save" /></button>
                                </form>

                                <form method="POST" action="{{ route('instructor.enrollments.account', $enrollment) }}" class="stack offering-form-box" @submit="if (!confirm($store.i18n.t('confirm_account'))) $event.preventDefault()">
                                    @csrf
                                    <div class="offering-form-title"><x-icon name="shield" :size="18" /><strong><x-t k="account_actions" /></strong><x-status :value="$enrollment->student->status" /></div>
                                    <p><x-t k="account_impact" /></p>
                                    <x-select name="action" :id="'account-action-'.$enrollment->id" label="action">
                                        @foreach(['reset', 'suspend', 'reactivate'] as $action)
                                            <option value="{{ $action }}" x-text="$store.i18n.t(@js($action))">{{ __('ui.'.$action) }}</option>
                                        @endforeach
                                    </x-select>
                                    <x-field name="reason" :id="'account-reason-'.$enrollment->id" required maxlength="500" />
                                    <button class="secondary"><x-icon name="check" :size="17" /><x-t k="save" /></button>
                                </form>
                            </div>

                            @foreach($enrollment->transferRequests->where('status', 'pending') as $transfer)
                                <div class="offering-transfer-request">
                                    <span><x-icon name="warning" :size="19" /></span>
                                    <div><h3><x-t k="transfer_requests" /> · {{ $transfer->group->display_name }}</h3><p>{{ $transfer->reason }}</p></div>
                                    <form method="POST" action="{{ route('instructor.transfers.review', $transfer) }}" class="stack">
                                        @csrf
                                        <x-field name="reason" :id="'review-reason-'.$transfer->id" required maxlength="500" />
                                        <div class="row"><button class="primary" name="decision" value="approved"><x-t k="approved" /></button><button class="secondary" name="decision" value="rejected"><x-t k="rejected" /></button></div>
                                    </form>
                                </div>
                            @endforeach
                        </div>
                    </details>
                @empty
                    <div class="offering-list-empty"><x-icon name="users" :size="24" /><strong><x-t k="no_students" /></strong><span><x-t k="no_students_hint" /></span></div>
                @endforelse
            </div>
        </section>
    </main>
</x-layout>
