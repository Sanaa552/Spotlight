import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

const themeStorageKey = 'spotlight-theme';

function syncThemeButtons() {
    const isLight = document.documentElement.dataset.theme === 'light';
    const label = isLight ? 'Passer au thème sombre' : 'Passer au thème clair';

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.setAttribute('aria-label', label);
        button.setAttribute('title', label);
    });
}

document.addEventListener('click', (event) => {
    if (!event.target.closest('[data-theme-toggle]')) return;

    const nextTheme = document.documentElement.dataset.theme === 'light' ? 'dark' : 'light';
    document.documentElement.dataset.theme = nextTheme;

    try {
        localStorage.setItem(themeStorageKey, nextTheme);
    } catch {
        // The choice still applies for this page when browser storage is unavailable.
    }

    syncThemeButtons();
});

window.addEventListener('storage', (event) => {
    if (event.key !== themeStorageKey) return;
    document.documentElement.dataset.theme = event.newValue === 'light' ? 'light' : 'dark';
    syncThemeButtons();
});

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', syncThemeButtons);
} else {
    syncThemeButtons();
}

Alpine.data('publicationTracker', (url, initialStatus) => ({
    status: initialStatus,
    error: '',
    delayed: false,
    timer: null,

    init() {
        this.error = this.$el.dataset.publicationError || '';
        if (['queued', 'processing'].includes(this.status)) {
            this.timer = setInterval(() => this.refresh(), 5000);
        }
    },

    async refresh() {
        try {
            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                cache: 'no-store',
            });
            if (!response.ok) return;

            const data = await response.json();
            this.delayed = Boolean(data.delayed);
            if (data.status !== this.status || data.declaration_status !== 'en_attente') {
                this.status = data.status;
                this.error = data.error || '';
                if (!['queued', 'processing'].includes(data.status)) {
                    clearInterval(this.timer);
                    this.timer = null;
                    if (data.status === 'succeeded') {
                        setTimeout(() => window.location.reload(), 4000);
                    }
                }
            }
        } catch {
            // The next poll retries without interrupting the moderator's page.
        }
    },

    destroy() {
        if (this.timer) clearInterval(this.timer);
    },
}));

Alpine.start();
