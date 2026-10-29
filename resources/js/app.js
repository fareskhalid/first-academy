import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
const config = window.courseApp;

const toastRegion = document.querySelector('[data-toast-region]');

function dismissToast(toast) {
    if (!(toast instanceof HTMLElement) || toast.dataset.dismissing === 'true') return;
    toast.dataset.dismissing = 'true';
    if (toast.dataset.timer) window.clearTimeout(Number(toast.dataset.timer));
    toast.classList.remove('is-visible');
    toast.classList.add('is-leaving');
    window.setTimeout(() => toast.remove(), 220);
}

function activateToast(toast) {
    if (!(toast instanceof HTMLElement) || toast.dataset.ready === 'true') return;
    toast.dataset.ready = 'true';
    toast.querySelector('[data-toast-dismiss]')?.addEventListener('click', () => dismissToast(toast));
    const timeout = Number(toast.dataset.timeout || 0);
    if (timeout > 0) toast.dataset.timer = String(window.setTimeout(() => dismissToast(toast), timeout));
}

function showToast(message, options = {}) {
    if (!toastRegion || !message) return;
    const type = options.type === 'error' ? 'error' : 'success';
    const timeout = Number(options.timeout ?? (type === 'error' ? 8000 : 5000));
    const toast = document.createElement('article');
    toast.className = `app-toast is-${type}`;
    toast.dataset.toast = '';
    toast.dataset.timeout = String(timeout);
    toast.setAttribute('role', type === 'error' ? 'alert' : 'status');

    const icon = document.createElement('span');
    icon.className = 'app-toast-icon';
    icon.setAttribute('aria-hidden', 'true');
    icon.textContent = type === 'error' ? '!' : '✓';

    const content = document.createElement('div');
    content.className = 'app-toast-content';
    const title = document.createElement('strong');
    title.textContent = options.title || validationText(type === 'error' ? 'toast_error' : 'toast_success');
    const body = document.createElement('div');
    body.className = 'app-toast-message';
    body.textContent = message;
    content.append(title, body);

    if (options.actionUrl && options.actionLabel) {
        const action = document.createElement('a');
        action.href = options.actionUrl;
        action.textContent = options.actionLabel;
        body.append(' ', action);
    }

    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'app-toast-close';
    close.dataset.toastDismiss = '';
    close.setAttribute('aria-label', validationText('dismiss_notification'));
    close.textContent = '×';
    toast.append(icon, content, close);

    if (timeout > 0) {
        const progress = document.createElement('span');
        progress.className = 'app-toast-progress';
        progress.style.setProperty('--toast-duration', `${timeout}ms`);
        progress.setAttribute('aria-hidden', 'true');
        toast.append(progress);
    }

    toastRegion.prepend(toast);
    activateToast(toast);
    window.requestAnimationFrame(() => toast.classList.add('is-visible'));
}

window.courseToast = showToast;
window.addEventListener('course:toast', event => {
    const detail = typeof event.detail === 'string' ? { message: event.detail } : (event.detail || {});
    showToast(detail.message, detail);
});
toastRegion?.querySelectorAll('[data-toast]').forEach(activateToast);

Alpine.store('i18n', {
    locale: document.documentElement.lang,
    busy: false,
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
        if (!config.authenticated) return;
        this.busy = true;
        try {
            const response = await fetch(config.localeUrl, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': config.csrf }, body: JSON.stringify({ locale: this.locale }) });
            if (!response.ok) throw new Error('locale');
        } catch { showToast(this.t('language_failed'), { type: 'error' }); }
        finally { this.busy = false; }
    },
});

const systemTheme = window.matchMedia('(prefers-color-scheme: dark)');
let storedTheme = null;
try { storedTheme = localStorage.getItem('first-academy-theme'); } catch {}
if (!['light', 'dark'].includes(storedTheme)) storedTheme = null;

function applyTheme(theme, persist = false) {
    const selected = theme === 'dark' ? 'dark' : 'light';
    document.documentElement.dataset.theme = selected;
    document.documentElement.style.colorScheme = selected;
    document.querySelector('[data-theme-color]')?.setAttribute('content', selected === 'dark' ? '#0d1120' : '#f7f7fc');
    if (persist) {
        storedTheme = selected;
        try { localStorage.setItem('first-academy-theme', selected); } catch {}
    }
    window.dispatchEvent(new CustomEvent('course:theme-changed', { detail: { theme: selected } }));
}

Alpine.store('theme', {
    current: document.documentElement.dataset.theme || (systemTheme.matches ? 'dark' : 'light'),
    get isDark() { return this.current === 'dark'; },
    toggle() {
        this.current = this.isDark ? 'light' : 'dark';
        applyTheme(this.current, true);
    },
});

