/**
 * Notenux - Retro Gamified Panel & Dynamic Chunked Editor
 */

class RetroAudio {
    constructor() {
        this.ctx = null;
        this.enabled = localStorage.getItem('notenux_sound') !== 'muted';
    }

    init() {
        if (!this.ctx && typeof AudioContext !== 'undefined') {
            const AudioContextClass = window.AudioContext || window.webkitAudioContext;
            this.ctx = new AudioContextClass();
        }
    }

    toggle() {
        this.enabled = !this.enabled;
        localStorage.setItem('notenux_sound', this.enabled ? 'enabled' : 'muted');
        if (this.enabled) {
            this.playCoin();
        }
        return this.enabled;
    }

    playTone(frequency, type = 'square', duration = 0.08, rampTo = null) {
        if (!this.enabled) return;
        try {
            this.init();
            if (this.ctx.state === 'suspended') {
                this.ctx.resume();
            }

            const osc = this.ctx.createOscillator();
            const gain = this.ctx.createGain();

            osc.type = type;
            osc.frequency.setValueAtTime(frequency, this.ctx.currentTime);
            if (rampTo) {
                osc.frequency.exponentialRampToValueAtTime(rampTo, this.ctx.currentTime + duration);
            }

            gain.gain.setValueAtTime(0.08, this.ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, this.ctx.currentTime + duration);

            osc.connect(gain);
            gain.connect(this.ctx.destination);

            osc.start();
            osc.stop(this.ctx.currentTime + duration);
        } catch (e) {
            // Audio context not allowed or unsupported
        }
    }

    playBlip() {
        this.playTone(480, 'square', 0.04);
    }

    playClick() {
        this.playTone(640, 'triangle', 0.03);
    }

    playCoin() {
        this.playTone(987, 'square', 0.08);
        setTimeout(() => this.playTone(1318, 'square', 0.15), 60);
    }

    playLevelUp() {
        const notes = [440, 554, 659, 880];
        notes.forEach((f, i) => {
            setTimeout(() => this.playTone(f, 'square', 0.12), i * 90);
        });
    }

    playWarp() {
        this.playTone(300, 'sawtooth', 0.18, 120);
    }

    playDelete() {
        this.playTone(320, 'sawtooth', 0.12, 110);
    }
}

class NotenuxPanel {
    constructor() {
        this.audio = new RetroAudio();
        this.notes = [];
        this.activeNote = null;
        this.activeCategory = 'all';
        this.searchQuery = '';
        this.saveTimeout = null;
        this.xpGainedSession = 0;

        this.dom = {
            sidebar: document.getElementById('notenux-sidebar'),
            sidebarOverlay: document.getElementById('sidebar-overlay'),
            sidebarToggleBtn: document.getElementById('sidebar-toggle-btn'),
            mobileToggleBtn: document.getElementById('mobile-toggle-btn'),
            closeSidebarBtn: document.getElementById('close-sidebar-btn'),
            themeToggleBtn: document.getElementById('theme-toggle-btn'),
            soundToggleBtn: document.getElementById('sound-toggle-btn'),
            notesList: document.getElementById('notes-list'),
            searchInput: document.getElementById('search-notes-input'),
            newNoteBtn: document.getElementById('new-note-btn'),
            categoryPills: document.querySelectorAll('.category-filter-btn'),
            // Editor elements
            noteTitleInput: document.getElementById('note-title-input'),
            editorArea: document.getElementById('editor-content'),
            fontFamilySelect: document.getElementById('font-family-select'),
            fontSizeSelect: document.getElementById('font-size-select'),
            saveStatusText: document.getElementById('save-status-text'),
            saveNowBtn: document.getElementById('save-now-btn'),
            pinNoteBtn: document.getElementById('pin-note-btn'),
            deleteNoteBtn: document.getElementById('delete-note-btn'),
            inspectChunksBtn: document.getElementById('inspect-chunks-btn'),
            // Gamification stats
            playerLevelBadge: document.getElementById('player-level-badge'),
            playerRankTitle: document.getElementById('player-rank-title'),
            playerXpText: document.getElementById('player-xp-text'),
            playerXpBar: document.getElementById('player-xp-bar'),
            playerManaText: document.getElementById('player-mana-text'),
            wordCountBadge: document.getElementById('word-count-badge'),
            charCountBadge: document.getElementById('char-count-badge'),
            readingTimeBadge: document.getElementById('reading-time-badge'),
            chunkCountBadge: document.getElementById('chunk-count-badge'),
            // Modal elements
            chunkModal: document.getElementById('chunk-inspector-modal'),
            chunkModalClose: document.getElementById('chunk-modal-close'),
            chunkModalContent: document.getElementById('chunk-modal-content'),
            chunkModalCount: document.getElementById('chunk-modal-count'),
            chunkModalBytes: document.getElementById('chunk-modal-bytes'),
        };

        this.init();
    }

