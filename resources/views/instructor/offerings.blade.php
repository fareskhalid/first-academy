<x-layout title="offerings">
    @php
        $openOfferings = $offerings->where('status', 'open')->count();
        $draftOfferings = $offerings->where('status', 'draft')->count();
        $activeStudents = $offerings->sum('active_enrollments_count');
    @endphp

    <main class="offering-page">
        <header class="offering-hero">
            <span class="offering-hero-icon"><x-icon name="book" :size="31" /></span>
            <div class="offering-hero-copy">
                <div class="eyebrow"><x-t k="course_workspace" /></div>
                <h1><x-t k="offerings" /></h1>
                <p><x-t k="offerings_management_hint" /></p>
            </div>
            <a class="button offering-setup-button" href="{{ route('instructor.setup') }}">
                <x-icon name="settings" :size="17" />
                <x-t k="setup" />
            </a>
        </header>

        <section class="offering-insights" aria-label="{{ __('ui.offerings_overview') }}">
            <article>
                <span><x-icon name="book" :size="20" /></span>
                <div><strong>{{ $offerings->count() }}</strong><small><x-t k="total_offerings" /></small></div>
            </article>
            <article>
                <span><x-icon name="check" :size="20" /></span>
                <div><strong>{{ $openOfferings }}</strong><small><x-t k="open_offerings" /></small></div>
            </article>
            <article>
                <span><x-icon name="clipboard" :size="20" /></span>
                <div><strong>{{ $draftOfferings }}</strong><small><x-t k="draft_offerings" /></small></div>
            </article>
            <article>
                <span><x-icon name="users" :size="20" /></span>
                <div><strong>{{ $activeStudents }}</strong><small><x-t k="active_students" /></small></div>
            </article>
        </section>

        <div class="offering-index-layout">
            <section class="offering-catalog" aria-labelledby="offering-catalog-title">
                <div class="offering-section-heading">
                    <span><x-icon name="book" :size="20" /></span>
                    <div>
                        <h2 id="offering-catalog-title"><x-t k="course_catalog" /></h2>
                        <p><x-t k="course_catalog_hint" /></p>
                    </div>
                    <strong>{{ $offerings->count() }}</strong>
                </div>

                <div class="offering-card-grid">
                    @forelse($offerings as $offering)
                        <article class="offering-card" data-status="{{ $offering->status }}">
                            <div class="offering-card-top">
                                <span class="offering-card-icon"><x-icon name="graduation" :size="22" /></span>
                                <div>
                                    <small>{{ $offering->semester->display_name }}</small>
                                    <h3>{{ $offering->display_title }}</h3>
                                </div>
                                <x-status :value="$offering->status" />
                            </div>

                            <div class="offering-code-row">
                                <bdi>{{ $offering->course->code }}</bdi>
                                <span>{{ $offering->course->display_name }}</span>
                            </div>

                            <div class="offering-card-metrics">
                                <div><x-icon name="users" :size="16" /><strong>{{ $offering->active_enrollments_count }}</strong><small><x-t k="students" /></small></div>
                                <div><x-icon name="graduation" :size="16" /><strong>{{ $offering->active_groups_count }}</strong><small><x-t k="groups" /></small></div>
                                <div><x-icon name="clipboard" :size="16" /><strong>{{ $offering->active_lessons_count }}</strong><small><x-t k="lessons" /></small></div>
                                <div><x-icon name="calendar" :size="16" /><strong>{{ $offering->class_sessions_count }}</strong><small><x-t k="lectures" /></small></div>
                            </div>

                            <a class="button secondary offering-card-action" href="{{ route('instructor.offerings.show', $offering) }}">
                                <x-t k="manage_course" />
                                <x-icon name="arrow" :size="17" />
                            </a>
                        </article>
                    @empty
                        <div class="offering-empty">
                            <span><x-icon name="book" :size="26" /></span>
                            <h3><x-t k="no_courses" /></h3>
                            <p><x-t k="create_offering_hint" /></p>
                        </div>
                    @endforelse
                </div>
            </section>

            <aside class="panel offering-create-card">
                <div class="offering-create-heading">
                    <span><x-icon name="plus" :size="21" /></span>
                    <div>
                        <h2><x-t k="new_offering" /></h2>
                        <p><x-t k="create_offering_hint" /></p>
                    </div>
                </div>

                @if($courses->isEmpty() || $semesters->isEmpty())
                    <div class="offering-setup-empty">
                        <x-icon name="warning" :size="24" />
                        <p><x-t k="setup_hint" /></p>
                        <a class="button" href="{{ route('instructor.setup') }}"><x-t k="setup" /></a>
                    </div>
                @else
                    <form method="POST" action="{{ route('instructor.offerings.store') }}" class="stack offering-create-form">
                        @csrf
                        <div class="offering-form-step">
                            <span>1</span>
                            <strong><x-t k="academic_context" /></strong>
                        </div>
                        <x-select name="semester_id" label="semester" required>
                            @foreach($semesters as $item)<option value="{{ $item->id }}">{{ $item->display_name }}</option>@endforeach
                        </x-select>
                        <x-select name="course_id" label="course" required>
                            @foreach($courses as $item)<option value="{{ $item->id }}">{{ $item->display_name }}</option>@endforeach
                        </x-select>

                        <div class="offering-form-step">
                            <span>2</span>
                            <strong><x-t k="course_identity" /></strong>
                        </div>
                        <x-field name="title" label="title_en" required maxlength="150" dir="ltr" />
                        <x-field name="title_ar" label="title_ar" required maxlength="150" dir="rtl" />
                        <div class="offering-group-choice"><x-check name="uses_groups" /></div>

                        <button class="primary offering-create-submit">
                            <x-icon name="plus" :size="18" />
                            <x-t k="create_offering" />
                        </button>
                    </form>
                @endif
            </aside>
        </div>
    </main>
</x-layout>
