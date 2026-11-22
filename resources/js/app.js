// Keep the current page usable when navigation is cancelled or fails.
let pageLoadingTimer;
let pageLoadingFallback;
let navigationVersion = 0;

function finishPageLoading() {
    navigationVersion++;
    clearTimeout(pageLoadingTimer);
    clearTimeout(pageLoadingFallback);
    const content = document.querySelector('[data-page-content]');
    const placeholder = document.querySelector('[data-page-loading]');
    if (content) {
        content.hidden = false;
        content.removeAttribute('aria-busy');
    }
    if (placeholder) placeholder.hidden = true;
}

function beginPageLoading() {
    clearTimeout(pageLoadingTimer);
    clearTimeout(pageLoadingFallback);
    const version = ++navigationVersion;
    pageLoadingTimer = setTimeout(() => {
        if (version !== navigationVersion) return;
        const content = document.querySelector('[data-page-content]');
        const placeholder = document.querySelector('[data-page-loading]');
        if (!content || !placeholder) return;
        content.setAttribute('aria-busy', 'true');
        content.hidden = true;
        placeholder.hidden = false;
    }, 200);
    pageLoadingFallback = setTimeout(finishPageLoading, 30000);
}

function isPageDestination(value) {
    const url = new URL(value, window.location.href);
    if (!['http:', 'https:'].includes(url.protocol) || url.origin !== window.location.origin) return false;
    if (url.pathname === window.location.pathname && url.search === window.location.search) return false;
    if (url.searchParams.has('signature') || /\/(?:export|download)(?:\/|$)/i.test(url.pathname)) return false;
    return !/\.(?:pdf|csv|xlsx?|zip|png|jpe?g|webp|svg)$/i.test(url.pathname);
}

document.addEventListener('livewire:navigate', (event) => {
    const version = navigationVersion;
    queueMicrotask(() => {
        if (!event.defaultPrevented && version === navigationVersion) beginPageLoading();
    });
});
document.addEventListener('livewire:navigated', finishPageLoading);
window.addEventListener('pageshow', finishPageLoading);

document.addEventListener('click', (event) => {
    if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    const link = event.target.closest('a[href]');
    const sort = event.target.closest('[data-sort-url]');
    if (link && (link.hasAttribute('download') || (link.target && link.target !== '_self'))) return;
    const destination = link?.href || sort?.dataset.sortUrl;
    if (!destination || !isPageDestination(destination)) return;
    queueMicrotask(() => { if (!event.defaultPrevented) beginPageLoading(); });
});

document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || form.method.toLowerCase() !== 'get' || (form.target && form.target !== '_self')) return;
    if (!isPageDestination(form.action) && !form.action.startsWith(window.location.origin + window.location.pathname)) return;
    queueMicrotask(() => { if (!event.defaultPrevented) beginPageLoading(); });
});

document.addEventListener('alpine:init', () => {
    Alpine.data('sidebarResize', () => ({
        width: 256,
        resizing: false,
        init() {
            try {
                const saved = Number(localStorage.getItem('aydra.sidebar.width'));
                if (saved >= 208 && saved <= 360) this.width = saved;
            } catch { /* The sidebar remains usable when storage is unavailable. */ }
        },
        setWidth(value) {
            this.width = Math.round(Math.min(360, Math.max(208, value)));
        },
        save() {
            try { localStorage.setItem('aydra.sidebar.width', this.width); } catch { /* Storage is optional. */ }
        },
        start(event) {
            if (event.button !== 0) return;
            this.resizing = true;
            event.currentTarget.setPointerCapture(event.pointerId);
        },
        move(event) {
            if (!this.resizing) return;
            this.setWidth(document.dir === 'rtl' ? window.innerWidth - event.clientX : event.clientX);
        },
        finish(event) {
            if (!this.resizing) return;
            this.resizing = false;
            if (event.currentTarget.hasPointerCapture(event.pointerId)) event.currentTarget.releasePointerCapture(event.pointerId);
            this.save();
        },
        adjust(amount) {
            this.setWidth(this.width + (document.dir === 'rtl' ? -amount : amount));
            this.save();
        },
    }));

    Alpine.data('activityDateRange', () => ({
        open: false,
        from: '',
        to: '',
        month: null,
        init() {
            this.from = this.$el.dataset.from || '';
            this.to = this.$el.dataset.to || '';
            const initial = this.from || this.to;
            const date = initial ? new Date(`${initial}T00:00:00Z`) : new Date();
            this.month = new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth(), 1));
        },
        get title() {
            return new Intl.DateTimeFormat('fr-FR', { month: 'long', year: 'numeric', timeZone: 'UTC' }).format(this.month);
        },
        get label() {
            const format = value => value ? value.split('-').reverse().join('/') : '…';
            return this.from || this.to ? `${format(this.from)} – ${format(this.to)} (UTC)` : 'Toutes les dates (UTC)';
        },
        get days() {
            const year = this.month.getUTCFullYear(), month = this.month.getUTCMonth();
            const offset = (this.month.getUTCDay() + 6) % 7;
            const count = new Date(Date.UTC(year, month + 1, 0)).getUTCDate();
            return [...Array(offset).fill(null), ...Array.from({ length: count }, (_, i) => {
                const date = new Date(Date.UTC(year, month, i + 1)).toISOString().slice(0, 10);
                return { date, day: i + 1 };
            })];
        },
        moveMonth(offset) {
            this.month = new Date(Date.UTC(this.month.getUTCFullYear(), this.month.getUTCMonth() + offset, 1));
        },
        select(date) {
            if (!this.from || this.to || date < this.from) { this.from = date; this.to = ''; }
            else { this.to = date; }
        },
        preset(days) {
            const now = new Date();
            const today = new Date(Date.UTC(now.getUTCFullYear(), now.getUTCMonth(), now.getUTCDate()));
            this.to = today.toISOString().slice(0, 10);
            today.setUTCDate(today.getUTCDate() - days + 1);
            this.from = today.toISOString().slice(0, 10);
            this.month = new Date(Date.UTC(now.getUTCFullYear(), now.getUTCMonth(), 1));
        },
        presetExclusive(days) {
            this.preset(days);
            const end = new Date(`${this.to}T00:00:00Z`);
            end.setUTCDate(end.getUTCDate() + 1);
            this.to = end.toISOString().slice(0, 10);
        },
    }));
});
