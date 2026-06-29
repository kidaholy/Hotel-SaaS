/**
 * Import pre-loaded lists from data/database.sqlite (platform template).
 */
if (!window.CloudImportUI) {
const CloudImportUI = {
    _status: null,
    _modalEl: null,

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
        const name = platformName || window.PLATFORM_NAME || 'Platform';
        return `Import from ${name}`;
    },

    async fetchBundle(options = {}) {
        const params = new URLSearchParams({ list: 'bundle' });
        if (options.collection) params.set('collection', options.collection);

        const res = await fetch(`api/admin/cloud-import.php?${params}`, { credentials: 'same-origin' });
        const data = await res.json();
        if (!res.ok) {
            throw new Error(data.message || 'Could not load import list');
        }
        return data.result;
    },

    async fetchCategories(type) {
        const params = new URLSearchParams({ list: 'categories', type });
        const res = await fetch(`api/admin/cloud-import.php?${params}`, { credentials: 'same-origin' });
        const data = await res.json();
        if (!res.ok) {
            throw new Error(data.message || 'Could not load categories');
        }
        return data.result;
    },

    ensureModal() {
        if (this._modalEl) return this._modalEl;

        const el = document.createElement('div');
        el.id = 'cloud-import-modal';
        el.className = 'hidden fixed inset-0 z-[200] flex items-center justify-center p-4';
        el.innerHTML = `
            <div class="cloud-import-overlay absolute inset-0 bg-gray-900/80 backdrop-blur-sm"></div>
            <div class="relative w-full max-w-3xl bg-gray-800 border border-gray-700 rounded-2xl shadow-2xl flex flex-col max-h-[92vh]">
                <div class="p-6 border-b border-gray-700/60 shrink-0">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 id="cloud-import-title" class="text-lg font-bold text-white">Import from Platform</h2>
                            <p id="cloud-import-subtitle" class="text-xs text-gray-400 mt-1"></p>
                        </div>
                        <button type="button" class="cloud-import-close text-gray-500 hover:text-white transition-colors p-1">
                            <i data-lucide="x" class="w-5 h-5"></i>
                        </button>
                    </div>
                    <div class="mt-4 flex flex-col sm:flex-row gap-3">
                        <input type="search" id="cloud-import-search" placeholder="Search menus, store items, categories…"
                            class="flex-1 bg-gray-900 border border-gray-700 text-sm text-gray-200 py-2.5 px-3.5 rounded-lg outline-none focus:border-[#1d6b4a] transition-colors">
                        <div class="flex gap-2 shrink-0">
                            <button type="button" id="cloud-import-select-all"
                                class="px-3 py-2 rounded-lg border border-gray-600 text-[10px] font-bold uppercase tracking-wider text-gray-300 hover:text-white hover:border-gray-500 transition-colors">
                                Select all
                            </button>
                            <button type="button" id="cloud-import-clear-all"
                                class="px-3 py-2 rounded-lg border border-gray-600 text-[10px] font-bold uppercase tracking-wider text-gray-300 hover:text-white hover:border-gray-500 transition-colors">
                                Clear
                            </button>
                        </div>
                    </div>
                </div>
                <div id="cloud-import-list" class="flex-1 overflow-y-auto p-4 space-y-6 min-h-[240px]"></div>
                <div class="p-6 border-t border-gray-700/60 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 shrink-0">
                    <p id="cloud-import-count" class="text-xs text-gray-400">0 selected</p>
                    <div class="flex gap-3 justify-end">
                        <button type="button" class="cloud-import-close px-4 py-2.5 rounded-lg border border-gray-600 text-xs font-bold uppercase tracking-wider text-gray-300 hover:text-white transition-colors">
                            Cancel
                        </button>
                        <button type="button" id="cloud-import-submit"
                            class="px-5 py-2.5 rounded-lg text-xs font-bold uppercase tracking-wider text-white transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                            style="background:#1d6b4a">
                            Import selected
                        </button>
                    </div>
                </div>
            </div>`;
        document.body.appendChild(el);

        el.querySelector('.cloud-import-overlay')?.addEventListener('click', () => this.closeModal());
        el.querySelectorAll('.cloud-import-close').forEach((btn) => {
            btn.addEventListener('click', () => this.closeModal());
        });

        this._modalEl = el;
        return el;
    },

    closeModal() {
        if (this._modalEl) {
            this._modalEl.classList.add('hidden');
        }
    },

    escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    },

    rowAttrs(kind, key, name, meta, exists) {
        return `
            data-kind="${kind}"
            data-key="${this.escapeHtml(key)}"
            data-name="${this.escapeHtml((name || '').toLowerCase())}"
            data-meta="${this.escapeHtml((meta || '').toLowerCase())}"
            class="cloud-import-row flex items-start gap-3 p-3 rounded-xl border border-gray-700/80 bg-gray-900/50 cursor-pointer transition-colors ${exists ? 'opacity-50' : 'hover:border-gray-600'}"`;
    },

    checkboxAttrs(kind, key, exists, checked) {
        const disabled = exists ? 'disabled' : '';
        const isChecked = exists ? '' : (checked ? 'checked' : '');
        return `<input type="checkbox" class="cloud-import-checkbox mt-0.5 w-4 h-4 rounded border-gray-600 text-[#1d6b4a] focus:ring-[#1d6b4a] bg-gray-800 shrink-0"
            data-kind="${kind}" data-key="${this.escapeHtml(key)}" value="${this.escapeHtml(key)}" ${isChecked} ${disabled}>`;
    },

    isRowVisible(row) {
        return !row.classList.contains('hidden')
            && !row.closest('.cloud-import-group.hidden')
            && !row.closest('.cloud-import-section.hidden');
    },

    renderCategoryRow(cat, kind) {
        if (!cat) return '';
        const exists = !!cat.exists;
        const meta = cat.description || '';
        return `
            <label ${this.rowAttrs(`category_${kind}`, cat.key, cat.name, meta, exists)}>
                ${this.checkboxAttrs(`category_${kind}`, cat.key, exists, false)}
                <span class="flex-1 min-w-0">
                    <span class="block text-sm font-semibold text-gray-100 truncate">${this.escapeHtml(cat.name)}</span>
                    ${meta ? `<span class="block text-[11px] text-gray-500 mt-0.5 truncate">${this.escapeHtml(meta)}</span>` : ''}
                    ${exists ? '<span class="inline-block mt-1 text-[10px] font-bold uppercase tracking-wider text-amber-500/90">Already imported</span>' : ''}
                </span>
            </label>`;
    },

    renderMenuItemRow(item) {
        const meta = [
            item.menuId ? `#${item.menuId}` : '',
            item.price ? Number(item.price).toFixed(2) : '',
        ].filter(Boolean).join(' · ');
        const exists = !!item.exists;
        return `
            <label ${this.rowAttrs('menu', item.key, item.name, `${item.category || ''} ${meta}`, exists)} style="margin-left:1.25rem">
                ${this.checkboxAttrs('menu', item.key, exists, false)}
                <span class="flex-1 min-w-0">
                    <span class="block text-sm text-gray-200 truncate">${this.escapeHtml(item.name)}</span>
                    ${meta ? `<span class="block text-[11px] text-gray-500 mt-0.5 truncate">${this.escapeHtml(meta)}</span>` : ''}
                    ${exists ? '<span class="inline-block mt-1 text-[10px] font-bold uppercase tracking-wider text-amber-500/90">Already imported</span>' : ''}
                </span>
            </label>`;
    },

    renderStockItemRow(item) {
        const meta = [item.category, item.unit].filter(Boolean).join(' · ');
        const exists = !!item.exists;
        return `
            <label ${this.rowAttrs('stock', item.key, item.name, meta, exists)} style="margin-left:1.25rem">
                ${this.checkboxAttrs('stock', item.key, exists, false)}
                <span class="flex-1 min-w-0">
                    <span class="block text-sm text-gray-200 truncate">${this.escapeHtml(item.name)}</span>
                    ${meta ? `<span class="block text-[11px] text-gray-500 mt-0.5 truncate">${this.escapeHtml(meta)}</span>` : ''}
                    ${exists ? '<span class="inline-block mt-1 text-[10px] font-bold uppercase tracking-wider text-amber-500/90">Already imported</span>' : ''}
                </span>
            </label>`;
    },

    renderGroupSection(title, icon, sectionId, groups, kind) {
        const hasContent = groups.some((g) => (g.category && !g.category.exists) || g.items.some((i) => !i.exists) || g.items.length);
        if (!hasContent && groups.length === 0) {
            return '';
        }

        const groupsHtml = groups.map((group) => {
            const cat = group.category;
            const catRow = cat ? this.renderCategoryRow(cat, kind) : `
                <div class="px-3 py-2 text-xs font-bold uppercase tracking-wider text-gray-500">${this.escapeHtml(group.name)}</div>`;

            const itemsHtml = (group.items || []).map((item) =>
                kind === 'menu' ? this.renderMenuItemRow(item) : this.renderStockItemRow(item)
            ).join('');

            if (!cat && !itemsHtml) return '';

            return `
                <div class="cloud-import-group space-y-1.5" data-group="${this.escapeHtml(group.name.toLowerCase())}">
                    ${catRow}
                    ${itemsHtml}
                </div>`;
        }).join('');

        if (!groupsHtml.trim()) {
            return `<section id="${sectionId}" class="cloud-import-section">
                <div class="flex items-center gap-2 mb-3">
                    <i data-lucide="${icon}" class="w-4 h-4 text-[#1d6b4a]"></i>
                    <h3 class="text-sm font-bold uppercase tracking-wider text-gray-300">${title}</h3>
                </div>
                <p class="text-sm text-gray-500 text-center py-6">Nothing new to import in this section.</p>
            </section>`;
        }

        return `
            <section id="${sectionId}" class="cloud-import-section space-y-3">
                <div class="flex items-center gap-2 sticky top-0 bg-gray-800/95 backdrop-blur py-2 z-10 border-b border-gray-700/40 -mx-1 px-1">
                    <i data-lucide="${icon}" class="w-4 h-4 text-[#1d6b4a]"></i>
                    <h3 class="text-sm font-bold uppercase tracking-wider text-gray-300">${title}</h3>
                </div>
                <div class="space-y-4">${groupsHtml}</div>
            </section>`;
    },

    renderBundle(bundle) {
        const menuSection = this.renderGroupSection('Menu — categories & items', 'utensils', 'cloud-import-menu', bundle.menu?.groups || [], 'menu');
        const storeSection = this.renderGroupSection('Store — categories & inventory', 'package', 'cloud-import-store', bundle.store?.groups || [], 'stock');
        return `${menuSection}${storeSection}`;
    },

    updateSelectionCount(modal) {
        const boxes = [];
        const checked = [];
        modal.querySelectorAll('.cloud-import-row').forEach((row) => {
            if (!this.isRowVisible(row)) return;
            const box = row.querySelector('.cloud-import-checkbox:not(:disabled)');
            if (!box) return;
            boxes.push(box);
            if (box.checked) checked.push(box);
        });

        const countEl = modal.querySelector('#cloud-import-count');
        const submitBtn = modal.querySelector('#cloud-import-submit');

        const byKind = {
            menu: 0,
            stock: 0,
            category_menu: 0,
            category_stock: 0,
            category_distribution: 0,
        };
        checked.forEach((box) => {
            const kind = box.dataset.kind || '';
            if (byKind[kind] !== undefined) byKind[kind]++;
        });

        const parts = [];
        if (byKind.category_menu) parts.push(`${byKind.category_menu} menu cat.`);
        if (byKind.menu) parts.push(`${byKind.menu} menu items`);
        if (byKind.category_stock) parts.push(`${byKind.category_stock} store cat.`);
        if (byKind.stock) parts.push(`${byKind.stock} store items`);
        if (byKind.category_distribution) parts.push(`${byKind.category_distribution} distribution cat.`);

        if (countEl) {
            countEl.textContent = checked.length
                ? `${checked.length} selected (${parts.join(', ')})`
                : `0 of ${boxes.length} visible selected`;
        }
        if (submitBtn) {
            submitBtn.disabled = checked.length === 0;
        }
    },

    filterRows(modal, query) {
        const q = query.trim().toLowerCase();
        modal.querySelectorAll('.cloud-import-section').forEach((section) => {
            let sectionVisible = false;
            section.querySelectorAll('.cloud-import-group').forEach((group) => {
                let groupVisible = false;
                group.querySelectorAll('.cloud-import-row').forEach((row) => {
                    const hay = `${row.dataset.name || ''} ${row.dataset.meta || ''}`;
                    const show = q === '' || hay.includes(q);
                    row.classList.toggle('hidden', !show);
                    if (!show) {
                        const box = row.querySelector('.cloud-import-checkbox:not(:disabled)');
                        if (box) box.checked = false;
                    }
                    if (show) groupVisible = true;
                });
                group.classList.toggle('hidden', !groupVisible);
                if (groupVisible) sectionVisible = true;
            });
            section.classList.toggle('hidden', !sectionVisible && q !== '');
        });
        this.updateSelectionCount(modal);
    },

    collectSelection(modal) {
        const selection = {
            categories: { menu: [], stock: [] },
            menus: [],
            stocks: [],
        };

        modal.querySelectorAll('.cloud-import-checkbox:not(:disabled):checked').forEach((box) => {
            const kind = box.dataset.kind;
            const key = box.value;
            if (!key) return;

            if (kind === 'category_menu') selection.categories.menu.push(key);
            else if (kind === 'category_stock') selection.categories.stock.push(key);
            else if (kind === 'menu') selection.menus.push(key);
            else if (kind === 'stock') selection.stocks.push(key);
        });

        return selection;
    },

    syncGroupFromCategory(categoryBox) {
        const group = categoryBox.closest('.cloud-import-group');
        if (!group) return;
        const modal = categoryBox.closest('#cloud-import-modal');
        const searchActive = !!modal?.querySelector('#cloud-import-search')?.value?.trim();

        group.querySelectorAll('.cloud-import-row').forEach((row) => {
            const itemBox = row.querySelector('.cloud-import-checkbox[data-kind="menu"], .cloud-import-checkbox[data-kind="stock"]');
            if (!itemBox || itemBox.disabled) return;
            if (searchActive && !this.isRowVisible(row)) return;
            itemBox.checked = categoryBox.checked;
        });
    },

    bindPickerEvents(modal) {
        const searchInput = modal.querySelector('#cloud-import-search');
        searchInput.value = '';
        searchInput.oninput = () => this.filterRows(modal, searchInput.value);

        modal.querySelectorAll('.cloud-import-checkbox').forEach((box) => {
            box.addEventListener('change', () => {
                if (box.dataset.kind === 'category_menu' || box.dataset.kind === 'category_stock') {
                    this.syncGroupFromCategory(box);
                }
                this.updateSelectionCount(modal);
            });
        });

        modal.querySelector('#cloud-import-select-all').onclick = () => {
            modal.querySelectorAll('.cloud-import-row').forEach((row) => {
                if (!this.isRowVisible(row)) return;
                const box = row.querySelector('.cloud-import-checkbox:not(:disabled)');
                if (box) box.checked = true;
            });
            this.updateSelectionCount(modal);
        };

        modal.querySelector('#cloud-import-clear-all').onclick = () => {
            modal.querySelectorAll('.cloud-import-checkbox:not(:disabled)').forEach((box) => {
                box.checked = false;
            });
            this.updateSelectionCount(modal);
        };
    },

    async importBundle(selection, options = {}) {
        const body = {
            scope: 'bundle',
            selected: selection,
        };
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
        return data;
    },

    async importCategories(type, selected) {
        const res = await fetch('api/admin/cloud-import.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ scope: 'categories', type, selected }),
        });
        const data = await res.json();
        if (!res.ok) {
            throw new Error(data.message || 'Import failed');
        }

        this._status = null;
        return data;
    },

    categoryTypeMeta(type) {
        const labels = {
            menu: { title: 'Menu categories', icon: 'book-open', stat: 'categories_menu' },
            stock: { title: 'Stock categories', icon: 'package', stat: 'categories_stock' },
            distribution: { title: 'Distribution categories', icon: 'truck', stat: 'categories_distribution' },
        };
        return labels[type] || labels.menu;
    },

    async showCategoryPicker(type, options = {}) {
        const status = await this.getStatus();
        if (!status.available) {
            alert(status.is_legacy_tenant
                ? 'This hotel already uses the template database.'
                : 'Cloud import is not available right now.');
            return null;
        }

        const meta = this.categoryTypeMeta(type);
        const platformName = status.platform_name || window.PLATFORM_NAME || 'Platform';
        const cloudCount = status.counts?.[meta.stat] ?? 0;
        const modal = this.ensureModal();

        modal.querySelector('#cloud-import-title').textContent = `Import ${meta.title.toLowerCase()} from ${platformName}`;
        modal.querySelector('#cloud-import-subtitle').textContent =
            `${cloudCount} ${meta.title.toLowerCase()} in the cloud. Select the categories you want, then submit.`;

        const searchInput = modal.querySelector('#cloud-import-search');
        if (searchInput) {
            searchInput.placeholder = `Search ${meta.title.toLowerCase()}…`;
        }

        const listEl = modal.querySelector('#cloud-import-list');
        listEl.innerHTML = `<p class="text-sm text-gray-400 text-center py-12">Loading ${meta.title.toLowerCase()}…</p>`;
        modal.classList.remove('hidden');

        let result;
        try {
            result = await this.fetchCategories(type);
        } catch (err) {
            listEl.innerHTML = `<p class="text-sm text-red-400 text-center py-12">${this.escapeHtml(err.message)}</p>`;
            return null;
        }

        const items = result.items || [];
        const html = items.length
            ? `<section class="cloud-import-section space-y-2">
                <div class="flex items-center gap-2 mb-3">
                    <i data-lucide="${meta.icon}" class="w-4 h-4 text-[#1d6b4a]"></i>
                    <h3 class="text-sm font-bold uppercase tracking-wider text-gray-300">${meta.title} (${items.length})</h3>
                </div>
                <div class="space-y-1.5">${items.map((cat) => this.renderCategoryRow(cat, type)).join('')}</div>
            </section>`
            : '';

        if (!html.trim()) {
            listEl.innerHTML = `<p class="text-sm text-gray-400 text-center py-12">No ${meta.title.toLowerCase()} available to import.</p>`;
            modal.querySelector('#cloud-import-submit').disabled = true;
            if (typeof lucide !== 'undefined') lucide.createIcons();
            return null;
        }

        listEl.innerHTML = html;
        this.bindPickerEvents(modal);
        this.updateSelectionCount(modal);
        if (typeof lucide !== 'undefined') lucide.createIcons();

        return new Promise((resolve) => {
            const submitBtn = modal.querySelector('#cloud-import-submit');
            submitBtn.onclick = async () => {
                const selected = [];
                modal.querySelectorAll(`.cloud-import-checkbox[data-kind="category_${type}"]:not(:disabled):checked`).forEach((box) => {
                    if (box.value) selected.push(box.value);
                });

                if (!selected.length) {
                    alert('Select at least one category to import.');
                    return;
                }

                submitBtn.disabled = true;
                submitBtn.textContent = 'Importing…';

                try {
                    const data = await this.importCategories(type, selected);
                    this.closeModal();
                    alert(data.message || 'Import completed.');
                    if (typeof options.onSuccess === 'function') {
                        options.onSuccess(data);
                    }
                    resolve(data);
                } catch (err) {
                    alert(err.message || 'Import failed');
                    resolve(null);
                } finally {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Import selected';
                }
            };
        });
    },

    async showMenuPicker(options = {}) {
        const status = await this.getStatus();
        if (!status.available) {
            alert(status.is_legacy_tenant
                ? 'This hotel already uses the master menu database — all template items are already here.'
                : 'Cloud import is not available right now.');
            return null;
        }

        const platformName = status.platform_name || window.PLATFORM_NAME || 'Platform';
        const cloudCount = status.counts?.menu_items ?? 0;
        const modal = this.ensureModal();

        modal.querySelector('#cloud-import-title').textContent = `Import menu from ${platformName}`;
        modal.querySelector('#cloud-import-subtitle').textContent =
            `${cloudCount} menu items in the cloud. Search a category, check only what you need, then submit.`;

        const listEl = modal.querySelector('#cloud-import-list');
        listEl.innerHTML = '<p class="text-sm text-gray-400 text-center py-12">Loading menu lists…</p>';
        modal.classList.remove('hidden');

        let bundle;
        try {
            bundle = await this.fetchBundle(options);
        } catch (err) {
            listEl.innerHTML = `<p class="text-sm text-red-400 text-center py-12">${this.escapeHtml(err.message)}</p>`;
            return null;
        }

        const html = this.renderGroupSection(
            `Menu items (${bundle.menu?.groups?.reduce((n, g) => n + (g.items?.length || 0), 0) || 0})`,
            'utensils',
            'cloud-import-menu',
            bundle.menu?.groups || [],
            'menu'
        );

        if (!html.trim()) {
            listEl.innerHTML = '<p class="text-sm text-gray-400 text-center py-12">No menu items available to import.</p>';
            modal.querySelector('#cloud-import-submit').disabled = true;
            if (typeof lucide !== 'undefined') lucide.createIcons();
            return null;
        }

        listEl.innerHTML = html;
        this.bindPickerEvents(modal);
        this.updateSelectionCount(modal);
        if (typeof lucide !== 'undefined') lucide.createIcons();

        return this._bindSubmit(modal, options, (selection) => ({
            categories: { menu: selection.categories.menu, stock: [] },
            menus: selection.menus,
            stocks: [],
        }));
    },

    async showStorePicker(options = {}) {
        const status = await this.getStatus();
        if (!status.available) {
            alert(status.is_legacy_tenant
                ? 'This hotel already uses the master store database.'
                : 'Cloud import is not available right now.');
            return null;
        }

        const platformName = status.platform_name || window.PLATFORM_NAME || 'Platform';
        const cloudCount = status.counts?.stocks ?? 0;
        const modal = this.ensureModal();

        modal.querySelector('#cloud-import-title').textContent = `Import bulk inventory from ${platformName}`;
        modal.querySelector('#cloud-import-subtitle').textContent =
            `${cloudCount} store items in the cloud. Search a category, check only what you need, then submit.`;

        const listEl = modal.querySelector('#cloud-import-list');
        listEl.innerHTML = '<p class="text-sm text-gray-400 text-center py-12">Loading store lists…</p>';
        modal.classList.remove('hidden');

        let bundle;
        try {
            bundle = await this.fetchBundle(options);
        } catch (err) {
            listEl.innerHTML = `<p class="text-sm text-red-400 text-center py-12">${this.escapeHtml(err.message)}</p>`;
            return null;
        }

        const html = this.renderGroupSection(
            `Bulk inventory (${bundle.store?.groups?.reduce((n, g) => n + (g.items?.length || 0), 0) || 0})`,
            'package',
            'cloud-import-store',
            bundle.store?.groups || [],
            'stock'
        );

        if (!html.trim()) {
            listEl.innerHTML = '<p class="text-sm text-gray-400 text-center py-12">No store items available to import.</p>';
            modal.querySelector('#cloud-import-submit').disabled = true;
            if (typeof lucide !== 'undefined') lucide.createIcons();
            return null;
        }

        listEl.innerHTML = html;
        this.bindPickerEvents(modal);
        this.updateSelectionCount(modal);
        if (typeof lucide !== 'undefined') lucide.createIcons();

        return this._bindSubmit(modal, options, (selection) => ({
            categories: { menu: [], stock: selection.categories.stock },
            menus: [],
            stocks: selection.stocks,
        }));
    },

    _bindSubmit(modal, options, mapSelection) {
        return new Promise((resolve) => {
            const submitBtn = modal.querySelector('#cloud-import-submit');
            submitBtn.onclick = async () => {
                const raw = this.collectSelection(modal);
                const payload = mapSelection(raw);
                const total = payload.categories.menu.length
                    + payload.categories.stock.length
                    + payload.menus.length
                    + payload.stocks.length;

                if (!total) {
                    alert('Select at least one item to import.');
                    return;
                }

                submitBtn.disabled = true;
                submitBtn.textContent = 'Importing…';

                try {
                    const data = await this.importBundle(payload, options);
                    this.closeModal();
                    alert(data.message || 'Import completed.');
                    if (typeof options.onSuccess === 'function') {
                        options.onSuccess(data);
                    }
                    resolve(data);
                } catch (err) {
                    alert(err.message || 'Import failed');
                    resolve(null);
                } finally {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Import selected';
                }
            };
        });
    },

    async showBundlePicker(options = {}) {
        const status = await this.getStatus();
        if (!status.available) {
            alert(status.is_legacy_tenant
                ? 'This hotel already uses the template database.'
                : 'Import is not available right now.');
            return null;
        }

        const platformName = status.platform_name || window.PLATFORM_NAME || 'Platform';
        const modal = this.ensureModal();

        modal.querySelector('#cloud-import-title').textContent = `Import from ${platformName}`;
        modal.querySelector('#cloud-import-subtitle').textContent =
            'Select menu categories, menu items, store categories, and store inventory from the pre-loaded platform data.';

        const listEl = modal.querySelector('#cloud-import-list');
        listEl.innerHTML = '<p class="text-sm text-gray-400 text-center py-12">Loading lists…</p>';
        modal.classList.remove('hidden');

        let bundle;
        try {
            bundle = await this.fetchBundle(options);
        } catch (err) {
            listEl.innerHTML = `<p class="text-sm text-red-400 text-center py-12">${this.escapeHtml(err.message)}</p>`;
            return null;
        }

        const html = this.renderBundle(bundle);
        if (!html.trim()) {
            listEl.innerHTML = '<p class="text-sm text-gray-400 text-center py-12">No items available to import.</p>';
            modal.querySelector('#cloud-import-submit').disabled = true;
            if (typeof lucide !== 'undefined') lucide.createIcons();
            return null;
        }

        listEl.innerHTML = html;
        this.bindPickerEvents(modal);
        this.updateSelectionCount(modal);

        if (options.focus) {
            const target = modal.querySelector(`#cloud-import-${options.focus}`);
            if (target) {
                setTimeout(() => target.scrollIntoView({ behavior: 'smooth', block: 'start' }), 100);
            }
        }

        if (typeof lucide !== 'undefined') lucide.createIcons();

        return this._bindSubmit(modal, options, (selection) => selection);
    },

    async run(scope, options = {}) {
        if (scope === 'menus') {
            return this.showMenuPicker(options);
        }
        if (scope === 'stocks') {
            return this.showStorePicker(options);
        }
        if (scope === 'categories') {
            const type = options.type || 'menu';
            if (['menu', 'stock', 'distribution'].includes(type)) {
                return this.showCategoryPicker(type, options);
            }
            return this.showBundlePicker(options);
        }
        return this.showBundlePicker(options);
    },

    async renderStoreButton(containerId, options = {}) {
        const el = document.getElementById(containerId);
        if (!el) return;

        try {
            const status = await this.getStatus();
            if (!status.available) {
                el.classList.add('hidden');
                el.innerHTML = '';
                return;
            }

            const label = this.buttonLabel(status.platform_name);
            const count = status.counts?.stocks ?? 0;
            el.classList.remove('hidden');
            el.innerHTML = `
                <i data-lucide="cloud-download" class="w-3.5 h-3.5"></i>
                <span>${label}</span>`;

            el.onclick = async () => {
                el.disabled = true;
                el.style.opacity = '0.7';
                try {
                    await this.showStorePicker({
                        ...options,
                        onSuccess: options.onSuccess,
                    });
                } catch (err) {
                    alert(err.message || 'Import failed');
                } finally {
                    el.disabled = false;
                    el.style.opacity = '';
                }
            };

            if (typeof lucide !== 'undefined') lucide.createIcons();

            const hint = el.parentElement?.querySelector('.store-cloud-import-hint');
            if (hint) {
                hint.textContent = `${count} bulk items in cloud`;
                hint.classList.remove('hidden');
            }
        } catch (err) {
            el.classList.add('hidden');
        }
    },

    async renderMenuButton(containerId, options = {}) {
        const el = document.getElementById(containerId);
        if (!el) return;

        try {
            const status = await this.getStatus();
            if (!status.available) {
                el.classList.add('hidden');
                el.innerHTML = '';
                return;
            }

            const label = this.buttonLabel(status.platform_name);
            const count = status.counts?.menu_items ?? 0;
            el.classList.remove('hidden');
            el.innerHTML = `
                <button type="button" class="w-full flex items-center justify-center gap-2 py-2.5 rounded-lg border text-xs font-bold uppercase tracking-wider transition-colors"
                    style="background:#f0faf5;border-color:#c5d5cc;color:#1d6b4a">
                    <i data-lucide="cloud-download" class="w-4 h-4"></i>
                    <span>${label}</span>
                </button>
                <p class="text-[10px] text-gray-500 mt-2 text-center">${count} menu items in cloud</p>`;

            el.querySelector('button')?.addEventListener('click', async () => {
                const btn = el.querySelector('button');
                if (btn) { btn.disabled = true; btn.style.opacity = '0.7'; }
                try {
                    await this.showMenuPicker(options);
                } catch (err) {
                    alert(err.message || 'Import failed');
                } finally {
                    if (btn) { btn.disabled = false; btn.style.opacity = ''; }
                }
            });

            if (typeof lucide !== 'undefined') lucide.createIcons();
        } catch (err) {
            el.classList.add('hidden');
            el.innerHTML = '';
        }
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

window.CloudImportUI = CloudImportUI;
}
