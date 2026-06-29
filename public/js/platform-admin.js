/**
 * Platform Super Admin — Hotel management
 */
const state = {
    tenants: [],
    revealedPasswords: {},
    editing: null,
    form: {
        name: '',
        slug: '',
        owner_name: '',
        owner_username: '',
        owner_password: '',
        plan: 'starter',
        status: 'active',
    },
};

document.addEventListener('DOMContentLoaded', () => {
    fetchTenants();
    document.getElementById('tenant-form')?.addEventListener('submit', handleFormSubmit);
    document.getElementById('field-name')?.addEventListener('input', autoSlug);
    initPlatformBranding();
    if (typeof lucide !== 'undefined') lucide.createIcons();
});

function initPlatformBranding() {
    const nameInput = document.getElementById('brand-app-name');
    const tagInput = document.getElementById('brand-app-tagline');
    const saveBtn = document.getElementById('brand-save-btn');
    const logoFile = document.getElementById('brand-logo-file');
    const faviconFile = document.getElementById('brand-favicon-file');

    nameInput?.addEventListener('input', updateBrandPreview);
    tagInput?.addEventListener('input', updateBrandPreview);
    saveBtn?.addEventListener('click', savePlatformBranding);
    logoFile?.addEventListener('change', (e) => uploadBrandImage(e.target.files[0], 'logo'));
    faviconFile?.addEventListener('change', (e) => uploadBrandImage(e.target.files[0], 'favicon'));
}

function updateBrandPreview() {
    const name = document.getElementById('brand-app-name')?.value || '';
    const tag = document.getElementById('brand-app-tagline')?.value || '';
    const nameEl = document.getElementById('brand-name-preview');
    const tagEl = document.getElementById('brand-tag-preview');
    const tabEl = document.getElementById('brand-tab-preview');
    if (nameEl) nameEl.textContent = name;
    if (tagEl) tagEl.textContent = tag;
    if (tabEl) tabEl.textContent = name;
}

async function putPlatformSetting(key, value) {
    const res = await fetch('api/platform/settings.php', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ key, value }),
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.message || 'Failed to save setting');
    return data;
}

async function savePlatformBranding() {
    try {
        const name = document.getElementById('brand-app-name')?.value?.trim() || '';
        const tagline = document.getElementById('brand-app-tagline')?.value?.trim() || '';
        if (!name) throw new Error('Website name is required');

        await putPlatformSetting('app_name', name);
        await putPlatformSetting('app_tagline', tagline);
        showToast('Website branding saved');
        document.title = `Platform Admin - ${name}`;
    } catch (err) {
        showToast(err.message, 'error');
    }
}

function compressBrandImage(file, size) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => {
            const img = new Image();
            img.onload = () => {
                const canvas = document.createElement('canvas');
                canvas.width = size;
                canvas.height = size;
                const ctx = canvas.getContext('2d');
                const scale = Math.max(size / img.width, size / img.height);
                const w = img.width * scale;
                const h = img.height * scale;
                ctx.drawImage(img, (size - w) / 2, (size - h) / 2, w, h);
                resolve(canvas.toDataURL('image/jpeg', 0.9));
            };
            img.onerror = () => reject(new Error('Could not read image'));
            img.src = reader.result;
        };
        reader.onerror = () => reject(new Error('Could not read file'));
        reader.readAsDataURL(file);
    });
}

async function uploadBrandImage(file, type) {
    if (!file) return;
    if (!file.type.startsWith('image/')) {
        showToast('Please choose an image file', 'error');
        return;
    }
    if (file.size > 5 * 1024 * 1024) {
        showToast('File too large (max 5MB)', 'error');
        return;
    }

    try {
        if (type === 'logo') {
            const logo = await compressBrandImage(file, 200);
            const favicon = await compressBrandImage(file, 64);
            await putPlatformSetting('logo_url', logo);
            await putPlatformSetting('favicon_url', favicon);
            setBrandImagePreviews('api/platform/branding-image.php?type=logo', 'api/platform/branding-image.php?type=favicon');
        } else {
            const favicon = await compressBrandImage(file, 64);
            await putPlatformSetting('favicon_url', favicon);
            setBrandImagePreviews(null, 'api/platform/branding-image.php?type=favicon&t=' + Date.now());
        }
        showToast(type === 'logo' ? 'Logo and favicon uploaded' : 'Favicon uploaded');
    } catch (err) {
        showToast(err.message, 'error');
    }
}

