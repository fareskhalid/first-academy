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
        window.dispatchEvent(new CustomEvent('course:locale-changed', { detail: { locale: this.locale } }));
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

function localInputValue(date) {
    const pad = value => String(value).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

async function initWeekCalendar() {
    const element = document.querySelector('[data-week-calendar]');
    const options = window.weekCalendar;
    if (!element || !options) return;
    const [{ Calendar }, { default: timeGridPlugin }, { default: interactionPlugin }, { default: arLocale }] = await Promise.all([
        import('@fullcalendar/core'),
        import('@fullcalendar/timegrid'),
        import('@fullcalendar/interaction'),
        import('@fullcalendar/core/locales/ar'),
    ]);

    const status = document.querySelector('[data-calendar-status]');
    const showStatus = (message, error = false) => {
        if (!status) return;
        status.textContent = message;
        status.classList.toggle('error', error);
        status.hidden = false;
    };
    const clearStatus = () => { if (status) status.hidden = true; };

    const moveEvent = async (info, overrideReason = null) => {
        clearStatus();
        const response = await fetch(info.event.extendedProps.moveUrl, {
            method: 'PATCH',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': config.csrf,
            },
            body: JSON.stringify({
                scheduled_start: info.event.start.toISOString(),
                scheduled_end: info.event.end.toISOString(),
                reason: options.messages.moveReason,
                conflict_override_reason: overrideReason,
            }),
        });
        const payload = await response.json().catch(() => ({}));
        if (response.ok) {
            let message = payload.message || options.messages.saved;
            if (payload.student_conflicts > 0) message += ` ${options.messages.studentConflicts}: ${payload.student_conflicts}`;
            showStatus(message);
            return;
        }

        const conflict = payload.errors?.scheduled_start?.includes(options.messages.scheduleConflict);
        if (response.status === 422 && conflict && overrideReason === null) {
            const reason = window.prompt(options.messages.overridePrompt);
            if (reason?.trim()) return moveEvent(info, reason.trim());
        }
        info.revert();
        const firstError = Object.values(payload.errors || {}).flat()[0];
        showStatus(firstError || payload.message || options.messages.failed, true);
    };

    const calendar = new Calendar(element, {
        plugins: [timeGridPlugin, interactionPlugin],
        initialView: 'timeGridWeek',
        initialDate: options.initialDate,
        firstDay: 6,
        locale: options.locale === 'ar' ? arLocale : 'en',
        direction: options.locale === 'ar' ? 'rtl' : 'ltr',
        headerToolbar: false,
        allDaySlot: false,
        slotMinTime: '07:00:00',
        slotMaxTime: '23:00:00',
        slotDuration: '00:30:00',
        slotLabelInterval: '01:00:00',
        nowIndicator: true,
        selectable: true,
        selectMirror: true,
        editable: true,
        eventStartEditable: true,
        eventDurationEditable: true,
        eventMinHeight: 36,
        events: options.events,
        select(selection) {
            const start = document.getElementById('scheduled_start');
            const end = document.getElementById('scheduled_end');
            const panel = document.getElementById('calendar-create-panel');
            if (!start || !end || !panel) return;
            start.value = localInputValue(selection.start);
            end.value = localInputValue(selection.end);
            panel.open = true;
            panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
            start.focus({ preventScroll: true });
            panel.classList.add('calendar-target');
            setTimeout(() => panel.classList.remove('calendar-target'), 1500);
        },
        eventClick(info) {
            info.jsEvent.preventDefault();
            window.location.assign(info.event.extendedProps.editUrl);
        },
        eventDrop(info) { moveEvent(info).catch(() => { info.revert(); showStatus(options.messages.failed, true); }); },
        eventResize(info) { moveEvent(info).catch(() => { info.revert(); showStatus(options.messages.failed, true); }); },
    });
    calendar.render();
    window.addEventListener('course:locale-changed', event => {
        calendar.setOption('locale', event.detail.locale === 'ar' ? arLocale : 'en');
        calendar.setOption('direction', event.detail.locale === 'ar' ? 'rtl' : 'ltr');
    });
}

document.addEventListener('DOMContentLoaded', initWeekCalendar);
