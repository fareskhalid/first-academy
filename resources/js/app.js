import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
const config = window.courseApp;
Alpine.store('i18n', {
    locale: document.documentElement.lang,
    busy: false,
    error: '',
    t(key, params = {}) {
        let value = config.messages[this.locale][key] || key;
        for (const [name, replacement] of Object.entries(params)) value = value.replaceAll(`:${name}`, String(replacement));
        return value;
    },
    async toggle() {
        this.locale = this.locale === 'ar' ? 'en' : 'ar';
        document.documentElement.lang = this.locale;
        document.documentElement.dir = this.locale === 'ar' ? 'rtl' : 'ltr';
        document.cookie = `locale=${this.locale}; Path=/; Max-Age=31536000; SameSite=Lax${location.protocol === 'https:' ? '; Secure' : ''}`;
        this.error = '';
        if (!config.authenticated) return;
        this.busy = true;
        try {
            const response = await fetch(config.localeUrl, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': config.csrf }, body: JSON.stringify({ locale: this.locale }) });
            if (!response.ok) throw new Error('locale');
        } catch { this.error = this.t('language_failed'); }
        finally { this.busy = false; }
    },
});
Livewire.start();
