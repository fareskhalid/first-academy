<x-layout title="scan_qr_code">
<div class="student-page student-scanner-page" data-qr-scanner
    data-camera-starting="{{ __('ui.camera_starting') }}"
    data-camera-ready="{{ __('ui.camera_ready') }}"
    data-camera-error="{{ __('ui.camera_access_error') }}"
    data-camera-https="{{ __('ui.camera_https_required') }}"
    data-invalid-qr="{{ __('ui.invalid_attendance_qr') }}"
    data-qr-detected="{{ __('ui.qr_detected') }}">
    <header class="student-scanner-hero">
        <a class="student-back" href="{{ route('dashboard') }}" aria-label="{{ __('ui.back') }}"><x-icon name="arrow" :size="19" /></a>
        <span class="student-scanner-hero-icon"><x-icon name="qr" :size="31" /></span>
        <div><p class="eyebrow"><x-t k="attendance" /></p><h1><x-t k="scan_qr_code" /></h1><p><x-t k="scan_qr_hint" /></p></div>
    </header>

    <section class="student-scanner-card">
        <div class="student-scanner-preview" data-qr-preview>
            <video data-qr-video playsinline muted hidden></video>
            <div class="student-scanner-placeholder" data-qr-placeholder><span><x-icon name="qr" :size="42" /></span><strong><x-t k="camera_ready" /></strong><small><x-t k="camera_privacy_hint" /></small></div>
            <div class="student-scanner-frame" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
        </div>
        <p class="student-scanner-status" data-qr-status aria-live="polite"><x-t k="camera_ready" /></p>
        <div class="student-scanner-actions">
            <button type="button" class="primary" data-qr-start><x-icon name="qr" :size="19" /><x-t k="start_camera" /></button>
            <button type="button" class="secondary" data-qr-stop hidden><x-t k="stop_camera" /></button>
        </div>
    </section>

    <section class="student-action-card student-scanner-fallback">
        <div class="student-action-heading"><span><x-icon name="clipboard" :size="23" /></span><div><h2><x-t k="manual_code_fallback" /></h2><p><x-t k="class_code_hint" /></p></div></div>
        <form method="POST" action="{{ route('attendance.code') }}" class="student-code-form" x-data="{ submitting: false }" @submit="submitting = true">@csrf
            <x-field name="code" label="short_code" required minlength="6" maxlength="6" pattern="[A-Za-z0-9]{6}" dir="ltr" inputmode="text" autocomplete="off" autocapitalize="characters" autocorrect="off" spellcheck="false" />
            <button class="primary" :disabled="submitting"><span x-show="!submitting"><x-icon name="check" :size="18" /><x-t k="record_attendance" /></span><span x-cloak x-show="submitting"><x-t k="please_wait" /></span></button>
        </form>
    </section>
</div>
</x-layout>