systemTheme.addEventListener('change', event => {
    if (storedTheme) return;
    const theme = event.matches ? 'dark' : 'light';
    Alpine.store('theme').current = theme;
    applyTheme(theme);
});
Livewire.start();

const validationControlSelector = 'input:not([type="hidden"]):not([type="submit"]):not([type="button"]):not([type="reset"]), select, textarea';
const validationMessages = new WeakMap();
let validationMessageSequence = 0;

function validationText(key, params = {}) {
    const locale = document.documentElement.lang === 'ar' ? 'ar' : 'en';
    let value = config.messages?.[locale]?.[key] || config.messages?.en?.[key] || key;
    for (const [name, replacement] of Object.entries(params)) value = value.replaceAll(`:${name}`, String(replacement));
    return value;
}

function normalizePhone(value) {
    const arabicDigits = '٠١٢٣٤٥٦٧٨٩';
    const persianDigits = '۰۱۲۳۴۵۶۷۸۹';
    let normalized = value.trim().replace(/[٠-٩۰-۹]/g, digit => {
        const arabicIndex = arabicDigits.indexOf(digit);
        return String(arabicIndex >= 0 ? arabicIndex : persianDigits.indexOf(digit));
    }).replace(/[\s()\-]/g, '');

    if (normalized.startsWith('00')) normalized = `+${normalized.slice(2)}`;
    if (/^01[0125][0-9]{8}$/.test(normalized)) normalized = `+20${normalized.slice(1)}`;
    return normalized;
}

function relatedControl(control, name) {
    return control.form?.querySelector(`[name="${name}"]`) || null;
}

function hasInvalidOrder(control) {
    if (!control.value) return false;
    const value = name => relatedControl(control, name)?.value || '';

    if (control.name === 'ends_on') return Boolean(value('starts_on') && control.value < value('starts_on'));
    if (control.name === 'to') return Boolean(value('from') && control.value < value('from'));
    if (control.name === 'scheduled_end') {
        return Boolean((value('scheduled_start') && control.value <= value('scheduled_start'))
            || (value('attendance_closes') && value('attendance_closes') > control.value));
    }
    if (control.name === 'attendance_opens') {
        const closes = value('attendance_closes') || value('scheduled_end');
        return Boolean(closes && control.value >= closes);
    }
    if (control.name === 'late_after') {
        const opens = value('attendance_opens') || value('scheduled_start');
        const closes = value('attendance_closes') || value('scheduled_end');
        return Boolean((opens && control.value < opens) || (closes && control.value > closes));
    }
    if (control.name === 'attendance_closes') {
        const opens = value('attendance_opens') || value('scheduled_start');
        return Boolean((opens && control.value <= opens)
            || (value('late_after') && control.value < value('late_after'))
            || (value('scheduled_end') && control.value > value('scheduled_end')));
    }
    return false;
}

function validationError(control) {
    if (control.disabled) return '';

    const value = control.type === 'checkbox' ? (control.checked ? control.value : '') : control.value;
    const trimmedValue = typeof value === 'string' ? value.trim() : value;
    if (control.required && !trimmedValue) return validationText('validation_required');
    if (!trimmedValue) return '';

    if (control.name === 'phone' && !/^\+201[0125][0-9]{8}$/.test(normalizePhone(value))) return validationText('invalid_phone');
    if (control.name === 'whatsapp_phone' && !/^\+[1-9][0-9]{7,14}$/.test(normalizePhone(value))) return validationText('invalid_whatsapp');
    if (control.inputMode === 'numeric' && !/^[0-9]+$/.test(value)) return validationText('validation_digits');

    const minimumLength = Number(control.getAttribute('minlength'));
    const maximumLength = Number(control.getAttribute('maxlength'));
    if (minimumLength > 0 && value.length < minimumLength) return validationText('validation_min_length', { min: minimumLength });
    if (maximumLength > 0 && value.length > maximumLength) return validationText('validation_max_length', { max: maximumLength });

    if (control.type === 'number') {
        const numericValue = Number(value);
        const minimum = control.getAttribute('min');
        const maximum = control.getAttribute('max');
        if (minimum !== null && numericValue < Number(minimum)) return validationText('validation_min_number', { min: minimum });
        if (maximum !== null && numericValue > Number(maximum)) return validationText('validation_max_number', { max: maximum });
    }

    if (control.name === 'password_confirmation') {
        const password = relatedControl(control, 'password');
        if (password && value !== password.value) return validationText('validation_password_match');
    }
    if (control.name === 'password') {
        const currentPassword = relatedControl(control, 'current_password');
        if (currentPassword?.value && value === currentPassword.value) return validationText('validation_password_different');
    }
    if (hasInvalidOrder(control)) return validationText('validation_date_order');

    const validity = control.validity;
    if (validity.typeMismatch || validity.patternMismatch || validity.badInput || validity.stepMismatch) return validationText('validation_invalid');
    if (validity.rangeUnderflow) return validationText('validation_min_number', { min: control.min });
    if (validity.rangeOverflow) return validationText('validation_max_number', { max: control.max });
    return '';
}

