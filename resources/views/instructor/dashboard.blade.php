<x-layout title="dashboard">
<div class="hero"><div><div class="eyebrow"><x-t k="instructor" /></div><h1><x-t k="welcome" />, {{ auth()->user()->name }}</h1><p><x-t k="overview_hint" /></p></div><div><div class="eyebrow"><x-t k="week_label" /></div><x-t k="week" /></div></div>
<div class="grid three mb-6">@foreach(['semesters'=>$semesterCount,'courses'=>$courseCount,'students'=>$studentCount] as $key=>$count)<div class="card"><span class="muted"><x-t :k="$key" /></span><strong class="stat">{{ $count }}</strong></div>@endforeach</div>
<div class="row between mb-4"><h2 class="mb-0"><x-t k="offerings" /></h2><a class="button" href="{{ route('instructor.offerings') }}"><x-t k="new_offering" /></a></div>
@if($offerings->isEmpty())<section class="empty"><h2><x-t k="start_setup" /></h2><p class="muted"><x-t k="setup_hint" /></p><a class="button" href="{{ route('instructor.setup') }}"><x-t k="setup" /></a></section>
@else<div class="grid two">@foreach($offerings as $offering)<article class="card"><div class="row between"><span class="muted">{{ $offering->semester->name }}</span><x-status :value="$offering->status" /></div><h3>{{ $offering->title }}</h3><p class="muted">{{ $offering->enrollments_count }} <x-t k="students" /></p><a class="button secondary" href="{{ route('instructor.offerings.show',$offering) }}"><x-t k="view_course" /></a></article>@endforeach</div>@endif
</x-layout>
