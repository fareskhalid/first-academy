<x-layout title="offerings"><div class="hero"><div><h1><x-t k="offerings" /></h1><p><x-t k="overview_hint" /></p></div></div>
<div class="grid two"><section><h2><x-t k="offerings" /></h2><div class="stack">@forelse($offerings as $offering)<article class="card"><div class="row between"><span class="muted">{{ $offering->semester->name }}</span><x-status :value="$offering->status" /></div><h3>{{ $offering->title }}</h3><p class="muted">{{ $offering->course->code }} · {{ $offering->enrollments_count }} <x-t k="students" /></p><a class="button secondary" href="{{ route('instructor.offerings.show',$offering) }}"><x-t k="view_course" /></a></article>@empty<div class="empty"><x-t k="no_courses" /></div>@endforelse</div></section>
<section class="panel"><h2><x-t k="new_offering" /></h2>
@if($courses->isEmpty() || $semesters->isEmpty())<p><x-t k="setup_hint" /></p><a class="button" href="{{ route('instructor.setup') }}"><x-t k="setup" /></a>
@else<form method="POST" action="{{ route('instructor.offerings.store') }}" class="stack">@csrf
<x-select name="semester_id" label="semester" required>@foreach($semesters as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach</x-select>
<x-select name="course_id" label="course" required>@foreach($courses as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach</x-select>
<x-field name="title" required maxlength="150" />
<x-field name="timezone" value="Africa/Cairo" required dir="ltr" />
<div class="grid two"><x-field name="fee" value="0.00" inputmode="decimal" required /><x-select name="currency"><option>EGP</option><option>USD</option><option>SAR</option></x-select></div>
<x-check name="uses_groups" /><x-check name="self_enrollment" :checked="true" /><button class="primary"><x-t k="create" /></button>
</form>@endif</section></div></x-layout>
