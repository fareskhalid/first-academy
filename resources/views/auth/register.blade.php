<x-layout title="register" :guest="true">
<section class="panel"><h2 class="section-heading"><span class="heading-icon"><x-icon name="graduation" :size="20" /></span><x-t k="register" /></h2>
<form method="POST" action="{{ route('register') }}" class="stack" x-data="{ same: @js((bool)old('whatsapp_same',true)) }">@csrf
<x-field name="name" autocomplete="name" maxlength="150" required />
<x-field name="phone" type="tel" autocomplete="tel" hint="phone_hint" required dir="ltr" />
<x-check name="whatsapp_same" :checked="true" x-model="same" />
<div x-show="!same"><x-field name="whatsapp_phone" type="tel" dir="ltr" x-bind:required="!same" /></div>
<x-check name="whatsapp_declared" required />
<x-field name="university" maxlength="150" />
<x-field name="university_student_id" maxlength="100" />
<x-field name="password" type="password" autocomplete="new-password" minlength="8" maxlength="255" hint="password_hint" required />
<x-field name="password_confirmation" type="password" autocomplete="new-password" required />
<button class="primary w-full"><x-icon name="arrow" :size="18" /><x-t k="register" /></button>
<a class="block text-sm" href="{{ route('login') }}"><x-t k="back_login" /></a>
</form></section>
</x-layout>
