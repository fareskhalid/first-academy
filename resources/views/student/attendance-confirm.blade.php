<x-layout title="record_attendance">
<div class="student-page student-attendance-page">
<header class="student-attendance-hero">
    <span><x-icon name="qr" :size="27" /></span>
    <div><p class="eyebrow"><x-t k="attendance_checkin" /></p><h1>{{ $session->offering->display_title }}</h1><strong>{{ $session->lesson->display_title }}</strong></div>
</header>
<div class="student-attendance-context"><span><x-icon name="users" :size="17" />{{ $session->group->display_name }}</span><span><x-icon name="calendar" :size="17" /><bdi>{{ $session->scheduled_start_at->timezone($session->timezone)->locale(app()->getLocale())->translatedFormat('Y-m-d · h:i A') }}</bdi></span></div>
<section class="student-confirm-card">
    <div class="student-confirm-icon"><x-icon name="check" :size="27" /></div>
    <h2><x-t k="record_attendance" /></h2><p><x-t k="record_attendance_hint" /></p>
    <form method="POST" action="{{ $intent ? route('attendance.record', $intent) : route('attendance.start', $token) }}" x-data="{ submitting: false }" @submit="submitting = true">@csrf
        <button class="primary w-full" :disabled="submitting"><span x-show="!submitting"><x-icon name="check" :size="18" /><x-t k="record_attendance" /></span><span x-cloak x-show="submitting"><x-t k="please_wait" /></span></button>
    </form>
</section>
</div>
</x-layout>