    init() {
        this.initTheme();
        this.initSidebar();
        this.initSoundToggle();
        this.initEditorToolbar();
        this.bindEvents();
        this.loadNotesFromDOM();
        this.refreshPlayerStats();
    }

    /* ---------------- THEME & DISPLAY ---------------- */
    initTheme() {
        const storedTheme = localStorage.getItem('notenux_theme');
        const systemDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        const isDark = storedTheme ? storedTheme === 'dark' : systemDark;

        this.setTheme(isDark ? 'dark' : 'light');

        if (this.dom.themeToggleBtn) {
            this.dom.themeToggleBtn.addEventListener('click', () => {
                this.audio.playWarp();
                const currentTheme = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
                const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
                this.setTheme(newTheme);
            });
        }
    }

    setTheme(theme) {
        if (theme === 'dark') {
            document.documentElement.classList.add('dark');
            document.documentElement.setAttribute('data-theme', 'dark');
            if (this.dom.themeToggleBtn) {
                this.dom.themeToggleBtn.innerHTML = '🌙 <span class="hidden sm:inline">NIGHT</span>';
            }
        } else {
            document.documentElement.classList.remove('dark');
            document.documentElement.setAttribute('data-theme', 'light');
            if (this.dom.themeToggleBtn) {
                this.dom.themeToggleBtn.innerHTML = '☀️ <span class="hidden sm:inline">DAY</span>';
            }
        }
        localStorage.setItem('notenux_theme', theme);
    }

    /* ---------------- SIDEBAR INTERACTION ---------------- */
    initSidebar() {
        const isCollapsed = localStorage.getItem('notenux_sidebar') === 'collapsed';
        if (isCollapsed && window.innerWidth >= 1024) {
            this.dom.sidebar?.classList.add('collapsed');
        }

        const toggleSidebar = () => {
            this.audio.playBlip();
            if (window.innerWidth < 1024) {
                // Mobile Drawer Toggle
                const isOpen = !this.dom.sidebar?.classList.contains('-translate-x-full');
                if (isOpen) {
                    this.closeMobileSidebar();
                } else {
                    this.openMobileSidebar();
                }
            } else {
                // Desktop Collapse Toggle
                this.dom.sidebar?.classList.toggle('collapsed');
                const collapsed = this.dom.sidebar?.classList.contains('collapsed');
                localStorage.setItem('notenux_sidebar', collapsed ? 'collapsed' : 'expanded');
            }
        };

        this.dom.sidebarToggleBtn?.addEventListener('click', toggleSidebar);
        this.dom.mobileToggleBtn?.addEventListener('click', toggleSidebar);
        this.dom.closeSidebarBtn?.addEventListener('click', () => this.closeMobileSidebar());
        this.dom.sidebarOverlay?.addEventListener('click', () => this.closeMobileSidebar());
    }

    openMobileSidebar() {
        this.dom.sidebar?.classList.remove('-translate-x-full');
        this.dom.sidebarOverlay?.classList.remove('hidden');
    }

    closeMobileSidebar() {
        this.dom.sidebar?.classList.add('-translate-x-full');
        this.dom.sidebarOverlay?.classList.add('hidden');
    }

    /* ---------------- SOUND TOGGLE ---------------- */
    initSoundToggle() {
        const updateSoundLabel = () => {
            if (this.dom.soundToggleBtn) {
                this.dom.soundToggleBtn.innerHTML = this.audio.enabled
                    ? '🔊 <span class="hidden sm:inline">SFX: ON</span>'
                    : '🔇 <span class="hidden sm:inline">SFX: OFF</span>';
            }
        };

        updateSoundLabel();

        this.dom.soundToggleBtn?.addEventListener('click', () => {
            this.audio.toggle();
            updateSoundLabel();
        });
    }

