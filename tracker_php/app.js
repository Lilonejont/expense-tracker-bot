/* ============================================================
   Expense Tracker — app.js
   WhySpent-style Telegram MiniApp with haptic feedback,
   SVG category icons, blur effects, and fullscreen support
   ============================================================ */

const app = {
    /* ---- Telegram WebApp ---- */
    tg: window.Telegram?.WebApp || {
        initDataUnsafe: { user: { first_name: 'Dev', username: 'dev_user', id: 1 } },
        initData: '',
        ready() {},
        expand() {},
        requestFullscreen() {},
        disableVerticalSwipes() {},
        enableClosingConfirmation() {},
        colorScheme: 'light',
        isExpanded: false,
        HapticFeedback: {
            impactOccurred() {},
            notificationOccurred() {},
            selectionChanged() {}
        }
    },

    /* ---- SVG icons for categories (inline, no emoji) ---- */
    catIcons: {
        'Еда':           { color: '#FF9500', bg: 'rgba(255,149,0,.12)', svg: '<path d="M18 8h1a4 4 0 0 1 0 8h-1"/><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8Z"/><line x1="6" y1="1" x2="6" y2="4"/><line x1="10" y1="1" x2="10" y2="4"/><line x1="14" y1="1" x2="14" y2="4"/>' },
        'Транспорт':     { color: '#0A84FF', bg: 'rgba(10,132,255,.12)', svg: '<path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12v4c0 .6.4 1 1 1h2"/><circle cx="7" cy="17" r="2"/><path d="M9 17h6"/><circle cx="17" cy="17" r="2"/>' },
        'Дом':           { color: '#34C759', bg: 'rgba(52,199,89,.12)', svg: '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>' },
        'Развлечения':   { color: '#AF52DE', bg: 'rgba(175,82,222,.12)', svg: '<line x1="6" y1="12" x2="10" y2="12"/><line x1="8" y1="10" x2="8" y2="14"/><line x1="15" y1="13" x2="15.01" y2="13"/><line x1="18" y1="11" x2="18.01" y2="11"/><rect width="20" height="12" x="2" y="6" rx="2"/>' },
        'Одежда':        { color: '#FF2D55', bg: 'rgba(255,45,85,.12)', svg: '<path d="M20.38 3.46 16 2 12 5.5 8 2l-4.38 1.46a2 2 0 0 0-1.34 1.88v10.32a2 2 0 0 0 1.34 1.88L8 19l4-3.5L16 19l4.38-1.46a2 2 0 0 0 1.34-1.88V5.34a2 2 0 0 0-1.34-1.88Z"/>' },
        'Здоровье':      { color: '#FF3B30', bg: 'rgba(255,59,48,.12)', svg: '<path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/>' },
        'Связь':         { color: '#5AC8FA', bg: 'rgba(90,200,250,.12)', svg: '<rect width="14" height="20" x="5" y="2" rx="2" ry="2"/><path d="M12 18h.01"/>' },
        'Образование':   { color: '#FFD60A', bg: 'rgba(255,214,10,.12)', svg: '<path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 10 3 12 0v-5"/>' },
        'Работа':        { color: '#8E8E93', bg: 'rgba(142,142,147,.12)', svg: '<rect width="20" height="14" x="2" y="7" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>' },
        'Красота':       { color: '#FF6482', bg: 'rgba(255,100,130,.12)', svg: '<circle cx="12" cy="12" r="3"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/>' },
        'Другое':        { color: '#8E8E93', bg: 'rgba(142,142,147,.12)', svg: '<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/>' },
    },

    /* ---- Default icon for unknown categories ---- */
    defaultIcon: { color: '#8E8E93', bg: 'rgba(142,142,147,.12)', svg: '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>' },

    /* ---- State ---- */
    state: {
        activeTab: 'home',
        activeTabIndex: 0,
        homePeriod: 'day',
        statsPeriod: 'month',
        statsType: 'categories',
        theme: 'system',
        currency: '₽',
        categories: [],
        sheetAmount: '0',
        sheetCategoryId: null,
        sheetEditId: null,
        searchTimeout: null,
        chartInstance: null
    },

    /* ================================================================
       INIT
       ================================================================ */
    init() {
        try { this.state.theme = localStorage.getItem('theme') || 'light'; } catch(e){}

        /* Telegram fullscreen setup */
        this.tg.ready();
        this.tg.expand();

        /* Request fullscreen (Telegram Bot API 8.0+) */
        try { this.tg.requestFullscreen(); } catch(e){}
        try { this.tg.disableVerticalSwipes(); } catch(e){}
        try { this.tg.enableClosingConfirmation(); } catch(e){}
        try { this.tg.isVerticalSwipesEnabled = false; } catch(e){}

        /* Apply TG safe areas to CSS */
        this.applySafeAreas();

        /* Listen for safe area changes */
        try {
            this.tg.onEvent('safeAreaChanged', () => this.applySafeAreas());
            this.tg.onEvent('contentSafeAreaChanged', () => this.applySafeAreas());
            this.tg.onEvent('viewportChanged', () => this.applySafeAreas());
            this.tg.onEvent('fullscreenChanged', () => this.applySafeAreas());
        } catch(e){}

        /* Set header/bg colors */
        try {
            const isDark = document.documentElement.classList.contains('dark');
            this.tg.setHeaderColor(isDark ? '#000000' : '#f5f5f7');
            this.tg.setBackgroundColor(isDark ? '#000000' : '#f5f5f7');
        } catch(e){}

        /* Theme */
        this.apiCall = this.request.bind(this);
        this.setTheme(this.state.theme);

        /* User */
        this.setupUser();

        /* Bind events */
        this.bindEvents();

        /* Load data */
        this.loadCategories().then(() => {
            this.loadHome();
            this.loadSettings();
        });
    },

    /* ================================================================
       SAFE AREAS — read from TG SDK and apply to CSS vars
       ================================================================ */
    applySafeAreas() {
        const root = document.documentElement;
        const tg = this.tg;

        /* safeAreaInset — device physical insets (notch, home indicator) */
        if (tg.safeAreaInset) {
            root.style.setProperty('--tg-safe-area-inset-top', (tg.safeAreaInset.top || 0) + 'px');
            root.style.setProperty('--tg-safe-area-inset-bottom', (tg.safeAreaInset.bottom || 0) + 'px');
            root.style.setProperty('--tg-safe-area-inset-left', (tg.safeAreaInset.left || 0) + 'px');
            root.style.setProperty('--tg-safe-area-inset-right', (tg.safeAreaInset.right || 0) + 'px');
        }

        /* contentSafeAreaInset — TG header area in fullscreen */
        if (tg.contentSafeAreaInset) {
            root.style.setProperty('--tg-content-safe-area-inset-top', (tg.contentSafeAreaInset.top || 0) + 'px');
            root.style.setProperty('--tg-content-safe-area-inset-bottom', (tg.contentSafeAreaInset.bottom || 0) + 'px');
            root.style.setProperty('--tg-content-safe-area-inset-left', (tg.contentSafeAreaInset.left || 0) + 'px');
            root.style.setProperty('--tg-content-safe-area-inset-right', (tg.contentSafeAreaInset.right || 0) + 'px');
        }

        /* Update viewport height for 100dvh fallback */
        root.style.setProperty('--vh', (tg.viewportStableHeight || window.innerHeight) * 0.01 + 'px');
    },

    /* ================================================================
       HAPTIC HELPERS
       ================================================================ */
    haptic(type, style) {
        try {
            if (type === 'impact') this.tg.HapticFeedback.impactOccurred(style || 'light');
            else if (type === 'notification') this.tg.HapticFeedback.notificationOccurred(style || 'success');
            else if (type === 'selection') this.tg.HapticFeedback.selectionChanged();
        } catch(e){}
        /* Browser vibrate fallback */
        try {
            if (navigator.vibrate) {
                if (type === 'impact' && style === 'heavy') navigator.vibrate(30);
                else if (type === 'impact' && style === 'medium') navigator.vibrate(20);
                else if (type === 'notification' && style === 'error') navigator.vibrate([20, 50, 20]);
                else if (type === 'notification' && style === 'success') navigator.vibrate([10, 30, 10]);
                else navigator.vibrate(10);
            }
        } catch(e){}
    },

    /* ================================================================
       USER SETUP
       ================================================================ */
    setupUser() {
        const user = this.tg.initDataUnsafe?.user;
        if (user) {
            const name = user.first_name + (user.last_name ? ' ' + user.last_name : '');
            document.getElementById('profile-name').textContent = name;
            document.getElementById('profile-username').textContent = user.username ? '@' + user.username : '';
            
            if (user.photo_url) {
                const img = document.getElementById('profile-photo');
                img.src = user.photo_url;
                img.classList.remove('hidden');
                document.getElementById('profile-avatar').classList.add('hidden');
            } else {
                document.getElementById('profile-avatar').textContent = user.first_name.charAt(0).toUpperCase();
            }
        }
    },

    /* ================================================================
       EVENTS
       ================================================================ */
    bindEvents() {
        /* Home period buttons */
        document.querySelectorAll('#screen-home .period-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                document.querySelectorAll('#screen-home .period-btn').forEach(b => b.classList.remove('active'));
                e.target.classList.add('active');
                this.state.homePeriod = e.target.dataset.period;
                this.haptic('selection');
                this.loadHome();
            });
        });

        /* Stats period buttons */
        document.querySelectorAll('#stats-period-selector .period-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                document.querySelectorAll('#stats-period-selector .period-btn').forEach(b => b.classList.remove('active'));
                e.target.classList.add('active');
                this.state.statsPeriod = e.target.dataset.period;
                this.haptic('selection');
                this.loadStats();
            });
        });

        /* Stats type selector */
        document.querySelectorAll('#stats-type-selector .period-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                document.querySelectorAll('#stats-type-selector .period-btn').forEach(b => b.classList.remove('active'));
                e.target.classList.add('active');
                this.state.statsType = e.target.dataset.type;
                this.haptic('selection');
                this.loadStats();
            });
        });

        /* History search */
        const searchInput = document.getElementById('history-search');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                clearTimeout(this.state.searchTimeout);
                this.state.searchTimeout = setTimeout(() => {
                    this.loadHistory(e.target.value);
                }, 400);
            });
        }
    },

    /* ================================================================
       CURRENCY & SHARED BUDGET
       ================================================================ */
    updateCurrencyCheck() {
        document.querySelectorAll('.curr-check').forEach(el => el.classList.add('hidden'));
        const check = document.querySelector(`.curr-check[data-curr-check="${this.state.currency}"]`);
        if (check) check.classList.remove('hidden');

        // Update add-sheet currency symbol
        const sheetCurr = document.getElementById('sheet-currency');
        if (sheetCurr) sheetCurr.textContent = this.state.currency;
    },

    async setCurrency(curr) {
        this.state.currency = curr;
        try { localStorage.setItem('currency', curr); } catch(e){}
        this.haptic('selection');
        this.updateCurrencyCheck();

        // Update server
        await this.request('set-currency', { curr }, 'POST');

        // Refresh all screens
        this.loadHome();
        if (this.state.activeTab === 'stats') this.loadStats();
        if (this.state.activeTab === 'history') this.loadHistory();
    },

    async invitePartner() {
        this.haptic('impact', 'medium');
        const res = await this.request('send-invite', {}, 'POST');
        if (res && res.invite_link) {
            const shareUrl = "https://t.me/share/url?url=" + encodeURIComponent(res.invite_link) + "&text=" + encodeURIComponent("🤝 Приглашаю вести общий учет расходов в этом боте!");
            if (this.tg && this.tg.openTelegramLink) {
                this.tg.openTelegramLink(shareUrl);
            } else {
                window.open(shareUrl, '_blank');
            }
        } else {
            const msg = 'Ссылка для общего бюджета отправлена в чат с ботом! Перешлите её вашему партнеру.';
            if (this.tg && this.tg.showAlert) {
                this.tg.showAlert(msg);
            } else {
                alert(msg);
            }
        }
    },

    async loadSettings() {
        const res = await this.request('get_settings', {}, 'GET');
        console.log("LOADSETTINGS", res);
        
        const conn = document.getElementById('shared-budget-connected');
        const unconn = document.getElementById('shared-budget-unconnected');
        
        if (res && res.is_shared) {
            if (conn) {
                conn.classList.remove('hidden');
                conn.style.display = 'block'; // Force display just in case
            }
            if (unconn) {
                unconn.classList.add('hidden');
                unconn.style.display = 'none';
            }
            
            const pName = document.getElementById('shared-partner-name');
            const pUser = document.getElementById('shared-partner-username');
            if (res.partner) {
                if (pName) pName.textContent = res.partner.first_name || 'Партнер';
                if (pUser) pUser.textContent = res.partner.username || ('ID: ' + res.partner.id);
            }
        } else {
            if (conn) {
                conn.classList.add('hidden');
                conn.style.display = 'none';
            }
            if (unconn) {
                unconn.classList.remove('hidden');
                unconn.style.display = 'block';
            }
        }
        
        if (res && res.ok && res.currency) {
            try {
                this.state.currency = res.currency;
                this.updateCurrencyCheck();
            } catch(e) { console.error(e); }
        }

        // Load limits
        this.loadLimits();
    },

    /* ── LIMITS MANAGEMENT ──────────────────────────────────────── */
    async loadLimits() {
        const res = await this.request('get-limits', {}, 'GET');
        const listEl = document.getElementById('limits-list');
        const emptyEl = document.getElementById('limits-empty');
        if (!listEl) return;

        if (res && res.ok && res.limits && res.limits.length > 0) {
            listEl.innerHTML = '';
            res.limits.forEach(lim => {
                const row = document.createElement('div');
                row.className = 'settings-row ripple-wrap';
                row.style.borderBottom = '1px solid var(--border-default)';
                row.innerHTML = `
                    <div class="flex items-center gap-3">
                        <span class="text-[18px]">${lim.emoji || '📁'}</span>
                        <div>
                            <div class="text-[15px] font-medium">${lim.name}</div>
                            <div class="text-[13px]" style="color: var(--text-secondary)">${Number(lim.limit_amount).toLocaleString('ru-RU')} ${this.state.currency || '₽'}/мес</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span onclick="event.stopPropagation(); app.deleteLimit(${lim.category_id}, '${lim.name}')" 
                              style="color: #FF3B30; padding: 6px; cursor: pointer; border-radius: 8px;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
                        </span>
                    </div>
                `;
                listEl.appendChild(row);
            });
        } else {
            listEl.innerHTML = '<div class="p-4 text-center text-[14px]" style="color: var(--text-secondary)">Лимиты не установлены</div>';
        }
    },

    async showAddLimitDialog() {
        this.haptic('impact', 'medium');
        // Load categories
        const catRes = await this.request('category-list', {}, 'GET');
        if (!catRes || !catRes.categories) {
            this.showAlert('Не удалось загрузить категории');
            return;
        }

        // Build options list
        const cats = catRes.categories;
        const catOptions = cats.map(c => `${c.emoji} ${c.name}`);

        if (this.tg && this.tg.showPopup) {
            // Use simple prompt approach: first select category via popup, then ask amount
            const buttons = cats.slice(0, 8).map((c, i) => ({
                id: String(c.id),
                type: 'default',
                text: `${c.emoji} ${c.name}`
            }));
            buttons.push({ id: 'cancel', type: 'destructive', text: 'Отмена' });

            this.tg.showPopup({
                title: 'Выберите категорию',
                message: 'Для какой категории установить лимит?',
                buttons: buttons.slice(0, 3) // TG popup supports max 3 buttons
            }, (btnId) => {
                if (btnId === 'cancel' || !btnId) return;
                const catId = parseInt(btnId);
                const cat = cats.find(c => c.id === catId);
                if (!cat) return;

                // Ask for amount
                const amount = prompt(`Введите лимит в месяц для "${cat.emoji} ${cat.name}" (или 0 для удаления):`);
                if (amount === null) return;
                const numAmount = parseFloat(amount.replace(',', '.'));
                if (isNaN(numAmount) || numAmount < 0) {
                    this.showAlert('Некорректная сумма');
                    return;
                }
                this.saveLimit(catId, numAmount);
            });
        } else {
            // Fallback: use prompt
            const catListStr = cats.map(c => `${c.id}: ${c.emoji} ${c.name}`).join('\n');
            const catIdStr = prompt(`Введите номер категории:\n\n${catListStr}`);
            if (!catIdStr) return;
            const catId = parseInt(catIdStr);
            const cat = cats.find(c => c.id === catId);
            if (!cat) { this.showAlert('Категория не найдена'); return; }

            const amount = prompt(`Лимит для "${cat.emoji} ${cat.name}" в месяц:`);
            if (!amount) return;
            const numAmount = parseFloat(amount.replace(',', '.'));
            if (isNaN(numAmount) || numAmount < 0) { this.showAlert('Некорректная сумма'); return; }
            this.saveLimit(catId, numAmount);
        }
    },

    async saveLimit(categoryId, amount) {
        const res = await this.request('set-limit', { category_id: categoryId, amount: amount }, 'POST');
        if (res && res.ok) {
            this.haptic('notification', 'success');
            this.loadLimits();
        } else {
            this.showAlert('Ошибка при сохранении лимита');
        }
    },

    async deleteLimit(categoryId, catName) {
        this.haptic('impact', 'medium');
        const confirmed = confirm(`Удалить лимит для "${catName}"?`);
        if (!confirmed) return;

        const res = await this.request('set-limit', { category_id: categoryId, amount: 0 }, 'POST');
        if (res && res.ok) {
            this.haptic('notification', 'success');
            this.loadLimits();
        }
    },

    async unlinkPartner() {
        const msg = 'Вы уверены, что хотите отключить общий бюджет? Ваши расходы больше не будут синхронизироваться с партнером.';
        const doUnlink = async () => {
            this.haptic('impact', 'medium');
            const res = await this.request('unlink_partner', {}, 'POST');
            if (res && res.ok) {
                this.haptic('notification', 'success');
                this.loadSettings();
                this.loadHome();
            }
        };

        if (this.tg && this.tg.showConfirm) {
            this.tg.showConfirm(msg, (confirmed) => {
                if (confirmed) doUnlink();
            });
        } else if (confirm(msg)) {
            doUnlink();
        }
    },
    /* ================================================================
       THEME
       ================================================================ */
    setTheme(theme) {
        this.state.theme = theme;
        try { localStorage.setItem('theme', theme); } catch(e){}

        document.querySelectorAll('.theme-check').forEach(el => el.classList.add('hidden'));
        const check = document.querySelector(`.theme-check[data-theme-check="${theme}"]`);
        if (check) check.classList.remove('hidden');

        let isDark = false;
        if (theme === 'system') {
            isDark = (this.tg.colorScheme === 'dark') || window.matchMedia('(prefers-color-scheme: dark)').matches;
        } else {
            isDark = (theme === 'dark');
        }

        document.documentElement.classList.toggle('dark', isDark);

        /* Update TG header/bg colors */
        try {
            this.tg.setHeaderColor(isDark ? '#000000' : '#f5f5f7');
            this.tg.setBackgroundColor(isDark ? '#000000' : '#f5f5f7');
        } catch(e){}

        this.haptic('selection');

        /* Redraw chart if on stats */
        if (this.state.activeTab === 'stats' && this.state.chartInstance) {
            setTimeout(() => this.loadStats(), 100);
        }
    },

    /* ================================================================
       TAB SWITCHING
       ================================================================ */
    switchTab(index, tabName) {
        if (this.state.activeTab === tabName) {
            // Scroll to top if tapping already active tab (iOS behavior)
            const activeScreen = document.getElementById(`screen-${tabName}`);
            if (activeScreen) {
                activeScreen.scrollTo({ top: 0, behavior: 'smooth' });
                this.haptic('impact', 'light');
            }
            return;
        }

        this.haptic('impact', 'light');

        /* Slide indicator */
        const slider = document.getElementById('nav-slider');
        slider.style.transform = `translateX(${index * 80}px)`;

        /* Active class */
        document.querySelectorAll('.liquid-glass-nav-item').forEach(item => {
            item.classList.toggle('active', parseInt(item.dataset.tab) === index);
        });

        /* Show/hide screens */
        document.querySelectorAll('.screen').forEach(s => s.classList.remove('active'));
        document.getElementById(`screen-${tabName}`).classList.add('active');

        /* Show/hide plus button */
        const plusBtn = document.getElementById('plus-btn-wrap');
        if (plusBtn) {
            plusBtn.style.display = (tabName === 'home' || tabName === 'history') ? '' : 'none';
        }

        this.state.activeTab = tabName;
        this.state.activeTabIndex = index;

        /* Load data */
        if (tabName === 'home') this.loadHome();
        else if (tabName === 'stats') this.loadStats();
        else if (tabName === 'history') this.loadHistory();
        else if (tabName === 'settings') this.loadSettings();
    },

    /* ================================================================
       API REQUEST
       ================================================================ */
    async request(action, params = {}, method = 'GET', body = null) {
        let url = `api.php?action=${action}`;
        for (const [k, v] of Object.entries(params)) {
            if (v !== undefined && v !== null) url += `&${k}=${encodeURIComponent(v)}`;
        }

        const initData = this.tg.initData;

        const options = { method, headers: {} };
        if (initData) {
            options.headers['X-Telegram-Init-Data'] = initData;
        }
        if (body) {
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(body);
        }

        try {
            const res = await fetch(url, options);
            const json = await res.json();
            if (json.ok) return json.data !== undefined ? json.data : json;
            console.error('API Error:', json.error);
            return null;
        } catch (e) {
            console.error('Fetch Error:', e);
            return null;
        }
    },

    /* ================================================================
       CATEGORY ICON HELPER
       ================================================================ */
    getCatIcon(catName) {
        return this.catIcons[catName] || this.defaultIcon;
    },

    makeCatIconHTML(catName, size) {
        const icon = this.getCatIcon(catName);
        const s = size || 44;
        const svgS = Math.round(s * 0.5);
        const br = Math.round(s * 0.32);
        return `<div class="cat-icon-wrap" style="width:${s}px;height:${s}px;border-radius:${br}px;background:${icon.bg}">
            <svg xmlns="http://www.w3.org/2000/svg" width="${svgS}" height="${svgS}" viewBox="0 0 24 24" fill="none" stroke="${icon.color}" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">${icon.svg}</svg>
        </div>`;
    },

    /* ================================================================
       LOAD CATEGORIES
       ================================================================ */
    async loadCategories() {
        const data = await this.request('category_list');
        if (data) this.state.categories = data;
    },

    /* ================================================================
       HOME SCREEN
       ================================================================ */
    async loadHome() {
        const summary = await this.request('summary', { period: this.state.homePeriod });
        if (summary) {
            if (summary.currency && summary.currency !== this.state.currency) {
                this.state.currency = summary.currency;
                try { localStorage.setItem('currency', summary.currency); } catch(e){}
                this.updateCurrencyCheck();
            }
            this.state.limits = summary.limits || {};
            document.getElementById('home-total').textContent = this.formatMoney(summary.total);
            document.getElementById('home-count').textContent = summary.count;
            document.getElementById('home-avg').textContent = this.formatMoney(summary.average_per_day || summary.avg || 0);
        }

        const expenses = await this.request('expenses', { period: this.state.homePeriod, limit: 10 });
        const list = document.getElementById('home-recent-list');
        list.innerHTML = '';

        if (expenses && expenses.length > 0) {
            expenses.forEach((exp, i) => {
                const item = this.makeTxItem(exp);
                if (i < expenses.length - 1) {
                    item.style.borderBottom = '1px solid var(--border-default)';
                }
                list.appendChild(item);
            });
            this.animateListItems(list);
        } else {
            list.innerHTML = `
                <div class="empty-state">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                    <div class="text-[14px]">Нет операций</div>
                    <div class="text-[12px] mt-1" style="opacity:.6">Нажмите + чтобы добавить</div>
                </div>`;
        }
    },

    /* ================================================================
       STATS SCREEN
       ================================================================ */
    async loadStats() {
        if (this.state.statsType === 'categories') {
            document.getElementById('stats-list-title').textContent = 'Расходы по категориям';
            const data = await this.request('categories', { period: this.state.statsPeriod });
            this.renderCategoryStats(data || []);
        } else {
            document.getElementById('stats-list-title').textContent = 'Расходы по дням';
            const data = await this.request('daily', { period: this.state.statsPeriod });
            this.renderDailyStats(data || []);
        }
    },

    renderCategoryStats(data) {
        const list = document.getElementById('stats-list');
        list.innerHTML = '';

        const labels = [], values = [], colors = [];
        const palette = ['#FF9500', '#0A84FF', '#34C759', '#AF52DE', '#FF2D55', '#FF3B30', '#5AC8FA', '#FFD60A', '#8E8E93', '#FF6482', '#30B0C7'];

        if (data.length === 0) {
            list.innerHTML = '<div class="empty-state"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/></svg><div class="text-[14px]">Нет данных</div></div>';
            this.renderChart('doughnut', ['Нет'], [1], ['rgba(142,142,147,.2)']);
            return;
        }

        const total = data.reduce((sum, item) => sum + parseFloat(item.total), 0);

        data.forEach((item, i) => {
            const icon = this.getCatIcon(item.name);
            const color = icon.color || palette[i % palette.length];
            labels.push(item.name);
            values.push(parseFloat(item.total));
            colors.push(color);

            const percent = total > 0 ? ((item.total / total) * 100).toFixed(1) : 0;

            const el = document.createElement('div');
            el.className = 'glass-card p-4 flex items-center gap-3';
            const limit = this.state.limits && this.state.limits[item.id];
            let limitHtml = '';
            if (limit) {
                const limPct = Math.min(100, Math.round((item.total / limit) * 100));
                const limColor = limPct >= 100 ? '#FF3B30' : (limPct >= 80 ? '#FF9500' : '#34C759');
                limitHtml = ` · <span style="color:${limColor};font-weight:600">Лимит: ${this.formatMoney(limit)} (${limPct}%)</span>`;
            }
            el.innerHTML = `
                ${this.makeCatIconHTML(item.name, 40)}
                <div class="flex-1 min-w-0">
                    <div class="flex justify-between items-center mb-1.5">
                        <span class="font-semibold text-[14px] truncate">${item.name}</span>
                        <span class="font-bold text-[15px] ml-2 flex-shrink-0 tnum">${this.formatMoney(item.total)}</span>
                    </div>
                    <div class="stat-bar">
                        <div class="stat-bar-fill" style="width:${percent}%;background:${color}"></div>
                    </div>
                    <div class="text-[11px] mt-1" style="color:var(--text-secondary)">${percent}% · ${item.count || 0} операций${limitHtml}</div>
                </div>`;
            list.appendChild(el);
        });

        this.animateListItems(list);
        this.renderChart('doughnut', labels, values, colors);
    },

    renderDailyStats(data) {
        const list = document.getElementById('stats-list');
        list.innerHTML = '';

        const labels = [], values = [];

        if (data.length === 0) {
            list.innerHTML = '<div class="empty-state"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg><div class="text-[14px]">Нет данных</div></div>';
            return;
        }

        data.forEach((item) => {
            const dateLabel = this.formatDateLabel(item.date);
            labels.push(dateLabel);
            values.push(parseFloat(item.total));

            const el = document.createElement('div');
            el.className = 'glass-card p-4 flex justify-between items-center';
            el.innerHTML = `
                <span class="font-medium text-[14px]">${dateLabel}</span>
                <span class="font-bold text-[15px] tnum">${this.formatMoney(item.total)}</span>`;
            list.appendChild(el);
        });

        this.animateListItems(list);
        this.renderChart('bar', labels, values, '#0A84FF');
    },

    renderChart(type, labels, data, colors) {
        const canvas = document.getElementById('stats-chart');
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        if (this.state.chartInstance) this.state.chartInstance.destroy();

        const isDark = document.documentElement.classList.contains('dark');
        const textColor = isDark ? 'rgba(255,255,255,.7)' : 'rgba(0,0,0,.6)';
        const gridColor = isDark ? 'rgba(255,255,255,.06)' : 'rgba(0,0,0,.06)';

        this.state.chartInstance = new Chart(ctx, {
            type,
            data: {
                labels,
                datasets: [{
                    data,
                    backgroundColor: colors,
                    borderRadius: type === 'bar' ? 6 : 0,
                    borderWidth: type === 'doughnut' ? 2 : 0,
                    borderColor: isDark ? '#000' : '#fff',
                    hoverOffset: type === 'doughnut' ? 8 : 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 600, easing: 'easeOutQuart' },
                cutout: type === 'doughnut' ? '65%' : undefined,
                plugins: {
                    legend: {
                        display: type === 'doughnut',
                        position: 'right',
                        labels: { color: textColor, font: { family: 'system-ui', size: 11 }, boxWidth: 12, padding: 12 }
                    },
                    tooltip: {
                        backgroundColor: isDark ? 'rgba(40,40,40,.95)' : 'rgba(255,255,255,.95)',
                        titleColor: isDark ? '#fff' : '#000',
                        bodyColor: isDark ? '#ccc' : '#333',
                        borderColor: isDark ? 'rgba(255,255,255,.1)' : 'rgba(0,0,0,.1)',
                        borderWidth: 1,
                        cornerRadius: 12,
                        padding: 12,
                        displayColors: true,
                        callbacks: {
                            label: (ctx) => ' ' + parseFloat(ctx.parsed || ctx.parsed.y || 0).toLocaleString('ru-RU') + ' ₽'
                        }
                    }
                },
                scales: type === 'bar' ? {
                    y: { ticks: { color: textColor, font: { size: 11 } }, grid: { color: gridColor }, border: { display: false } },
                    x: { ticks: { color: textColor, maxTicksLimit: 7, font: { size: 10 } }, grid: { display: false }, border: { display: false } }
                } : {}
            }
        });
    },

    /* ================================================================
       HISTORY SCREEN
       ================================================================ */
    async loadHistory(search) {
        const expenses = await this.request('expenses', { period: 'all', limit: 100, search: search || '' });
        const list = document.getElementById('history-list');
        list.innerHTML = '';

        if (expenses && expenses.length > 0) {
            let currentDate = '';
            let groupContainer = null;

            expenses.forEach((exp, i) => {
                const dateStr = exp.created_at ? exp.created_at.split(' ')[0] : '';
                if (dateStr !== currentDate) {
                    currentDate = dateStr;

                    const header = document.createElement('div');
                    header.className = 'text-[13px] font-semibold uppercase tracking-wider sticky-date';
                    header.style.color = 'var(--text-secondary)';
                    // if (i > 0) header.style.marginTop = '1rem'; // Let css handle spacing for stickiness
                    header.textContent = this.formatDateLabel(currentDate);
                    list.appendChild(header);

                    groupContainer = document.createElement('div');
                    groupContainer.className = 'card-level-1 overflow-hidden';
                    list.appendChild(groupContainer);
                }

                const item = this.makeTxItem(exp);
                item.style.borderRadius = '0';
                item.style.boxShadow = 'none';
                item.style.backgroundColor = 'transparent';
                item.style.border = 'none';
                groupContainer.appendChild(item);

                /* Separator line between items in a group */
                const nextExp = expenses[i + 1];
                const nextDate = nextExp ? (nextExp.created_at ? nextExp.created_at.split(' ')[0] : '') : '';
                if (nextDate === currentDate) {
                    const sep = document.createElement('div');
                    sep.style.cssText = 'height:1px;background:var(--border-default);margin-left:72px;margin-right:16px;';
                    groupContainer.appendChild(sep);
                }
            });
            this.animateListItems(list);
        } else {
            list.innerHTML = `
                <div class="empty-state">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                    <div class="text-[14px]">Ничего не найдено</div>
                </div>`;
        }
    },

    /* ================================================================
       TRANSACTION ITEM (used in Home + History)
       ================================================================ */
    makeTxItem(exp) {
        const el = document.createElement('div');
        el.className = 'tx-item';

        const catName = exp.category_name || 'Другое';
        const iconHTML = this.makeCatIconHTML(catName, 44);
        const time = exp.created_at ? exp.created_at.split(' ')[1]?.substring(0, 5) || '' : '';

        el.innerHTML = `
            <div class="flex items-center gap-3 min-w-0 flex-1">
                ${iconHTML}
                <div class="min-w-0">
                    <div class="font-semibold text-[15px] truncate">${catName}</div>
                    <div class="text-[12px] truncate" style="color:var(--text-secondary)">${exp.description || time || 'Без описания'}</div>
                </div>
            </div>
            <div class="font-bold text-[16px] ml-3 flex-shrink-0 tnum" style="color:var(--danger-color)">
                −${this.formatMoney(exp.amount)}
            </div>`;

        /* Click to edit */
        el.addEventListener('click', () => {
            this.haptic('impact', 'light');
            this.openAddSheet(exp);
        });

        return el;
    },

    /* ================================================================
       MONEY FORMATTER
       ================================================================ */
    formatMoney(val) {
        const num = parseFloat(val) || 0;
        const cur = (this && this.state && this.state.currency) ? this.state.currency : '₽';
        return num.toLocaleString('ru-RU', { maximumFractionDigits: 2 }) + ' ' + cur;
    },

    formatDateLabel(dateStr) {
        if (!dateStr) return '';
        const today = new Date().toISOString().split('T')[0];
        const yesterday = new Date(Date.now() - 86400000).toISOString().split('T')[0];
        if (dateStr === today) return 'Сегодня';
        if (dateStr === yesterday) return 'Вчера';
        const months = ['янв', 'фев', 'мар', 'апр', 'мая', 'июн', 'июл', 'авг', 'сен', 'окт', 'ноя', 'дек'];
        const [y, m, d] = dateStr.split('-');
        return `${parseInt(d)} ${months[parseInt(m) - 1]} ${y}`;
    },

    /* ================================================================
       ADD/EDIT BOTTOM SHEET
       ================================================================ */
    openAddSheet(editData) {
        this.haptic('impact', 'medium');
        this.state.sheetEditId = editData ? editData.id : null;
        this.state.sheetAmount = editData ? String(editData.amount) : '0';
        document.getElementById('sheet-note').value = editData ? (editData.description || '') : '';
        this.updateSheetDisplay();

        /* Categories */
        const catContainer = document.getElementById('sheet-categories');
        catContainer.innerHTML = '';

        this.state.categories.forEach((cat, i) => {
            const isSelected = editData ? (cat.id == editData.category_id) : (i === 0);
            if (isSelected) this.state.sheetCategoryId = cat.id;

            const icon = this.getCatIcon(cat.name);
            const chip = document.createElement('div');
            chip.className = `cat-chip${isSelected ? ' selected' : ''}`;
            chip.setAttribute('role', 'button');
            chip.setAttribute('tabindex', '0');
            chip.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="${isSelected ? 'white' : icon.color}" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">${icon.svg}</svg>${cat.name}`;

            chip.addEventListener('click', () => {
                this.haptic('selection');
                this.state.sheetCategoryId = cat.id;
                catContainer.querySelectorAll('.cat-chip').forEach(c => {
                    if (!c.dataset.isDelete) {
                        c.className = 'cat-chip';
                        const ci = this.getCatIcon(c.textContent.trim());
                        const svg = c.querySelector('svg');
                        if (svg) svg.setAttribute('stroke', ci.color);
                    }
                });
                chip.className = 'cat-chip selected';
                const svg = chip.querySelector('svg');
                if (svg) svg.setAttribute('stroke', 'white');
                chip.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
            });
            catContainer.appendChild(chip);
        });

        /* Delete button if editing */
        if (editData) {
            const delChip = document.createElement('div');
            delChip.dataset.isDelete = 'true';
            delChip.className = 'cat-chip';
            delChip.style.cssText = 'background:rgba(255,59,48,.1);color:#FF3B30;border-color:rgba(255,59,48,.2);margin-left:auto';
            delChip.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#FF3B30" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>Удалить';
            delChip.addEventListener('click', async () => {
                this.haptic('notification', 'warning');
                if (confirm('Удалить эту запись?')) {
                    await this.request('delete_expense', {}, 'POST', { id: editData.id });
                    this.haptic('notification', 'success');
                    this.closeAddSheet();
                    this.showToast('Запись удалена');
                    this.refreshCurrentTab();
                }
            });
            catContainer.appendChild(delChip);
        }

        /* Show sheet */
        document.getElementById('sheet-overlay').classList.add('active');
        document.getElementById('add-sheet').classList.add('active');
    },

    closeAddSheet() {
        document.getElementById('sheet-overlay').classList.remove('active');
        document.getElementById('add-sheet').classList.remove('active');
        this.state.sheetEditId = null;
        this.state.sheetAmount = '0';
    },

    /* ================================================================
       NUMERIC KEYBOARD
       ================================================================ */
    keyPress(key) {
        this.haptic('impact', 'light');

        if (key === 'del') {
            this.state.sheetAmount = this.state.sheetAmount.slice(0, -1) || '0';
        } else if (key === '.') {
            if (!this.state.sheetAmount.includes('.')) {
                this.state.sheetAmount += '.';
            }
        } else {
            if (this.state.sheetAmount === '0') {
                this.state.sheetAmount = key;
            } else {
                const parts = this.state.sheetAmount.split('.');
                if (parts.length > 1 && parts[1].length >= 2) return;
                if (this.state.sheetAmount.length < 10) {
                    this.state.sheetAmount += key;
                }
            }
        }
        this.updateSheetDisplay();
    },

    updateSheetDisplay() {
        const el = document.getElementById('sheet-amount');
        const val = this.state.sheetAmount;
        if (val.endsWith('.')) {
            el.textContent = val;
        } else {
            const num = parseFloat(val);
            el.textContent = isNaN(num) ? '0' : num.toLocaleString('ru-RU', { maximumFractionDigits: 2 });
        }
    },

    /* ================================================================
       SAVE TRANSACTION
       ================================================================ */
    async saveTransaction() {
        const amount = parseFloat(this.state.sheetAmount);
        if (isNaN(amount) || amount <= 0) {
            this.haptic('notification', 'error');
            const el = document.getElementById('sheet-amount');
            el.style.color = 'var(--danger-color)';
            setTimeout(() => el.style.color = '', 400);
            return;
        }

        this.haptic('impact', 'heavy');

        const payload = {
            amount,
            category_id: this.state.sheetCategoryId,
            description: document.getElementById('sheet-note').value.trim()
        };

        let result;
        if (this.state.sheetEditId) {
            payload.id = this.state.sheetEditId;
            result = await this.request('edit_expense', {}, 'POST', payload);
        } else {
            result = await this.request('add_expense', {}, 'POST', payload);
        }

        if (result !== null) {
            this.haptic('notification', 'success');
            this.showToast(this.state.sheetEditId ? 'Запись обновлена' : 'Расход добавлен');
        } else {
            this.haptic('notification', 'error');
        }

        this.closeAddSheet();
        this.refreshCurrentTab();
    },

    /* ================================================================
       TOAST NOTIFICATION
       ================================================================ */
    showToast(text) {
        const toast = document.getElementById('toast');
        const toastText = document.getElementById('toast-text');
        if (!toast || !toastText) return;
        toastText.textContent = text;
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 2500);
    },

    /* ================================================================
       REFRESH
       ================================================================ */
    refreshCurrentTab() {
        if (this.state.activeTab === 'home') this.loadHome();
        else if (this.state.activeTab === 'stats') this.loadStats();
        else if (this.state.activeTab === 'history') this.loadHistory();
    },

    /* ================================================================
       EXPORT
       ================================================================ */
    async exportData() {
        this.haptic('impact', 'medium');
        const res = await this.request('export_csv_chat', {}, 'POST');
        if (res && res.ok) {
            const msg = `📊 Файл расходов (${res.count || 0} записей) успешно отправлен в ваш чат с ботом!`;
            if (this.tg && this.tg.showAlert) {
                this.tg.showAlert(msg);
            } else {
                alert(msg);
            }
        } else {
            window.open('api.php?action=export_csv&period=all', '_blank');
        }
    },

    /* ================================================================
       SCROLL EFFECTS — sticky header elevation + scroll-reveal
       ================================================================ */
    initScrollEffects() {
        /* 1. Sticky search bar elevation on scroll */
        const historyScreen = document.getElementById('screen-history');
        const searchWrap = document.getElementById('history-search-wrap');
        if (historyScreen && searchWrap) {
            historyScreen.addEventListener('scroll', () => {
                searchWrap.classList.toggle('scrolled', historyScreen.scrollTop > 4);
            }, { passive: true });
        }

        /* 2. Scroll-reveal for cards on all screens */
        const io = new IntersectionObserver((entries) => {
            entries.forEach((entry, i) => {
                if (entry.isIntersecting) {
                    entry.target.style.transitionDelay = (i * 40) + 'ms';
                    entry.target.classList.add('visible');
                    io.unobserve(entry.target);
                }
            });
        }, { threshold: 0.05 });

        document.querySelectorAll('.card-level-1, .glass-card, .tx-item').forEach(el => {
            if (!el.classList.contains('fade-up')) {
                el.classList.add('fade-up');
            }
            io.observe(el);
        });

        /* 3. Re-observe when new cards are injected */
        const mutation = new MutationObserver(() => {
            document.querySelectorAll('.card-level-1:not(.fade-up), .glass-card:not(.fade-up), .tx-item:not(.fade-up)').forEach(el => {
                el.classList.add('fade-up');
                io.observe(el);
            });
        });
        mutation.observe(document.getElementById('app-container'), { childList: true, subtree: true });
    },

    /* ================================================================
       STAGGERED ITEM ANIMATION — call after rendering lists
       ================================================================ */
    animateListItems(list) {
        if (!list) return;
        const items = Array.from(list.children);
        items.forEach((el, i) => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(14px)';
            el.style.transition = `opacity .38s cubic-bezier(.32,.72,0,1) ${i * 35}ms, transform .38s cubic-bezier(.32,.72,0,1) ${i * 35}ms`;
            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    el.style.opacity = '1';
                    el.style.transform = 'translateY(0)';
                });
            });
        });
    }
};

/* ---- Start ---- */
document.addEventListener('DOMContentLoaded', () => {
    app.init();
    app.initScrollEffects();
});
