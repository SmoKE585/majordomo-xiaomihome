(() => {
    'use strict';

    const labels = {online: 'Онлайн', offline: 'Офлайн', unknown: 'Нет данных'};

    const post = async (root, op, data = {}) => {
        const body = new URLSearchParams(Object.assign({op, _token: root.dataset.mdCsrf || '', _md_csrf: root.dataset.mdCsrf || ''}, data));
        const response = await fetch(root.dataset.mdAjaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8', 'X-Requested-With': 'XMLHttpRequest'},
            body
        });
        let payload;
        try {
            payload = await response.json();
        } catch (error) {
            throw new Error('Сервер вернул не JSON: ' + response.status);
        }
        if (!payload || payload.ok !== true) {
            throw new Error((payload && payload.error) || ('Ошибка запроса: ' + response.status));
        }
        return payload;
    };

    const toast = (type, title, text) => {
        if (window.MDJUIToast) window.MDJUIToast.show({type, title, text});
    };

    const initRefresh = page => {
        page.addEventListener('submit', async event => {
            const form = event.target.closest('form[data-md-refresh-device]');
            if (!form) return;
            event.preventDefault();
            const button = form.querySelector('button[type="submit"]');
            if (button) button.disabled = true;
            try {
                const payload = await post(page, 'refresh', {device_id: form.dataset.mdRefreshDevice});
                const message = payload.data && payload.data.message ? payload.data.message : 'Команда поставлена в очередь.';
                toast('success', 'Запрос отправлен', message);
            } catch (error) {
                toast('danger', 'Не удалось обновить', error.message);
            } finally {
                if (button) button.disabled = false;
            }
        });
    };
    const initImages = page => {
        page.querySelectorAll('[data-md-xiaomihome-photo]').forEach(img => {
            const wrap = img.closest('[data-md-xiaomihome-image]');
            const loaded = () => wrap.classList.add('md-xiaomihome-image--loaded');
            const failed = () => {
                img.hidden = true;
                wrap.classList.remove('md-xiaomihome-image--loaded');
            };
            img.addEventListener('load', loaded, {once: true});
            img.addEventListener('error', failed, {once: true});
            if (img.complete) {
                if (img.naturalWidth > 0) loaded(); else failed();
            }
        });
    };

    const statusNode = state => {
        const wrap = document.createElement('span');
        wrap.className = 'md-status md-status--' + (['online', 'offline'].includes(state) ? state : 'idle');
        const dot = document.createElement('span');
        dot.className = 'md-status__dot';
        dot.setAttribute('aria-hidden', 'true');
        const label = document.createElement('span');
        label.className = 'md-status__label';
        label.textContent = labels[state] || labels.unknown;
        wrap.append(dot, label);
        return wrap;
    };

    const ageText = item => {
        if (item.age === null || typeof item.age === 'undefined') return 'ещё не отвечало';
        return item.age < 60 ? item.age + ' с назад' : Math.round(item.age / 60) + ' мин назад';
    };

    const applyRow = (row, item) => {
        const status = row.querySelector('[data-md-role="status"]');
        if (status) status.replaceChildren(statusNode(item.state));
        const age = row.querySelector('[data-md-role="age"]');
        if (age) age.textContent = ageText(item);
        const battery = row.querySelector('[data-md-role="battery"]');
        if (battery) {
            battery.textContent = '';
            if (item.battery !== '') {
                const badge = document.createElement('span');
                badge.className = 'md-badge md-badge--' + (item.battery_tone || 'outline');
                badge.textContent = Math.round(Number(item.battery)) + '%';
                battery.append(badge);
            }
        }
        const preview = row.querySelector('[data-md-role="preview"]');
        if (preview) preview.textContent = item.preview === '' ? '—' : item.preview;
        const updated = row.querySelector('[data-md-role="updated"]');
        if (updated) updated.textContent = item.updated || '—';
        row.classList.toggle('is-lost', !!item.lost);
    };

    const initLiveList = page => {
        const list = page.querySelector('[data-md-xiaomihome-list]');
        if (!list || !page.dataset.mdAjaxUrl) return;
        let timer = null;
        let busy = false;

        const refresh = async () => {
            if (busy || document.visibilityState === 'hidden') return;
            busy = true;
            try {
                const response = await fetch(page.dataset.mdAjaxUrl + '?op=rows', {
                    credentials: 'same-origin',
                    headers: {'X-Requested-With': 'XMLHttpRequest'}
                });
                const payload = await response.json();
                if (!payload || payload.ok !== true || !Array.isArray(payload.items)) return;
                const rows = new Map();
                list.querySelectorAll('[data-md-device-id]').forEach(row => rows.set(row.dataset.mdDeviceId, row));
                payload.items.forEach(item => {
                    const row = rows.get(String(item.id));
                    if (row) applyRow(row, item);
                });
            } catch (error) {
                // Тихо: следующая попытка через 8 секунд.
            } finally {
                busy = false;
            }
        };

        const start = () => {
            if (timer) clearInterval(timer);
            timer = setInterval(refresh, 8000);
        };
        start();
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') refresh();
        });
        refresh();
    };

    const init = (root = document) => {
        root.querySelectorAll('[data-md-xiaomihome]').forEach(page => {
            if (page.dataset.mdXiaomihomeReady) return;
            page.dataset.mdXiaomihomeReady = '1';
            initImages(page);
            initLiveList(page);
            initRefresh(page);
        });
    };

    if (window.MDJAdminUI) window.MDJAdminUI.registerModuleUI('xiaomihome', {init});
    else if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => init());
    else init();
})();