    /* ---------------- INITIAL DOM STATE ---------------- */
    loadNotesFromDOM() {
        const initialDataEl = document.getElementById('initial-notes-data');
        if (initialDataEl) {
            try {
                this.notes = JSON.parse(initialDataEl.textContent);
            } catch (e) {
                this.notes = [];
            }
        }

        const activeIdEl = document.getElementById('initial-active-note-id');
        const activeId = activeIdEl ? parseInt(activeIdEl.value, 10) : null;

        if (this.notes.length > 0) {
            this.activeNote = this.notes.find(n => n.id === activeId) || this.notes[0];
            this.renderNotesList();
            this.loadNoteIntoEditor(this.activeNote);
        } else {
            this.createNewNote(false);
        }
    }

    /* ---------------- EVENTS ---------------- */
    bindEvents() {
        // Search notes
        this.dom.searchInput?.addEventListener('input', (e) => {
            this.searchQuery = e.target.value.toLowerCase().trim();
            this.renderNotesList();
        });

        // Category filter
        this.dom.categoryPills.forEach(pill => {
            pill.addEventListener('click', () => {
                this.audio.playBlip();
                this.dom.categoryPills.forEach(p => p.classList.remove('active-category', 'bg-blue-600', 'text-white'));
                pill.classList.add('active-category', 'bg-blue-600', 'text-white');
                this.activeCategory = pill.dataset.category || 'all';
                this.renderNotesList();
            });
        });

        // New note button
        this.dom.newNoteBtn?.addEventListener('click', () => {
            this.createNewNote(true);
        });

        // Note title input
        this.dom.noteTitleInput?.addEventListener('input', () => {
            if (!this.activeNote) return;
            this.activeNote.title = this.dom.noteTitleInput.value;
            this.updateActiveNoteInList();
            this.scheduleAutoSave();
        });

        // Editor input
        this.dom.editorArea?.addEventListener('input', () => {
            this.handleEditorInput();
        });

        // Save Now Button
        this.dom.saveNowBtn?.addEventListener('click', () => {
            this.audio.playCoin();
            this.saveActiveNote();
        });

        // Pin Toggle
        this.dom.pinNoteBtn?.addEventListener('click', () => {
            this.togglePinActiveNote();
        });

        // Delete Note
        this.dom.deleteNoteBtn?.addEventListener('click', () => {
            this.deleteActiveNote();
        });

        // Chunk Inspector Modal
        this.dom.inspectChunksBtn?.addEventListener('click', () => {
            this.openChunkInspector();
        });

        this.dom.chunkModalClose?.addEventListener('click', () => {
            this.audio.playBlip();
            this.closeChunkInspector();
        });

        // Font family change
        this.dom.fontFamilySelect?.addEventListener('change', (e) => {
            this.audio.playClick();
            this.applyFontFamily(e.target.value);
            this.scheduleAutoSave();
        });

        // Font size change
        this.dom.fontSizeSelect?.addEventListener('change', (e) => {
            this.audio.playClick();
            this.applyFontSize(e.target.value);
            this.scheduleAutoSave();
        });
    }

