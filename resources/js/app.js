import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

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
