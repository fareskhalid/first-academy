<x-layout title="login" :guest="true">
<section class="panel"><h2 class="section-heading"><span class="heading-icon"><x-icon name="user" :size="20" /></span><x-t k="login" /></h2>
<form method="POST" action="{{ route('login') }}" class="stack">@csrf
<x-field name="identifier" autocomplete="username" required autofocus dir="ltr" />
<x-field name="password" type="password" autocomplete="current-password" required />
<button class="primary w-full"><x-icon name="arrow" :size="18" /><x-t k="login" /></button>
<a class="block text-sm" href="{{ route('recovery') }}"><x-t k="recovery" /></a>
</form><hr class="divider"><a class="button secondary w-full" href="{{ route('register') }}"><x-t k="register" /></a></section>
</x-layout>