    /* ---------------- RICH TEXT TOOLBAR ---------------- */
    initEditorToolbar() {
        document.querySelectorAll('[data-command]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                this.audio.playClick();
                const command = btn.dataset.command;
                const value = btn.dataset.value || null;

                if (command === 'createLink') {
                    const url = prompt('Enter the link URL (https://...):');
                    if (url) {
                        document.execCommand(command, false, url);
                    }
                } else if (command === 'formatBlock' && value) {
                    document.execCommand('formatBlock', false, value);
                } else {
                    document.execCommand(command, false, value);
                }

                this.dom.editorArea.focus();
                this.handleEditorInput();
            });
        });

        // Custom Color pickers
        document.querySelectorAll('[data-color]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                this.audio.playClick();
                const color = btn.dataset.color;
                const type = btn.dataset.colorType || 'foreColor';
                document.execCommand(type, false, color);
                this.dom.editorArea.focus();
                this.handleEditorInput();
            });
        });
    }

    applyFontFamily(font) {
        if (!this.dom.editorArea) return;

        const fontMap = {
            pixel: "'Press Start 2P', monospace",
            arcade: "'VT323', monospace",
            mono: "'Fira Code', ui-monospace, monospace",
            sans: "'Instrument Sans', system-ui, sans-serif",
            serif: "'Merriweather', Georgia, serif",
        };

        this.dom.editorArea.style.fontFamily = fontMap[font] || fontMap.pixel;
        if (this.activeNote) {
            this.activeNote.font_family = font;
        }
    }

    applyFontSize(size) {
        if (!this.dom.editorArea) return;
        this.dom.editorArea.style.fontSize = size;
        if (this.activeNote) {
            this.activeNote.font_size = size;
        }
    }

    /* ---------------- EDITOR INPUT & METRICS ---------------- */
    handleEditorInput() {
        if (!this.activeNote) return;

        this.activeNote.content = this.dom.editorArea.innerHTML;
        this.updateWordAndCharMetrics();
        this.triggerXpBurst();
        this.scheduleAutoSave();
    }

    updateWordAndCharMetrics() {
        const text = this.dom.editorArea.innerText || '';
        const words = text.trim() === '' ? 0 : text.trim().split(/\s+/).length;
        const chars = text.length;
        const minutes = Math.max(1, Math.ceil(words / 180));

        if (this.dom.wordCountBadge) this.dom.wordCountBadge.textContent = `${words} Words`;
        if (this.dom.charCountBadge) this.dom.charCountBadge.textContent = `${chars} Chars`;
        if (this.dom.readingTimeBadge) this.dom.readingTimeBadge.textContent = `⏱️ ${minutes}m Read`;

        if (this.activeNote) {
            this.activeNote.word_count = words;
            this.activeNote.char_count = chars;
        }
    }

    triggerXpBurst() {
        this.xpGainedSession += 1;
        if (this.xpGainedSession % 8 === 0) {
            this.audio.playBlip();
            this.spawnXpPopup('+3 XP');
        }
    }

    spawnXpPopup(text) {
        const popup = document.createElement('div');
        popup.className = 'xp-burst fixed z-50 pointer-events-none font-bold font-mono text-xs text-yellow-400 bg-black/80 px-2 py-0.5 rounded border border-yellow-500 shadow-md';
        popup.textContent = text;

        const rect = this.dom.editorArea.getBoundingClientRect();
        popup.style.left = `${rect.right - 90}px`;
        popup.style.top = `${rect.top + 20}px`;

        document.body.appendChild(popup);
        setTimeout(() => popup.remove(), 1200);
    }

    scheduleAutoSave() {
        if (this.dom.saveStatusText) {
            this.dom.saveStatusText.innerHTML = '<span class="text-amber-500 animate-pulse">● Inscribing...</span>';
        }

        clearTimeout(this.saveTimeout);
        this.saveTimeout = setTimeout(() => {
            this.saveActiveNote();
        }, 800);
    }

    /* ---------------- CRUD & API SYNC ---------------- */
    async saveActiveNote() {
        if (!this.activeNote) return;

        try {
            const payload = {
                title: this.activeNote.title || 'Untitled Scroll',
                category: this.activeNote.category || 'quest',
                content: this.dom.editorArea.innerHTML,
                font_family: this.activeNote.font_family || 'pixel',
                font_size: this.activeNote.font_size || '16px',
                theme_color: this.activeNote.theme_color || 'blue',
                is_pinned: this.activeNote.is_pinned || false,
            };

            const response = await fetch(`/api/notes/${this.activeNote.id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify(payload),
            });

            const data = await response.json();
            if (response.ok && data.status === 'success') {
                this.activeNote = data.data;
                const index = this.notes.findIndex(n => n.id === this.activeNote.id);
                if (index !== -1) {
                    this.notes[index] = data.data;
                }

                if (this.dom.saveStatusText) {
                    this.dom.saveStatusText.innerHTML = '<span class="text-emerald-500">✓ Inscribed & Chunked</span>';
                }

                this.renderNotesList();
                this.refreshPlayerStats();
            } else {
                throw new Error(data.message || 'Failed to save');
            }
        } catch (e) {
            if (this.dom.saveStatusText) {
                this.dom.saveStatusText.innerHTML = '<span class="text-red-500">⚠️ Storage error</span>';
            }
        }
    }

    async createNewNote(selectImmediately = true) {
        this.audio.playLevelUp();
        try {
            const count = this.notes.length + 1;
            const payload = {
                title: `⚔️ Quest Scroll #${count}: New Adventure`,
                category: this.activeCategory !== 'all' ? this.activeCategory : 'quest',
                content: `<h2>⚔️ Chapter ${count}</h2><p>Inscribe your thoughts, strategic lore, or arcane spells here...</p>`,
                font_family: 'pixel',
                font_size: '16px',
                theme_color: 'blue',
            };

            const response = await fetch('/api/notes', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify(payload),
            });

            const data = await response.json();
            if (response.ok && data.status === 'success') {
                const newNote = data.data;
                this.notes.unshift(newNote);
                this.renderNotesList();

                if (selectImmediately) {
                    this.selectNote(newNote);
                }
                this.refreshPlayerStats();
            }
        } catch (e) {
            console.error('Error creating note:', e);
        }
    }

    async togglePinActiveNote() {
        if (!this.activeNote) return;
        this.audio.playClick();

        try {
            const response = await fetch(`/api/notes/${this.activeNote.id}/pin`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
            });

            const data = await response.json();
            if (response.ok) {
                this.activeNote.is_pinned = data.is_pinned;
                this.updatePinButtonUI();
                this.renderNotesList();
            }
        } catch (e) {
            console.error('Error toggling pin:', e);
        }
    }

    async deleteActiveNote() {
        if (!this.activeNote) return;
        if (!confirm(`Are you sure you want to banish scroll "${this.activeNote.title}" to the digital void?`)) {
            return;
        }

        this.audio.playDelete();
        try {
            const response = await fetch(`/api/notes/${this.activeNote.id}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                },
            });

            if (response.ok) {
                this.notes = this.notes.filter(n => n.id !== this.activeNote.id);
                this.activeNote = this.notes.length > 0 ? this.notes[0] : null;

                this.renderNotesList();
                if (this.activeNote) {
                    this.loadNoteIntoEditor(this.activeNote);
                } else {
                    this.createNewNote(true);
                }
                this.refreshPlayerStats();
            }
        } catch (e) {
            console.error('Error deleting note:', e);
        }
    }

    selectNote(note) {
        this.audio.playBlip();
        this.activeNote = note;
        this.loadNoteIntoEditor(note);
        this.renderNotesList();
        if (window.innerWidth < 1024) {
            this.closeMobileSidebar();
        }
    }

    loadNoteIntoEditor(note) {
        if (!note) return;

        if (this.dom.noteTitleInput) {
            this.dom.noteTitleInput.value = note.title;
        }

        if (this.dom.editorArea) {
            this.dom.editorArea.innerHTML = note.content || '';
        }

        const font = note.font_family || 'pixel';
        const size = note.font_size || '16px';

        if (this.dom.fontFamilySelect) this.dom.fontFamilySelect.value = font;
        if (this.dom.fontSizeSelect) this.dom.fontSizeSelect.value = size;

        this.applyFontFamily(font);
        this.applyFontSize(size);
        this.updateWordAndCharMetrics();
        this.updatePinButtonUI();

        if (this.dom.chunkCountBadge) {
            const count = note.chunks ? note.chunks.length : (note.chunks_count || 1);
            this.dom.chunkCountBadge.textContent = `📦 ${count} Chunks`;
        }

        if (this.dom.saveStatusText) {
            this.dom.saveStatusText.innerHTML = '<span class="text-blue-500">● Synchronized</span>';
        }
    }

    updatePinButtonUI() {
        if (!this.dom.pinNoteBtn || !this.activeNote) return;
        if (this.activeNote.is_pinned) {
            this.dom.pinNoteBtn.innerHTML = '📌 <span class="hidden sm:inline">PINNED</span>';
            this.dom.pinNoteBtn.classList.add('bg-amber-400', 'text-black');
            this.dom.pinNoteBtn.classList.remove('bg-blue-600', 'text-white');
        } else {
            this.dom.pinNoteBtn.innerHTML = '📍 <span class="hidden sm:inline">PIN</span>';
            this.dom.pinNoteBtn.classList.remove('bg-amber-400', 'text-black');
            this.dom.pinNoteBtn.classList.add('bg-blue-600', 'text-white');
        }
    }

    /* ---------------- RENDER NOTES LIST ---------------- */
    renderNotesList() {
        if (!this.dom.notesList) return;

        let filtered = this.notes;

        if (this.activeCategory !== 'all') {
            filtered = filtered.filter(n => n.category === this.activeCategory);
        }

        if (this.searchQuery) {
            filtered = filtered.filter(n =>
                n.title.toLowerCase().includes(this.searchQuery) ||
                (n.content && n.content.toLowerCase().includes(this.searchQuery))
            );
        }

        // Sort: pinned first, then newest updated
        filtered.sort((a, b) => {
            if (a.is_pinned && !b.is_pinned) return -1;
            if (!a.is_pinned && b.is_pinned) return 1;
            return new Date(b.updated_at || 0) - new Date(a.updated_at || 0);
        });

        if (filtered.length === 0) {
            this.dom.notesList.innerHTML = `
                <div class="p-4 text-center text-xs font-mono text-blue-400/80 border-2 border-dashed border-blue-400/30 rounded-lg">
                    [ NO SCROLLS FOUND ]<br>
                    <span class="text-[10px] opacity-75">Spawn a new scroll above</span>
                </div>
            `;
            return;
        }

        this.dom.notesList.innerHTML = filtered.map(note => {
            const isActive = this.activeNote && this.activeNote.id === note.id;
            const categoryBadge = this.getCategoryBadge(note.category);
            const chunkCount = note.chunks ? note.chunks.length : (note.chunks_count || 1);

            return `
                <div data-note-id="${note.id}"
                     class="note-list-card cursor-pointer p-3 rounded-lg border-2 transition-all mb-2 select-none
                            ${isActive
                                ? 'bg-blue-600 text-white border-black dark:border-cyan-400 shadow-[3px_3px_0px_#000]'
                                : 'bg-surface hover:bg-blue-100/50 dark:hover:bg-blue-900/30 border-blue-300 dark:border-blue-900/60 shadow-[2px_2px_0px_rgba(0,0,0,0.2)]'
                            }">
                    <div class="flex items-center justify-between gap-1 mb-1">
                        <div class="flex items-center gap-1.5 overflow-hidden">
                            ${note.is_pinned ? '<span title="Pinned Scroll" class="text-amber-400 text-xs">📌</span>' : ''}
                            <h4 class="font-bold text-sm truncate ${isActive ? 'text-white' : 'text-main'}">
                                ${this.escapeHtml(note.title)}
                            </h4>
                        </div>
                        <span class="text-[10px] font-mono shrink-0 px-1.5 py-0.5 rounded border border-black/40 ${categoryBadge.classes}">
                            ${categoryBadge.label}
                        </span>
                    </div>

                    <div class="flex items-center justify-between text-[11px] opacity-80 font-mono mt-2 pt-1 border-t border-black/10 dark:border-white/10">
                        <span>📦 ${chunkCount} chunks</span>
                        <span>Lv.${note.level || 1} • ${note.word_count || 0}w</span>
                    </div>
                </div>
            `;
        }).join('');

        // Bind clicks to rendered note cards
        this.dom.notesList.querySelectorAll('.note-list-card').forEach(card => {
            card.addEventListener('click', () => {
                const id = parseInt(card.dataset.noteId, 10);
                const note = this.notes.find(n => n.id === id);
                if (note) {
                    this.selectNote(note);
                }
            });
        });
    }

    updateActiveNoteInList() {
        if (!this.activeNote) return;
        const card = this.dom.notesList?.querySelector(`[data-note-id="${this.activeNote.id}"] h4`);
        if (card) {
            card.textContent = this.activeNote.title;
        }
    }

    getCategoryBadge(category) {
        switch (category) {
            case 'quest':
                return { label: '⚔️ QUEST', classes: 'bg-amber-500/30 text-amber-900 dark:text-amber-300' };
            case 'spellbook':
                return { label: '🔮 SPELL', classes: 'bg-purple-500/30 text-purple-900 dark:text-purple-300' };
            case 'strategy':
                return { label: '🛡️ RAID', classes: 'bg-rose-500/30 text-rose-900 dark:text-rose-300' };
            case 'lore':
                return { label: '📜 LORE', classes: 'bg-blue-500/30 text-blue-900 dark:text-blue-300' };
            default:
                return { label: '📝 NOTE', classes: 'bg-gray-500/30 text-gray-900 dark:text-gray-300' };
        }
    }

    /* ---------------- CHUNK INSPECTOR MODAL ---------------- */
    async openChunkInspector() {
        if (!this.activeNote) return;
        this.audio.playCoin();

        if (this.dom.chunkModal) {
            this.dom.chunkModal.classList.remove('hidden');
        }

        if (this.dom.chunkModalContent) {
            this.dom.chunkModalContent.innerHTML = `
                <div class="p-8 text-center font-mono text-sm text-blue-400">
                    <span class="animate-spin inline-block mr-2">⚙️</span> Inspecting database memory matrix...
                </div>
            `;
        }

        try {
            const response = await fetch(`/api/notes/${this.activeNote.id}/chunks`);
            const data = await response.json();

            if (response.ok && data.status === 'success') {
                if (this.dom.chunkModalCount) {
                    this.dom.chunkModalCount.textContent = `${data.total_chunks} Total Chunks`;
                }
                if (this.dom.chunkModalBytes) {
                    this.dom.chunkModalBytes.textContent = `${data.total_bytes} Bytes Stored`;
                }

                if (data.chunks.length === 0) {
                    this.dom.chunkModalContent.innerHTML = `
                        <div class="p-6 text-center font-mono text-sm opacity-70">
                            No chunks found. Save note to generate database identity shards.
                        </div>
                    `;
                    return;
                }

                this.dom.chunkModalContent.innerHTML = data.chunks.map(chunk => `
                    <div class="p-3 mb-3 border-2 border-blue-400 dark:border-blue-700 bg-blue-50 dark:bg-slate-900/90 rounded-lg font-mono text-xs shadow-[2px_2px_0px_#000]">
                        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-blue-200 dark:border-blue-800 pb-2 mb-2">
                            <div class="flex items-center gap-2">
                                <span class="bg-blue-600 text-white font-bold px-1.5 py-0.5 rounded text-[10px]">#${chunk.chunk_index}</span>
                                <span class="text-cyan-600 dark:text-cyan-400 font-bold">${this.escapeHtml(chunk.chunk_uid)}</span>
                            </div>
                            <div class="flex items-center gap-2 text-[11px] opacity-75">
                                <span class="bg-slate-200 dark:bg-slate-800 px-1 rounded uppercase">${chunk.chunk_type}</span>
                                <span>${chunk.byte_size} bytes</span>
                            </div>
                        </div>
                        <div class="text-[11px] text-gray-500 dark:text-gray-400 truncate mb-1">
                            SHA-256: <span class="font-mono">${chunk.checksum || 'N/A'}</span>
                        </div>
                        <div class="bg-white dark:bg-black/50 p-2 rounded border border-gray-300 dark:border-gray-800 max-h-24 overflow-y-auto text-main text-[11px] whitespace-pre-wrap">
                            ${this.escapeHtml(chunk.content)}
                        </div>
                    </div>
                `).join('');
            }
        } catch (e) {
            if (this.dom.chunkModalContent) {
                this.dom.chunkModalContent.innerHTML = `
                    <div class="p-4 text-center text-red-500 font-mono">
                        Error fetching chunk telemetry.
                    </div>
                `;
            }
        }
    }

    closeChunkInspector() {
        if (this.dom.chunkModal) {
            this.dom.chunkModal.classList.add('hidden');
        }
    }

    /* ---------------- PLAYER STATS REFRESH ---------------- */
    async refreshPlayerStats() {
        try {
            const response = await fetch('/api/player-stats');
            const data = await response.json();

            if (response.ok && data.status === 'success') {
                const s = data.data;
                if (this.dom.playerLevelBadge) this.dom.playerLevelBadge.textContent = `LV.${s.level}`;
                if (this.dom.playerRankTitle) this.dom.playerRankTitle.textContent = `${s.rank_badge} ${s.rank_title}`;
                if (this.dom.playerXpText) this.dom.playerXpText.textContent = `${s.total_xp} XP`;
                if (this.dom.playerXpBar) this.dom.playerXpBar.style.width = `${s.progress_percent}%`;
                if (this.dom.playerManaText) this.dom.playerManaText.textContent = s.mana;
            }
        } catch (e) {
            // Background telemetry
        }
    }

    escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.notenux = new NotenuxPanel();
});