function messageElement(control) {
    if (validationMessages.has(control)) return validationMessages.get(control);

    const message = document.createElement('span');
    message.id = `field-validation-${++validationMessageSequence}`;
    message.className = 'field-error';
    message.hidden = true;
    message.setAttribute('aria-live', 'polite');

    const field = control.closest('.field');
    const check = control.closest('.check');
    if (field) field.append(message);
    else if (check) check.insertAdjacentElement('afterend', message);
    else control.insertAdjacentElement('afterend', message);

    const describedBy = new Set((control.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean));
    describedBy.add(message.id);
    control.setAttribute('aria-describedby', [...describedBy].join(' '));
    validationMessages.set(control, message);
    return message;
}

function renderValidation(control, force = false) {
    if (!(control instanceof HTMLInputElement || control instanceof HTMLSelectElement || control instanceof HTMLTextAreaElement)) return true;
    if (!force && control.dataset.validationTouched !== 'true') return true;

    const error = validationError(control);
    const message = messageElement(control);
    const container = control.closest('.field, .check');
    message.textContent = error;
    message.hidden = !error;
    control.toggleAttribute('aria-invalid', Boolean(error));
    container?.classList.toggle('has-error', Boolean(error));
    return !error;
}

function revalidateRelatedControls(control) {
    const dependants = {
        password: ['password_confirmation'],
        current_password: ['password'],
        starts_on: ['ends_on'],
        from: ['to'],
        scheduled_start: ['scheduled_end', 'late_after', 'attendance_closes'],
        scheduled_end: ['attendance_opens', 'late_after', 'attendance_closes'],
        attendance_opens: ['late_after', 'attendance_closes'],
        late_after: ['attendance_opens', 'attendance_closes'],
        attendance_closes: ['scheduled_end', 'attendance_opens', 'late_after'],
    };
    for (const name of dependants[control.name] || []) {
        const dependant = relatedControl(control, name);
        if (dependant?.dataset.validationTouched === 'true') renderValidation(dependant);
    }
}

function handleLiveValidation(event) {
    const control = event.target.closest?.(validationControlSelector);
    if (!control) return;
    control.dataset.validationTouched = 'true';
    renderValidation(control, true);
    revalidateRelatedControls(control);
}

function enableLiveValidation(root = document) {
    if (root instanceof HTMLFormElement) root.noValidate = true;
    root.querySelectorAll?.('form').forEach(form => { form.noValidate = true; });
}

enableLiveValidation();
new MutationObserver(mutations => {
    for (const mutation of mutations) {
        mutation.addedNodes.forEach(node => {
            if (node instanceof HTMLElement) enableLiveValidation(node);
        });
    }
}).observe(document.body, { childList: true, subtree: true });

document.addEventListener('input', handleLiveValidation);
document.addEventListener('change', handleLiveValidation);
document.addEventListener('blur', handleLiveValidation, true);
document.addEventListener('submit', event => {
    if (!(event.target instanceof HTMLFormElement)) return;
    const controls = [...event.target.querySelectorAll(validationControlSelector)];
    const invalidControls = controls.filter(control => {
        control.dataset.validationTouched = 'true';
        return !renderValidation(control, true);
    });
    if (!invalidControls.length) return;

    event.preventDefault();
    event.stopImmediatePropagation();
    invalidControls[0].focus({ preventScroll: true });
    invalidControls[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
}, true);
window.addEventListener('course:locale-changed', () => {
    document.querySelectorAll(validationControlSelector).forEach(control => {
        if (control.dataset.validationTouched === 'true') renderValidation(control, true);
    });
});

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

    const showStatus = (message, error = false) => showToast(message, { type: error ? 'error' : 'success' });

    const moveEvent = async (info, overrideReason = null) => {
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
        slotLabelFormat: { hour: 'numeric', minute: '2-digit', hour12: true },
        eventTimeFormat: { hour: 'numeric', minute: '2-digit', hour12: true },
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
