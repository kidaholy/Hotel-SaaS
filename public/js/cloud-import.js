/**
 * Import lists from the platform cloud database (legacy data/database.sqlite).
 */
const CloudImportUI = {
    _status: null,

    async getStatus() {
        if (this._status) {
            return this._status;
        }
        const res = await fetch('api/admin/cloud-import.php', { credentials: 'same-origin' });
        const data = await res.json();
        if (!res.ok) {
            throw new Error(data.message || 'Could not load cloud import status');
        }
        this._status = data;
        if (data.platform_name) {
            window.PLATFORM_NAME = data.platform_name;
        }
        return data;
    },

    buttonLabel(platformName) {
        const name = platformName || window.PLATFORM_NAME || 'Cloud';
        return `Import data from ${name}`;
    },

    async run(scope, options = {}) {
        const status = await this.getStatus();
        if (!status.available) {
            alert(status.is_legacy_tenant
                ? 'This hotel already uses the cloud database.'
                : 'Cloud import is not available right now.');
            return null;
        }

        const platformName = status.platform_name || window.PLATFORM_NAME || 'Cloud';
        const type = options.type || '';
        const countKey = scope === 'categories'
            ? `categories_${type}`
            : scope === 'menus'
                ? 'menu_items'
                : 'stocks';
        const count = status.counts?.[countKey] ?? 0;

        const scopeLabel = scope === 'categories'
            ? `${type} categories`
            : scope === 'menus'
                ? 'menu items'
                : 'store inventory items';

        const confirmed = confirm(
            `Import ${count} ${scopeLabel} from ${platformName}?\n\n` +
            'Items with the same name or ID already in your hotel will be skipped.'
        );
        if (!confirmed) {
            return null;
        }

        const body = { scope };
        if (type) body.type = type;
        if (options.collection) body.collection = options.collection;

        const res = await fetch('api/admin/cloud-import.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(body),
        });
        const data = await res.json();
        if (!res.ok) {
            throw new Error(data.message || 'Import failed');
        }

        this._status = null;
        alert(data.message || 'Import completed.');
        if (typeof options.onSuccess === 'function') {
            options.onSuccess(data);
        }
        return data;
    },

    async renderButton(containerId, scope, options = {}) {
        const el = document.getElementById(containerId);
        if (!el) return;

        try {
            const status = await this.getStatus();
            if (!status.available) {
                el.innerHTML = '';
                return;
            }

            const label = this.buttonLabel(status.platform_name);
            el.innerHTML = `
                <button type="button" class="cloud-import-btn w-full flex items-center justify-center gap-2 py-2.5 rounded-lg border text-xs font-bold uppercase tracking-wider transition-colors"
                    style="background:#f0faf5;border-color:#c5d5cc;color:#1d6b4a">
                    <i data-lucide="cloud-download" class="w-4 h-4"></i>
                    <span>${label}</span>
                </button>`;

            el.querySelector('button')?.addEventListener('click', async () => {
                const btn = el.querySelector('button');
                if (btn) {
                    btn.disabled = true;
                    btn.style.opacity = '0.7';
                }
                try {
                    await this.run(scope, options);
                } catch (err) {
                    alert(err.message || 'Import failed');
                } finally {
                    if (btn) {
                        btn.disabled = false;
                        btn.style.opacity = '';
                    }
                }
            });

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        } catch (err) {
            el.innerHTML = '';
        }
    },
};

window.cloudImportCategories = (type) => CloudImportUI.run('categories', {
    type,
    onSuccess: () => {
        if (typeof AdminSettings !== 'undefined') {
            AdminSettings.reloadCategories?.();
        }
        location.reload();
    },
});

window.cloudImportMenus = (collection) => CloudImportUI.run('menus', {
    collection: collection || 'menuItems',
    onSuccess: () => {
        if (typeof menuMgr !== 'undefined' && menuMgr.loadData) {
            menuMgr.loadData().then(() => menuMgr.render());
        } else {
            location.reload();
        }
    },
});

window.cloudImportStocks = () => CloudImportUI.run('stocks', {
    onSuccess: () => {
        if (typeof fetchAll === 'function') {
            fetchAll();
        } else {
            location.reload();
        }
    },
});
