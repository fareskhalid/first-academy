<x-layout title="attendance_qr">
<div class="qr-stage" aria-live="polite"><div class="eyebrow"><x-t k="attendance_qr" /></div><h1>{{ $classSession->offering->title }}</h1><p>{{ $classSession->lesson->title }} · {{ $classSession->group->name }} · {{ $classSession->scheduled_start_at->timezone($classSession->timezone)->format('Y-m-d H:i') }}</p><img class="qr-image" src="{{ $qrDataUri }}" alt="{{ __('ui.attendance_qr') }}"><div class="short-code"><span><x-t k="short_code" /></span><strong dir="ltr">{{ $shortCode }}</strong></div><p class="muted"><x-t k="qr_rotates" /> · <x-t k="expires" /> {{ $credential->expires_at->locale(app()->getLocale())->diffForHumans() }}</p></div>
<div class="row"><a class="button secondary" href="{{ route('instructor.sessions.show',$classSession) }}"><x-t k="manage_session" /></a></div>
<script>setTimeout(() => window.location.reload(), {{ (int) config('academic.qr_rotate_seconds',30) * 1000 }});</script>
</x-layout>
