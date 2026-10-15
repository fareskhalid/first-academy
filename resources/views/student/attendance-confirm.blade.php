<x-layout title="record_attendance">
<div class="student-page student-attendance-page">
<header class="student-attendance-hero">
    <span><x-icon name="qr" :size="27" /></span>
    <div><p class="eyebrow"><x-t k="attendance_checkin" /></p><h1>{{ $session->offering->display_title }}</h1><strong>{{ $session->lesson->display_title }}</strong></div>
</header>
<div class="student-attendance-context"><span><x-icon name="users" :size="17" />{{ $session->group->display_name }}</span><span><x-icon name="calendar" :size="17" /><bdi>{{ $session->scheduled_start_at->timezone($session->timezone)->locale(app()->getLocale())->translatedFormat('Y-m-d · h:i A') }}</bdi></span></div>
<section class="student-confirm-card">
    <div class="student-confirm-icon"><x-icon name="check" :size="27" /></div>
    <h2><x-t k="confirm_attendance" /></h2><p><x-t k="confirm_attendance_hint" /></p>
    @if($intent)
    <form method="POST" action="{{ route('attendance.record',$intent) }}" x-data="{busy:false,async submit(){this.busy=true;try{const response=await fetch(this.$el.action,{method:'POST',body:new FormData(this.$el),headers:{'Accept':'text/html'}});window.location.assign(response.url || @js(route('attendance.intent',$intent)));}catch(error){this.busy=false;this.$dispatch('course:toast',{message:@js(__('ui.attendance_not_confirmed')),type:'error',timeout:0,actionUrl:@js(route('attendance.intent',$intent)),actionLabel:@js(__('ui.check_attendance_status'))});}}}" @submit.prevent="submit">@csrf
        <button class="primary w-full" :disabled="busy"><span x-show="!busy"><x-icon name="check" :size="18" /><x-t k="record_attendance" /></span><span x-cloak x-show="busy"><x-t k="please_wait" /></span></button>
    </form>
    <p class="student-intent-expiry"><x-t k="intent_expires" /> {{ $intent->expires_at->locale(app()->getLocale())->diffForHumans() }}</p>
    @else
    <form method="POST" action="{{ route('attendance.start',$token) }}" x-data="{ submitting: false }" @submit="submitting = true">@csrf<button class="primary w-full" :disabled="submitting"><span x-show="!submitting"><x-t k="continue_checkin" /></span><span x-cloak x-show="submitting"><x-t k="please_wait" /></span></button></form>
    @endif
</section>
</div>
</x-layout>