function setBrandImagePreviews(logoUrl, faviconUrl) {
    const logo = document.getElementById('brand-logo-preview');
    const fallback = document.getElementById('brand-logo-fallback');
    const favicon = document.getElementById('brand-favicon-preview');

    if (logoUrl && logo) {
        logo.src = logoUrl + (logoUrl.includes('?') ? '&' : '?') + 't=' + Date.now();
        logo.style.display = '';
        if (fallback) fallback.style.display = 'none';
    }
    if (faviconUrl && favicon) {
        favicon.src = faviconUrl;
        favicon.style.display = '';
    }
}

async function fetchTenants() {
    try {
        const res = await fetch('api/platform/tenants.php');
        const data = await res.json();
        if (!res.ok) throw new Error(data.message || 'Failed to load hotels');
        state.tenants = data.data || [];
        renderStats();
        renderTable();
    } catch (err) {
        showToast(err.message, 'error');
    }
}

function renderStats() {
    const total = state.tenants.length;
    const active = state.tenants.filter(t => (t.status || 'active') === 'active').length;
    document.getElementById('stat-total').textContent = total;
    document.getElementById('stat-active').textContent = active;
}

function esc(str) {
    const d = document.createElement('div');
    d.textContent = str ?? '';
    return d.innerHTML;
}

function renderTable() {
    const body = document.getElementById('tenants-body');
    if (!body) return;

    if (!state.tenants.length) {
        body.innerHTML = '<tr><td colspan="9" class="px-6 py-10 text-center" style="color:#5c6f68">No hotels registered yet.</td></tr>';
        return;
    }

    body.innerHTML = state.tenants.map(t => {
        const isActive = (t.status || 'active') === 'active';
        const isDefault = t.id === 'default';
        const revealed = !!state.revealedPasswords[t.id];
        const password = t.owner_password || '';
        const created = t.created_at ? new Date(t.created_at).toLocaleDateString() : '—';
        const paidUntilDate = t.paid_until ? new Date(t.paid_until) : null;
        const paidUntilLabel = paidUntilDate ? paidUntilDate.toLocaleDateString() : '—';
        const expired = paidUntilDate ? Date.now() > paidUntilDate.getTime() : false;

        return `
            <tr class="border-b align-top" style="border-color:#e2ebe6" onmouseover="this.style.background='#f0faf5'" onmouseout="this.style.background=''">
                <td class="px-4 py-4 font-medium">${esc(t.name)}</td>
                <td class="px-4 py-4 font-mono text-xs" style="color:#5c6f68">${esc(t.slug)}</td>
                <td class="px-4 py-4" style="color:#3d5249">${esc(t.owner_name || '—')}</td>
                <td class="px-4 py-4 font-mono text-xs" style="color:#1d6b4a">@${esc(t.owner_username || '—')}</td>
                <td class="px-4 py-4">
                    <div class="flex items-center gap-2">
                        <span class="font-mono text-xs" style="color:#5c6f68">${revealed ? esc(password || '—') : '••••••••'}</span>
                        ${revealed && !password ? '<span class="text-[9px] block mt-1" style="color:#b45309">Click Verify to confirm password</span>' : ''}
                        <button type="button" onclick="togglePassword('${t.id}')" style="color:#9aada4" onmouseover="this.style.color='#1d6b4a'" onmouseout="this.style.color='#9aada4'" title="Reveal password">
                            <i data-lucide="${revealed ? 'eye-off' : 'eye'}" class="w-4 h-4"></i>
                        </button>
                        ${revealed && !password ? `<button type="button" onclick="verifyPassword('${t.id}')" class="text-[9px] font-bold uppercase tracking-wider" style="color:#1d6b4a">Verify</button>` : ''}
                    </div>
                </td>
                <td class="px-4 py-4" style="color:#5c6f68">${esc(t.plan || 'starter')}</td>
                <td class="px-4 py-4 text-xs" style="color:${expired ? '#b91c1c' : '#7a8f85'}">${esc(paidUntilLabel)}${expired ? ' (expired)' : ''}</td>
                <td class="px-4 py-4">
                    <span class="inline-flex px-2 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider ${isActive ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-600'}">
                        ${isActive ? 'active' : 'inactive'}
                    </span>
                </td>
                <td class="px-4 py-4 text-xs" style="color:#7a8f85">${esc(created)}</td>
                <td class="px-4 py-4">
                    <div class="flex flex-wrap gap-2">
                        <button type="button" onclick="openEdit('${t.id}')" class="px-2.5 py-1.5 rounded-lg text-[10px] font-bold uppercase tracking-wider" style="background:#f0faf5;border:1px solid #e2ebe6;color:#1d6b4a">Edit</button>
                        <button type="button" onclick="toggleStatus('${t.id}')" class="px-2.5 py-1.5 rounded-lg text-[10px] font-bold uppercase tracking-wider" style="background:#f0faf5;border:1px solid #e2ebe6;color:#5c6f68">
                            ${isActive ? 'Deactivate' : 'Activate'}
                        </button>
                        ${isDefault ? '' : `<button type="button" onclick="deleteTenant('${t.id}')" class="px-2.5 py-1.5 rounded-lg bg-red-50 border border-red-200 text-red-600 text-[10px] font-bold uppercase tracking-wider">Delete</button>`}
                    </div>
                </td>
            </tr>
        `;
    }).join('');

    lucide.createIcons();
}

