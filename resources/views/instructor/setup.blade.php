<x-layout title="setup">
<div class="hero"><div><h1><x-t k="setup" /></h1><p><x-t k="setup_hint" /></p></div></div>
<div class="grid two">
@foreach(['semesters'=>[$semesters,$editSemester],'courses'=>[$courses,$editCourse]] as $kind=>[$items,$editing])
<section class="panel"><h2><x-t :k="$kind" /></h2>
<form method="POST" action="{{ $editing ? route('instructor.setup.update',[$kind,$editing->id]) : route('instructor.setup.store',$kind) }}" class="stack">@csrf @if($editing) @method('PUT') @endif
<div class="grid two"><x-field name="name" :id="$kind.'-name'" label="name_en" :value="$editing?->name" required maxlength="150" dir="ltr" /><x-field name="name_ar" :id="$kind.'-name-ar'" label="name_ar" :value="$editing?->name_ar" required maxlength="150" dir="rtl" /></div>
@if($kind === 'semesters')
<div class="grid two"><x-field name="starts_on" type="date" :value="$editing?->starts_on?->format('Y-m-d')" required /><x-field name="ends_on" type="date" :value="$editing?->ends_on?->format('Y-m-d')" required /></div>
@else
<x-field name="code" :value="$editing?->code" required maxlength="40" dir="ltr" />
<div class="grid two"><div class="field"><label for="description"><x-t k="description_en" /></label><textarea id="description" name="description" maxlength="3000" dir="ltr">{{ old('description',$editing?->description) }}</textarea></div><div class="field"><label for="description_ar"><x-t k="description_ar" /></label><textarea id="description_ar" name="description_ar" maxlength="3000" dir="rtl">{{ old('description_ar',$editing?->description_ar) }}</textarea></div></div>
@endif
<div class="row"><button class="primary"><x-t :k="$editing ? 'save' : 'create'" /></button>@if($editing)<a href="{{ route('instructor.setup') }}"><x-t k="cancel" /></a>@endif</div>
</form><hr class="divider">
@forelse($items as $item)<div class="list-item"><div class="row between"><strong>{{ $item->display_name }}</strong>@if($item->archived_at)<x-status value="archived" />@else<div class="row"><a href="{{ route('instructor.setup',[$kind === 'semesters' ? 'semester' : 'course' => $item->id]) }}"><x-t k="edit" /></a><form method="POST" action="{{ route('instructor.setup.archive',[$kind,$item->id]) }}" @submit="if (!confirm($store.i18n.t('confirm_archive'))) $event.preventDefault()">@csrf<button class="secondary danger"><x-t k="archive" /></button></form></div>@endif</div>@if($kind === 'semesters')<p class="muted">{{ $item->starts_on->format('Y-m-d') }} — {{ $item->ends_on->format('Y-m-d') }}</p>@else<p class="muted">{{ $item->code }}</p>@endif</div>@empty<p class="muted"><x-t k="empty" /></p>@endforelse
</section>@endforeach
</div></x-layout>
