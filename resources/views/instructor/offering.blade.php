<x-layout title="course">
<div class="hero"><div><div class="eyebrow">{{ $offering->semester->name }} · {{ $offering->course->code }}</div><h1>{{ $offering->title }}</h1><p>{{ $offering->timezone }} · {{ number_format($offering->fee_minor / 100,2) }} {{ $offering->currency }}</p></div><x-status :value="$offering->status" /></div>
@if(session('invitation_url'))<div class="alert"><x-t k="invitation" />: <a dir="ltr" href="{{ session('invitation_url') }}">{{ session('invitation_url') }}</a></div>@endif
@if(session('temporary_password'))<div class="alert"><p><x-t k="temporary_password" /></p><strong dir="ltr">{{ session('temporary_password') }}</strong></div>@endif
<section class="panel"><details><summary><x-t k="settings" /></summary><div class="grid two">
<form method="POST" action="{{ route('instructor.offerings.update',$offering) }}" class="stack">@csrf @method('PUT')
<x-field name="title" :value="$offering->title" required /><x-field name="timezone" :value="$offering->timezone" required />
<div class="grid two"><x-field name="fee" :value="number_format($offering->fee_minor/100,2,'.','')" inputmode="decimal" required /><x-select name="currency">@foreach(['EGP','USD','SAR'] as $c)<option @selected($c === $offering->currency)>{{ $c }}</option>@endforeach</x-select></div>
<x-check name="self_enrollment" :checked="$offering->self_enrollment" /><button class="primary"><x-t k="save" /></button></form>
<form method="POST" action="{{ route('instructor.offerings.status',$offering) }}" class="stack" @submit="if (!confirm($store.i18n.t('confirm_status'))) $event.preventDefault()">@csrf
<x-select name="status">@foreach(['draft','open','completed','archived'] as $status)<option value="{{ $status }}" @selected($status === $offering->status) x-text="$store.i18n.t(@js($status))">{{ __('ui.'.$status) }}</option>@endforeach</x-select>
<x-field name="reason" id="status-reason" required maxlength="500" /><p class="muted"><x-t k="confirm_status" /></p><button class="secondary"><x-t k="save" /></button></form>
</div></details></section>
<div class="grid two">
@foreach(['groups'=>$offering->groups,'lessons'=>$offering->lessons] as $kind=>$items)
<section class="panel"><h2><x-t :k="$kind" /></h2>
@foreach($items as $item)<details><summary>{{ $kind === 'groups' ? $item->name : $item->position.'. '.$item->title }} @if($item->archived_at)<x-status value="archived" />@endif</summary>
@if(!$item->archived_at && $offering->status !== 'archived')
<form method="POST" action="{{ route('instructor.'.$kind.'.update',[$offering,$item]) }}" class="stack">@csrf @method('PUT')
@if($kind === 'groups')<x-field name="name" :id="'group-name-'.$item->id" :value="$item->name" required /><x-field name="capacity" :id="'capacity-'.$item->id" type="number" min="1" :value="$item->capacity" />
@else<x-field name="title" :id="'lesson-title-'.$item->id" :value="$item->title" required /><x-field name="position" :id="'position-'.$item->id" type="number" min="1" :value="$item->position" required />@endif
<button class="primary"><x-t k="save" /></button></form>
<form method="POST" action="{{ route('instructor.children.archive',[$offering,$kind,$item->id]) }}" class="mt-3" @submit="if (!confirm($store.i18n.t('confirm_archive'))) $event.preventDefault()">@csrf<button class="secondary danger"><x-t k="archive" /></button></form>
@endif</details>@endforeach
@if($offering->status !== 'archived' && ($kind === 'lessons' || $offering->uses_groups))<hr class="divider"><form method="POST" action="{{ route('instructor.'.$kind.'.store',$offering) }}" class="stack">@csrf
@if($kind === 'groups')<x-field name="name" id="new-group-name" required maxlength="80" /><x-field name="capacity" type="number" min="1" />@else<x-field name="title" id="new-lesson-title" required maxlength="150" /><x-field name="position" type="number" min="1" :value="($offering->lessons->max('position') ?? 0)+1" required />@endif
<button class="primary"><x-t k="create" /></button></form>@endif
</section>@endforeach</div>
@php($activeGroups = $offering->groups->whereNull('archived_at'))
@if($offering->status === 'open')<div class="grid two">
<section class="panel"><h2><x-t k="manual_enroll" /></h2><form method="POST" action="{{ route('instructor.enrollments.store',$offering) }}" class="stack">@csrf<x-field name="identifier" required dir="ltr" /><x-select name="group_id" label="group" required>@foreach($activeGroups as $group)<option value="{{ $group->id }}">{{ $group->name }}</option>@endforeach</x-select><button class="primary"><x-t k="enroll" /></button></form></section>
<section class="panel"><h2><x-t k="invitations" /></h2><p class="muted"><x-t k="invitation_hint" /></p><form method="POST" action="{{ route('instructor.invitations.store',$offering) }}" class="stack">@csrf<x-select name="group_id" id="invitation-group" label="group" required>@foreach($activeGroups as $group)<option value="{{ $group->id }}">{{ $group->name }}</option>@endforeach</x-select><button class="primary"><x-t k="create_invitation" /></button></form>
@foreach($offering->invitations->whereNull('revoked_at')->filter(fn($i) => $i->expires_at->isFuture()) as $invite)<div class="list-item row between"><span class="muted">{{ $invite->group->name }} · <x-t k="expires" /> {{ $invite->expires_at->format('Y-m-d') }}</span><form method="POST" action="{{ route('instructor.invitations.revoke',$invite) }}">@csrf<button class="secondary"><x-t k="revoke" /></button></form></div>@endforeach</section>
</div>@endif
<section class="panel"><h2><x-t k="roster" /> <span class="muted">({{ $offering->enrollments->count() }})</span></h2>
@forelse($offering->enrollments as $enrollment)
<details><summary><span class="row between"><span>{{ $enrollment->student->name }} · <bdi>{{ $enrollment->student->student_code }}</bdi></span><x-status :value="$enrollment->status" /></span></summary>
<p class="muted"><bdi>{{ $enrollment->student->phone }}</bdi> · <x-t k="whatsapp_phone" />: <bdi>{{ $enrollment->student->profile?->whatsapp_phone }}</bdi> · {{ $enrollment->group?->name }} · {{ number_format($enrollment->agreed_fee_minor/100,2) }} {{ $enrollment->currency }}</p>
<div class="grid two"><form method="POST" action="{{ route('instructor.enrollments.update',$enrollment) }}" class="stack" @submit="if (!confirm($store.i18n.t('confirm_enrollment'))) $event.preventDefault()">@csrf @method('PUT')
<x-select name="action" :id="'enrollment-action-'.$enrollment->id" label="action">@foreach($enrollment->status === 'enrolled' ? ['transfer','withdraw'] : ['restore'] as $action)<option value="{{ $action }}" x-text="$store.i18n.t(@js($action))">{{ __('ui.'.$action) }}</option>@endforeach</x-select>
<x-select name="group_id" :id="'enrollment-group-'.$enrollment->id" label="group">@foreach($activeGroups as $group)<option value="{{ $group->id }}" @selected($group->id === $enrollment->group_id)>{{ $group->name }}</option>@endforeach</x-select>
<x-field name="reason" :id="'enrollment-reason-'.$enrollment->id" required maxlength="500" /><button class="primary"><x-t k="save" /></button></form>
<form method="POST" action="{{ route('instructor.enrollments.account',$enrollment) }}" class="stack" @submit="if (!confirm($store.i18n.t('confirm_account'))) $event.preventDefault()">@csrf
<h3><x-t k="account_actions" /></h3><x-status :value="$enrollment->student->status" /><p class="muted"><x-t k="account_impact" /></p>
<x-select name="action" :id="'account-action-'.$enrollment->id" label="action">@foreach(['reset','suspend','reactivate'] as $action)<option value="{{ $action }}" x-text="$store.i18n.t(@js($action))">{{ __('ui.'.$action) }}</option>@endforeach</x-select>
<x-field name="reason" :id="'account-reason-'.$enrollment->id" required maxlength="500" /><button class="secondary"><x-t k="save" /></button></form></div>
@foreach($enrollment->transferRequests->where('status','pending') as $transfer)<div class="alert mt-5"><h3><x-t k="transfer_requests" /> · {{ $transfer->group->name }}</h3><p>{{ $transfer->reason }}</p><form method="POST" action="{{ route('instructor.transfers.review',$transfer) }}" class="stack">@csrf<x-field name="reason" :id="'review-reason-'.$transfer->id" required maxlength="500" /><div class="row"><button class="primary" name="decision" value="approved"><x-t k="approved" /></button><button class="secondary" name="decision" value="rejected"><x-t k="rejected" /></button></div></form></div>@endforeach
</details>@empty<p class="muted"><x-t k="empty" /></p>@endforelse</section>
</x-layout>
