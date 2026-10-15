<x-layout title="profile">
<div class="hero"><div><div class="eyebrow"><x-t k="profile" /></div><h1>{{ auth()->user()->name }}</h1>@if(auth()->user()->student_code)<p><x-t k="student_code" /> · <bdi>{{ auth()->user()->student_code }}</bdi></p>@endif</div></div>
@if(auth()->user()->must_change_password)<div class="alert"><x-t k="forced_password" /></div>@endif
<div class="grid two">
@if(!auth()->user()->must_change_password)
<section class="panel"><h2><x-t k="profile" /></h2><form method="POST" action="{{ route('profile.update') }}" class="stack">@csrf @method('PUT')
<x-field name="name" :value="auth()->user()->name" required autocomplete="name" maxlength="150" />
<x-field name="phone" :value="auth()->user()->phone" type="tel" required dir="ltr" maxlength="32" />
@if(!auth()->user()->isInstructor())
<x-field name="whatsapp_phone" :value="auth()->user()->profile?->whatsapp_phone" type="tel" required dir="ltr" maxlength="32" />
<x-check name="whatsapp_declared" required />
<x-field name="university" :value="auth()->user()->profile?->university" maxlength="150" />
<x-field name="university_student_id" :value="auth()->user()->profile?->university_student_id" maxlength="100" />
@endif
<x-field name="current_password" type="password" required autocomplete="current-password" maxlength="255" />
<button class="primary"><x-t k="save" /></button></form></section>
@endif
<section class="panel"><h2><x-t k="change_password" /></h2><form method="POST" action="{{ route('profile.password') }}" class="stack">@csrf @method('PUT')
<x-field name="current_password" id="password-current" type="password" required autocomplete="current-password" maxlength="255" />
<x-field name="password" label="new_password" type="password" required autocomplete="new-password" minlength="8" maxlength="255" hint="password_hint" />
<x-field name="password_confirmation" type="password" required autocomplete="new-password" maxlength="255" />
<button class="primary"><x-t k="change_password" /></button></form></section>
</div></x-layout>