window.togglePassword = (id) => {
    state.revealedPasswords[id] = !state.revealedPasswords[id];
    renderTable();
};

window.verifyPassword = async (id) => {
    const tenant = state.tenants.find(t => t.id === id);
    if (!tenant) return;
    const password = prompt(`Enter the owner password for "${tenant.name}" to verify and save for viewing.\n\nThis does NOT change the password.`);
    if (!password) return;

    try {
        const res = await fetch(`api/platform/tenants.php?id=${id}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'verify_password', password }),
        });
        const data = await res.json();
        if (!res.ok) throw new Error(data.message);
        showToast(data.message || 'Password saved', 'success');
        fetchTenants();
        state.revealedPasswords[id] = true;
    } catch (err) {
        alert(err.message);
    }
};

window.openCreate = () => {
    state.editing = null;
    state.form = { name: '', slug: '', owner_name: '', owner_username: '', owner_password: '', plan: 'starter', status: 'active' };
    document.getElementById('modal-title').textContent = 'New Hotel';
    document.getElementById('password-hint').textContent = 'Min. 8 characters';
    fillForm();
    document.getElementById('tenant-modal').classList.remove('hidden');
};

window.openEdit = (id) => {
    const tenant = state.tenants.find(t => t.id === id);
    if (!tenant) return;
    state.editing = tenant;
    state.form = {
        name: tenant.name || '',
        slug: tenant.slug || '',
        owner_name: tenant.owner_name || '',
        owner_username: tenant.owner_username || '',
        owner_password: '',
        plan: tenant.plan || 'starter',
        status: tenant.status || 'active',
    };
    document.getElementById('modal-title').textContent = 'Edit Hotel';
    document.getElementById('password-hint').textContent = 'Leave blank to keep current password';
    fillForm();
    document.getElementById('tenant-modal').classList.remove('hidden');
};

window.closeModal = () => {
    document.getElementById('tenant-modal').classList.add('hidden');
};

function fillForm() {
    const f = state.form;
    document.getElementById('field-name').value = f.name;
    document.getElementById('field-slug').value = f.slug;
    document.getElementById('field-owner-name').value = f.owner_name;
    document.getElementById('field-owner-username').value = f.owner_username;
    document.getElementById('field-owner-password').value = f.owner_password;
    document.getElementById('field-plan').value = f.plan;
    document.getElementById('field-status').value = f.status;
    document.getElementById('owner-fields').classList.toggle('hidden', !!state.editing && state.editing.id === 'default');

    const paidUntil = state.editing?.paid_until || '';
    const paidField = document.getElementById('field-paid-until');
    if (paidField) {
        paidField.value = paidUntil || '';
    }

    const confirmBtn = document.getElementById('confirm-payment-btn');
    if (confirmBtn) {
        confirmBtn.disabled = !state.editing;
        confirmBtn.style.opacity = state.editing ? '' : '0.4';
        confirmBtn.onclick = async () => {
            if (!state.editing) return;
            if (!confirm(`Confirm payment for "${state.editing.name}" and extend 1 month?`)) return;
            try {
                const res = await fetch(`api/platform/tenants.php?id=${state.editing.id}`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'confirm_payment', months: 1 }),
                });
                const data = await res.json();
                if (!res.ok) throw new Error(data.message || 'Request failed');
                showToast(data.message || 'Payment confirmed', 'success');
                closeModal();
                fetchTenants();
            } catch (err) {
                alert(err.message);
            }
        };
    }
}

function autoSlug() {
    if (state.editing) return;
    const name = document.getElementById('field-name').value;
    document.getElementById('field-slug').value = name.toLowerCase().trim()
        .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 48);
}

async function handleFormSubmit(e) {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    btn.disabled = true;

    const payload = {
        name: document.getElementById('field-name').value.trim(),
        slug: document.getElementById('field-slug').value.trim(),
        owner_name: document.getElementById('field-owner-name').value.trim(),
        owner_username: document.getElementById('field-owner-username').value.trim(),
        plan: document.getElementById('field-plan').value,
        status: document.getElementById('field-status').value,
    };

    const password = document.getElementById('field-owner-password').value;
    if (password) payload.owner_password = password;

    const url = state.editing ? `api/platform/tenants.php?id=${state.editing.id}` : 'api/platform/tenants.php';
    const method = state.editing ? 'PUT' : 'POST';

    if (!state.editing) {
        if (!payload.owner_name || !payload.owner_username || !password) {
            alert('Owner name, username, and password are required for new hotels.');
            btn.disabled = false;
            return;
        }
        payload.owner_password = password;
    }

    try {
        const res = await fetch(url, {
            method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const data = await res.json();
        if (!res.ok) throw new Error(data.message || 'Request failed');
        closeModal();
        showToast(data.message || 'Saved', 'success');
        fetchTenants();
    } catch (err) {
        alert(err.message);
    } finally {
        btn.disabled = false;
    }
}

window.toggleStatus = async (id) => {
    const tenant = state.tenants.find(t => t.id === id);
    if (!tenant) return;
    const next = (tenant.status || 'active') === 'active' ? 'inactive' : 'active';
    const action = next === 'inactive' ? 'deactivate' : 'activate';
    if (!confirm(`${action.charAt(0).toUpperCase() + action.slice(1)} "${tenant.name}"?`)) return;

    try {
        const res = await fetch(`api/platform/tenants.php?id=${id}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ status: next }),
        });
        const data = await res.json();
        if (!res.ok) throw new Error(data.message);
        showToast(data.message || 'Status updated', 'success');
        fetchTenants();
    } catch (err) {
        alert(err.message);
    }
};

window.deleteTenant = async (id) => {
    const tenant = state.tenants.find(t => t.id === id);
    if (!tenant) return;
    if (!confirm(`Permanently delete "${tenant.name}" and all its data? This cannot be undone.`)) return;

    try {
        const res = await fetch(`api/platform/tenants.php?id=${id}`, { method: 'DELETE' });
        const data = await res.json();
        if (!res.ok) throw new Error(data.message);
        showToast(data.message || 'Hotel deleted', 'success');
        fetchTenants();
    } catch (err) {
        alert(err.message);
    }
};

function showToast(message, type = 'info') {
    const el = document.getElementById('toast');
    if (!el) return;
    el.textContent = message;
    el.className = `fixed bottom-6 right-6 z-50 px-5 py-3 rounded-xl text-sm font-semibold shadow-2xl transition-all ${type === 'error' ? 'bg-red-500 text-white' : 'text-white'}`;
    el.style.background = type === 'error' ? '' : '#1d6b4a';
    el.classList.remove('hidden');
    setTimeout(() => el.classList.add('hidden'), 3000);
}
