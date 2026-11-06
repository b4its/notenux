/**
 * Notenux - Retro Gamified Dual-Sidebar Panel & Markdown-Enabled Chunked Editor
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
            if (this.ctx && this.ctx.state === 'suspended') {
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

/**
 * Top-Right Retro Notification Toast System
 */
class RetroToast {
    constructor(audio) {
        this.audio = audio;
        this.container = document.getElementById('toast-container');
        if (!this.container) {
            this.container = document.createElement('div');
            this.container.id = 'toast-container';
            document.body.appendChild(this.container);
        }
    }

    show(message, type = 'info', title = null, iconClass = null) {
        const toast = document.createElement('div');
        toast.className = 'arcade-toast';

        let defaultIcon = 'fa-solid fa-circle-info text-blue-500';
        let borderColor = 'border-blue-500';

        if (type === 'success') {
            defaultIcon = 'fa-solid fa-circle-check text-emerald-500';
            borderColor = 'border-emerald-500';
            this.audio?.playCoin();
        } else if (type === 'danger') {
            defaultIcon = 'fa-solid fa-circle-xmark text-rose-500';
            borderColor = 'border-rose-500';
            this.audio?.playDelete();
        } else if (type === 'warning') {
            defaultIcon = 'fa-solid fa-triangle-exclamation text-amber-500';
            borderColor = 'border-amber-500';
            this.audio?.playBlip();
        } else if (type === 'xp') {
            defaultIcon = 'fa-solid fa-coins text-yellow-400';
            borderColor = 'border-yellow-400';
            this.audio?.playCoin();
        } else {
            this.audio?.playBlip();
        }

        toast.classList.add(borderColor);

        const iconHtml = `<i class="${iconClass || defaultIcon} text-base shrink-0 mt-0.5"></i>`;
        const titleHtml = title ? `<div class="font-bold text-xs uppercase tracking-wider mb-0.5">${title}</div>` : '';

        toast.innerHTML = `
            ${iconHtml}
            <div class="flex-1 min-w-0 pr-2">
                ${titleHtml}
                <div class="text-xs break-words">${message}</div>
            </div>
            <button class="toast-close-btn opacity-60 hover:opacity-100 text-xs shrink-0 cursor-pointer">
                <i class="fa-solid fa-xmark"></i>
            </button>
            <div class="arcade-toast-progress"></div>
        `;

        this.container.appendChild(toast);

        // Slide in
        requestAnimationFrame(() => {
            toast.classList.add('show');
        });

        const progressEl = toast.querySelector('.arcade-toast-progress');
        const duration = 3500;
        const startTime = Date.now();

        const updateProgress = () => {
            const elapsed = Date.now() - startTime;
            const remaining = Math.max(0, 1 - (elapsed / duration));
            if (progressEl) {
                progressEl.style.transform = `scaleX(${remaining})`;
            }
            if (elapsed < duration) {
                requestAnimationFrame(updateProgress);
            }
        };
        requestAnimationFrame(updateProgress);

        const dismiss = () => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 250);
        };

        toast.querySelector('.toast-close-btn').addEventListener('click', dismiss);
        const timer = setTimeout(dismiss, duration);

        toast.addEventListener('mouseenter', () => clearTimeout(timer));
    }
}

/**
 * Built-in Robust Markdown Parser for fast client-side markdown conversion
 */
class MarkdownEngine {
    static parse(md) {
        if (!md) return '';

        // If marked.js is available on window, use it
        if (typeof window.marked !== 'undefined' && typeof window.marked.parse === 'function') {
            return window.marked.parse(md);
        }

        let html = md;

        // Code blocks ```lang ... ```
        html = html.replace(/```([a-z0-9_-]*)\n([\s\S]*?)```/gim, (match, lang, code) => {
            return `<pre><code class="language-${lang}">${MarkdownEngine.escape(code.trim())}</code></pre>`;
        });

        // Inline code `code`
        html = html.replace(/`([^`]+)`/gim, '<code>$1</code>');

        // Markdown Tables
        html = html.replace(/((?:\|[^\n]+\|\r?\n)+)/g, (tableBlock) => {
            const lines = tableBlock.trim().split('\n').map(l => l.trim()).filter(Boolean);
            if (lines.length < 2) return tableBlock;

            // Header line
            const headerCols = lines[0].split('|').map(c => c.trim()).slice(1, -1);
            // Check separator
            if (!lines[1].includes('---')) return tableBlock;

            let tableHtml = '<table class="retro-table"><thead><tr>';
            headerCols.forEach(col => {
                tableHtml += `<th>${col}</th>`;
            });
            tableHtml += '</tr></thead><tbody>';

            // Data rows
            for (let i = 2; i < lines.length; i++) {
                const rowCols = lines[i].split('|').map(c => c.trim()).slice(1, -1);
                tableHtml += '<tr>';
                rowCols.forEach(col => {
                    tableHtml += `<td>${col}</td>`;
                });
                tableHtml += '</tr>';
            }

            tableHtml += '</tbody></table>';
            return tableHtml;
        });

        // Headers
        html = html.replace(/^### (.*$)/gim, '<h3>$1</h3>');
        html = html.replace(/^## (.*$)/gim, '<h2>$1</h2>');
        html = html.replace(/^# (.*$)/gim, '<h1>$1</h1>');

        // Blockquotes
        html = html.replace(/^\> (.*$)/gim, '<blockquote>$1</blockquote>');

        // Task / Checklist items
        html = html.replace(/^\s*[-*]\s+\[ \]\s+(.*)$/gim, '<ul class="task-list"><li><input type="checkbox"> $1</li></ul>');
        html = html.replace(/^\s*[-*]\s+\[[xX]\]\s+(.*)$/gim, '<ul class="task-list"><li><input type="checkbox" checked> $1</li></ul>');
        html = html.replace(/<\/ul>\s*<ul class="task-list">/gim, '');

        // Bold & Italic
        html = html.replace(/\*\*\*(.*?)\*\*\*/gim, '<strong><em>$1</em></strong>');
        html = html.replace(/\*\*(.*?)\*\*/gim, '<strong>$1</strong>');
        html = html.replace(/\*(.*?)\*/gim, '<em>$1</em>');
        html = html.replace(/~~(.*?)~~/gim, '<del>$1</del>');

        // Horizontal Rule
        html = html.replace(/^---$/gim, '<hr>');

        // Unordered lists
        html = html.replace(/^\s*[-*+]\s+(.*)$/gim, '<ul><li>$1</li></ul>');
        html = html.replace(/<\/ul>\s*<ul>/gim, '');

        // Ordered lists
        html = html.replace(/^\s*\d+\.\s+(.*)$/gim, '<ol><li>$1</li></ol>');
        html = html.replace(/<\/ol>\s*<ol>/gim, '');

        // Paragraphs for remaining text blocks
        const blocks = html.split(/\n{2,}/);
        html = blocks.map(b => {
            const trimmed = b.trim();
            if (!trimmed) return '';
            if (/^<(h[1-6]|ul|ol|pre|table|blockquote|hr)/i.test(trimmed)) {
                return trimmed;
            }
            return `<p>${trimmed.replace(/\n/g, '<br>')}</p>`;
        }).join('');

        return html;
    }

    static escape(str) {
        return str
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }
}

class NotenuxPanel {
    constructor() {
        this.audio = new RetroAudio();
        this.toast = new RetroToast(this.audio);
        this.notes = [];
        this.activeNote = null;
        this.activeCategory = 'all';
        this.searchQuery = '';
        this.currentSort = 'updated_desc';
        this.saveTimeout = null;
        this.xpGainedSession = 0;
        this.currentZoom = 100;

        this.dom = {
            // Dual Sidebars
            primaryNavSidebar: document.getElementById('primary-nav-sidebar'),
            notesExplorerSidebar: document.getElementById('notes-explorer-sidebar'),
            sidebarOverlay: document.getElementById('sidebar-overlay'),

            // Toggles
            primarySidebarToggleBtn: document.getElementById('primary-sidebar-toggle-btn'),
            explorerToggleBtn: document.getElementById('explorer-toggle-btn'),
            mobileNavToggleBtn: document.getElementById('mobile-nav-toggle-btn'),
            mobileExplorerToggleBtn: document.getElementById('mobile-explorer-toggle-btn'),
            closeMobileNavBtn: document.getElementById('close-mobile-nav-btn'),
            closeMobileExplorerBtn: document.getElementById('close-mobile-explorer-btn'),

            // Global controls
            themeToggleBtn: document.getElementById('theme-toggle-btn'),
            soundToggleBtn: document.getElementById('sound-toggle-btn'),
            inspectChunksBtn: document.getElementById('inspect-chunks-btn'),

            // Explorer & List
            notesList: document.getElementById('notes-list'),
            searchInput: document.getElementById('search-notes-input'),
            sortNotesSelect: document.getElementById('sort-notes-select'),
            newNoteBtn: document.getElementById('new-note-btn'),
            categoryPills: document.querySelectorAll('.category-filter-btn'),
            navCategoryLinks: document.querySelectorAll('.nav-category-link'),

            // Editor elements
            noteTitleInput: document.getElementById('note-title-input'),
            noteCategorySelect: document.getElementById('note-category-select'),
            editorArea: document.getElementById('editor-content'),
            editorZoomWrapper: document.getElementById('editor-zoom-wrapper'),
            fontFamilySelect: document.getElementById('font-family-select'),
            fontSizeSelect: document.getElementById('font-size-select'),
            saveStatusText: document.getElementById('save-status-text'),
            saveNowBtn: document.getElementById('save-now-btn'),
            pinNoteBtn: document.getElementById('pin-note-btn'),
            favoriteNoteBtn: document.getElementById('favorite-note-btn'),
            duplicateNoteBtn: document.getElementById('duplicate-note-btn'),
            exportNoteBtn: document.getElementById('export-note-btn'),
            exportMenu: document.getElementById('export-menu'),
            zenModeBtn: document.getElementById('zen-mode-btn'),
            deleteNoteBtn: document.getElementById('delete-note-btn'),

            // Zoom controls
            zoomInBtn: document.getElementById('zoom-in-btn'),
            zoomOutBtn: document.getElementById('zoom-out-btn'),
            zoomResetBtn: document.getElementById('zoom-reset-btn'),
            zoomLevelDisplay: document.getElementById('zoom-level-display'),

            // Table, Task & Markdown tools
            insertTableBtn: document.getElementById('insert-table-btn'),
            insertTaskBtn: document.getElementById('insert-task-btn'),
            markdownPasteBtn: document.getElementById('markdown-paste-btn'),

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
        this.initSidebars();
        this.initSoundToggle();
        this.initZoom();
        this.initEditorToolbar();
        this.initPasteInterceptor();
        this.bindEvents();
        this.bindKeyboardShortcuts();
        this.loadNotesFromDOM();
        this.refreshPlayerStats();
    }

    /* ---------------- THEME & DISPLAY ---------------- */
    initTheme() {
        const storedTheme = localStorage.getItem('notenux_theme');
        const systemDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        const isDark = storedTheme ? storedTheme === 'dark' : systemDark;

        this.setTheme(isDark ? 'dark' : 'light', false);

        if (this.dom.themeToggleBtn) {
            this.dom.themeToggleBtn.addEventListener('click', () => {
                this.audio.playWarp();
                const currentTheme = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
                const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
                this.setTheme(newTheme, true);
            });
        }
    }

    setTheme(theme, notify = false) {
        if (theme === 'dark') {
            document.documentElement.classList.add('dark');
            document.documentElement.setAttribute('data-theme', 'dark');
            if (this.dom.themeToggleBtn) {
                this.dom.themeToggleBtn.innerHTML = '<i class="fa-solid fa-moon text-cyan-300 mr-1.5"></i> <span class="hidden sm:inline">NIGHT</span>';
            }
        } else {
            document.documentElement.classList.remove('dark');
            document.documentElement.setAttribute('data-theme', 'light');
            if (this.dom.themeToggleBtn) {
                this.dom.themeToggleBtn.innerHTML = '<i class="fa-solid fa-sun text-amber-500 mr-1.5"></i> <span class="hidden sm:inline">DAY</span>';
            }
        }
        localStorage.setItem('notenux_theme', theme);

        if (notify) {
            this.toast.show(
                theme === 'dark' ? 'Retro Night Dark Theme engaged' : 'Arcade Day Light Theme engaged',
                'info',
                'Theme Switched',
                theme === 'dark' ? 'fa-solid fa-moon text-cyan-400' : 'fa-solid fa-sun text-amber-400'
            );
        }
    }

    /* ---------------- DUAL SIDEBAR INTERACTION ---------------- */
    initSidebars() {
        // Restore desktop collapse states
        const primaryCollapsed = localStorage.getItem('notenux_primary_collapsed') === 'true';
        const explorerCollapsed = localStorage.getItem('notenux_explorer_collapsed') === 'true';

        if (primaryCollapsed && window.innerWidth >= 1024) {
            this.dom.primaryNavSidebar?.classList.add('collapsed');
        }
        if (explorerCollapsed && window.innerWidth >= 1024) {
            this.dom.notesExplorerSidebar?.classList.add('collapsed');
        }

        // Primary Sidebar Desktop Toggle
        this.dom.primarySidebarToggleBtn?.addEventListener('click', () => {
            this.audio.playBlip();
            this.dom.primaryNavSidebar?.classList.toggle('collapsed');
            const isColl = this.dom.primaryNavSidebar?.classList.contains('collapsed');
            localStorage.setItem('notenux_primary_collapsed', isColl ? 'true' : 'false');
            this.toast.show(isColl ? 'Main navigation collapsed' : 'Main navigation expanded', 'info');
        });

        // Notes Explorer Desktop Toggle
        this.dom.explorerToggleBtn?.addEventListener('click', () => {
            this.audio.playBlip();
            this.dom.notesExplorerSidebar?.classList.toggle('collapsed');
            const isColl = this.dom.notesExplorerSidebar?.classList.contains('collapsed');
            localStorage.setItem('notenux_explorer_collapsed', isColl ? 'true' : 'false');
            this.toast.show(isColl ? 'Scroll Explorer collapsed' : 'Scroll Explorer expanded', 'info');
        });

        // Mobile Nav Drawer Toggle
        this.dom.mobileNavToggleBtn?.addEventListener('click', () => {
            this.audio.playBlip();
            this.dom.primaryNavSidebar?.classList.toggle('-translate-x-full');
            this.dom.notesExplorerSidebar?.classList.add('-translate-x-full');
            this.updateMobileOverlay();
        });

        // Mobile Explorer Drawer Toggle
        this.dom.mobileExplorerToggleBtn?.addEventListener('click', () => {
            this.audio.playBlip();
            this.dom.notesExplorerSidebar?.classList.toggle('-translate-x-full');
            this.dom.primaryNavSidebar?.classList.add('-translate-x-full');
            this.updateMobileOverlay();
        });

        this.dom.closeMobileNavBtn?.addEventListener('click', () => {
            this.dom.primaryNavSidebar?.classList.add('-translate-x-full');
            this.updateMobileOverlay();
        });

        this.dom.closeMobileExplorerBtn?.addEventListener('click', () => {
            this.dom.notesExplorerSidebar?.classList.add('-translate-x-full');
            this.updateMobileOverlay();
        });

        this.dom.sidebarOverlay?.addEventListener('click', () => {
            this.dom.primaryNavSidebar?.classList.add('-translate-x-full');
            this.dom.notesExplorerSidebar?.classList.add('-translate-x-full');
            this.updateMobileOverlay();
        });
    }

    updateMobileOverlay() {
        const anyOpen =
            !this.dom.primaryNavSidebar?.classList.contains('-translate-x-full') ||
            !this.dom.notesExplorerSidebar?.classList.contains('-translate-x-full');

        if (anyOpen) {
            this.dom.sidebarOverlay?.classList.remove('hidden');
        } else {
            this.dom.sidebarOverlay?.classList.add('hidden');
        }
    }

    closeMobileDrawers() {
        this.dom.primaryNavSidebar?.classList.add('-translate-x-full');
        this.dom.notesExplorerSidebar?.classList.add('-translate-x-full');
        this.dom.sidebarOverlay?.classList.add('hidden');
    }

    /* ---------------- ZOOM CONTROLS ---------------- */
    initZoom() {
        const updateZoomUI = () => {
            if (this.dom.editorZoomWrapper) {
                this.dom.editorZoomWrapper.style.transform = `scale(${this.currentZoom / 100})`;
                this.dom.editorZoomWrapper.style.width = `${10000 / this.currentZoom}%`;
            }
            if (this.dom.zoomLevelDisplay) {
                this.dom.zoomLevelDisplay.textContent = `${this.currentZoom}%`;
            }
        };

        this.dom.zoomInBtn?.addEventListener('click', () => {
            if (this.currentZoom < 200) {
                this.currentZoom += 15;
                updateZoomUI();
                this.toast.show(`Content Zoom: ${this.currentZoom}%`, 'info', null, 'fa-solid fa-magnifying-glass-plus text-cyan-400');
            }
        });

        this.dom.zoomOutBtn?.addEventListener('click', () => {
            if (this.currentZoom > 60) {
                this.currentZoom -= 15;
                updateZoomUI();
                this.toast.show(`Content Zoom: ${this.currentZoom}%`, 'info', null, 'fa-solid fa-magnifying-glass-minus text-blue-400');
            }
        });

        this.dom.zoomResetBtn?.addEventListener('click', () => {
            this.currentZoom = 100;
            updateZoomUI();
            this.toast.show('Content Zoom reset to 100%', 'info', null, 'fa-solid fa-arrows-rotate text-emerald-400');
        });
    }

    /* ---------------- SOUND TOGGLE ---------------- */
    initSoundToggle() {
        const updateSoundLabel = () => {
            if (this.dom.soundToggleBtn) {
                this.dom.soundToggleBtn.innerHTML = this.audio.enabled
                    ? '<i class="fa-solid fa-volume-high text-cyan-400 mr-1.5"></i> <span class="hidden sm:inline">SFX: ON</span>'
                    : '<i class="fa-solid fa-volume-xmark text-gray-400 mr-1.5"></i> <span class="hidden sm:inline">SFX: OFF</span>';
            }
        };

        updateSoundLabel();

        this.dom.soundToggleBtn?.addEventListener('click', () => {
            const state = this.audio.toggle();
            updateSoundLabel();
            this.toast.show(
                state ? '8-Bit Audio Synthesizer Enabled' : 'Audio Muted',
                state ? 'success' : 'warning',
                null,
                state ? 'fa-solid fa-volume-high text-cyan-400' : 'fa-solid fa-volume-xmark text-gray-400'
            );
        });
    }

    /* ---------------- KEYBOARD SHORTCUTS ---------------- */
    bindKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Ctrl+S or Cmd+S -> Save/Inscribe
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                this.saveActiveNote(true);
            }
            // Escape -> Close Modals or Zen mode
            if (e.key === 'Escape') {
                this.closeChunkInspector();
                if (document.body.classList.contains('zen-mode')) {
                    this.toggleZenMode();
                }
            }
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

        // Sort notes select
        this.dom.sortNotesSelect?.addEventListener('change', (e) => {
            this.currentSort = e.target.value;
            this.renderNotesList();
            this.toast.show(`Scrolls sorted by ${e.target.options[e.target.selectedIndex].text}`, 'info', 'Sorting');
        });

        // Category filter in explorer
        this.dom.categoryPills.forEach(pill => {
            pill.addEventListener('click', () => {
                this.audio.playBlip();
                this.setCategory(pill.dataset.category || 'all');
            });
        });

        // Category links in primary navigation sidebar
        this.dom.navCategoryLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                this.audio.playBlip();
                const cat = link.dataset.category || 'all';
                this.setCategory(cat);
                // ensure explorer is open on mobile
                if (window.innerWidth < 1024) {
                    this.dom.primaryNavSidebar?.classList.add('-translate-x-full');
                    this.dom.notesExplorerSidebar?.classList.remove('-translate-x-full');
                    this.updateMobileOverlay();
                }
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

        // Note category select
        this.dom.noteCategorySelect?.addEventListener('change', (e) => {
            if (!this.activeNote) return;
            this.activeNote.category = e.target.value;
            this.updateActiveNoteInList();
            this.scheduleAutoSave();
            this.toast.show(`Scroll moved to category "${e.target.value.toUpperCase()}"`, 'info', 'Category');
        });

        // Editor input
        this.dom.editorArea?.addEventListener('input', () => {
            this.handleEditorInput();
        });

        // Save Now Button
        this.dom.saveNowBtn?.addEventListener('click', () => {
            this.saveActiveNote(true);
        });

        // Pin Toggle
        this.dom.pinNoteBtn?.addEventListener('click', () => {
            this.togglePinActiveNote();
        });

        // Favorite Toggle
        this.dom.favoriteNoteBtn?.addEventListener('click', () => {
            this.toggleFavoriteActiveNote();
        });

        // Duplicate Note
        this.dom.duplicateNoteBtn?.addEventListener('click', () => {
            this.duplicateActiveNote();
        });

        // Export Dropdown
        this.dom.exportNoteBtn?.addEventListener('click', (e) => {
            e.stopPropagation();
            this.dom.exportMenu?.classList.toggle('hidden');
        });

        document.addEventListener('click', (e) => {
            if (!this.dom.exportMenu?.contains(e.target) && e.target !== this.dom.exportNoteBtn) {
                this.dom.exportMenu?.classList.add('hidden');
            }
        });

        this.dom.exportMenu?.querySelectorAll('[data-export-format]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const format = btn.dataset.exportFormat || 'md';
                this.exportActiveNote(format);
                this.dom.exportMenu?.classList.add('hidden');
            });
        });

        // Zen Mode Toggle
        this.dom.zenModeBtn?.addEventListener('click', () => {
            this.toggleZenMode();
        });

        // Delete Note
        this.dom.deleteNoteBtn?.addEventListener('click', () => {
            this.deleteActiveNote();
        });

        // Table Insertion
        this.dom.insertTableBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            this.insertRetroTable();
        });

        // Task / Checklist Insertion
        this.dom.insertTaskBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            this.insertInteractiveTask();
        });

        // Markdown Paste / Import modal button
        this.dom.markdownPasteBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            this.promptMarkdownInput();
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
            this.toast.show(`Font changed to ${e.target.options[e.target.selectedIndex].text}`, 'info', 'Typography');
            this.scheduleAutoSave();
        });

        // Font size change
        this.dom.fontSizeSelect?.addEventListener('change', (e) => {
            this.audio.playClick();
            this.applyFontSize(e.target.value);
            this.toast.show(`Font size set to ${e.target.value}`, 'info', 'Typography');
            this.scheduleAutoSave();
        });
    }

    setCategory(cat) {
        this.activeCategory = cat;

        this.dom.categoryPills.forEach(p => {
            if (p.dataset.category === cat) {
                p.classList.add('active-category', 'bg-blue-600', 'text-white');
            } else {
                p.classList.remove('active-category', 'bg-blue-600', 'text-white');
            }
        });

        this.dom.navCategoryLinks.forEach(l => {
            if (l.dataset.category === cat) {
                l.classList.add('bg-blue-600', 'text-white');
            } else {
                l.classList.remove('bg-blue-600', 'text-white');
            }
        });

        this.renderNotesList();
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
                    const url = prompt('Enter link URL (https://...):');
                    if (url) {
                        document.execCommand(command, false, url);
                        this.toast.show('Hyperlink inserted', 'info');
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

    /* ---------------- CLIPBOARD PASTE INTERCEPTOR (.MD & TABLES) ---------------- */
    initPasteInterceptor() {
        if (!this.dom.editorArea) return;

        this.dom.editorArea.addEventListener('paste', (e) => {
            const clipboardData = e.clipboardData || window.clipboardData;
            if (!clipboardData) return;

            const textPlain = clipboardData.getData('text/plain') || '';
            const textHtml = clipboardData.getData('text/html') || '';

            // Check if plain text looks like Markdown or contains a Markdown table
            const isMarkdown =
                /(^|\n)#{1,6}\s+/m.test(textPlain) ||
                /\|[^\n]+\|\r?\n\|[-:\s|]+\|/m.test(textPlain) ||
                /```[\s\S]*?```/m.test(textPlain) ||
                /(^|\n)>\s+/m.test(textPlain) ||
                /(\*\*|__)[^\*]+(\*\*|__)/m.test(textPlain) ||
                /(^|\n)[-*+]\s+/m.test(textPlain);

            if (isMarkdown) {
                e.preventDefault();
                const convertedHtml = MarkdownEngine.parse(textPlain);
                document.execCommand('insertHTML', false, convertedHtml);
                this.handleEditorInput();
                this.toast.show(
                    'Pasted Markdown (.md) formatted into rich elements & tables!',
                    'success',
                    'Markdown Parsed',
                    'fa-solid fa-file-code text-cyan-400'
                );
                return;
            }

            // If pasting rich HTML that contains table, sanitize & ensure retro table class
            if (textHtml && textHtml.includes('<table')) {
                e.preventDefault();
                const parser = new DOMParser();
                const doc = parser.parseFromString(textHtml, 'text/html');
                doc.querySelectorAll('table').forEach(tbl => {
                    tbl.classList.add('retro-table');
                });
                document.execCommand('insertHTML', false, doc.body.innerHTML);
                this.handleEditorInput();
                this.toast.show('Pasted HTML Table formatted cleanly!', 'info', 'Table Injected', 'fa-solid fa-table text-cyan-400');
                return;
            }
        });
    }

    insertRetroTable() {
        this.audio.playCoin();
        const tableHtml = `
            <table class="retro-table">
                <thead>
                    <tr>
                        <th>Phase / Item</th>
                        <th>Class / Type</th>
                        <th>Status</th>
                        <th>EXP</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Arcane Citadel</td>
                        <td>Dungeon Raid</td>
                        <td>Active</td>
                        <td>+350 XP</td>
                    </tr>
                    <tr>
                        <td>Sapphire Glyph</td>
                        <td>Artifact</td>
                        <td>Equipped</td>
                        <td>+120 XP</td>
                    </tr>
                </tbody>
            </table>
            <p><br></p>
        `;
        document.execCommand('insertHTML', false, tableHtml);
        this.dom.editorArea.focus();
        this.handleEditorInput();
        this.toast.show('3x4 Retro Data Table inserted into editor', 'success', 'Table Added', 'fa-solid fa-table text-cyan-400');
    }

    insertInteractiveTask() {
        this.audio.playClick();
        const taskHtml = `
            <ul class="task-list my-2">
                <li class="flex items-center gap-2">
                    <input type="checkbox" class="w-4 h-4 rounded text-blue-600 focus:ring-blue-500">
                    <span>New Quest Objective</span>
                </li>
            </ul>
            <p><br></p>
        `;
        document.execCommand('insertHTML', false, taskHtml);
        this.dom.editorArea.focus();
        this.handleEditorInput();
        this.toast.show('Interactive quest task item added', 'info', 'Checklist');
    }

    promptMarkdownInput() {
        const mdText = prompt('Paste your raw Markdown (.md) here to convert into rich document:');
        if (mdText) {
            const html = MarkdownEngine.parse(mdText);
            document.execCommand('insertHTML', false, html);
            this.handleEditorInput();
            this.toast.show('Markdown (.md) converted and inscribed!', 'success', 'Markdown Inscribed', 'fa-solid fa-file-code text-cyan-400');
        }
    }

    toggleZenMode() {
        this.audio.playWarp();
        document.body.classList.toggle('zen-mode');
        const isZen = document.body.classList.contains('zen-mode');
        this.toast.show(
            isZen ? 'Distraction-Free Zen Writing Mode engaged. Press Zen button or ESC to exit.' : 'Exited Zen Writing Mode.',
            'info',
            'Studio Layout',
            'fa-solid fa-expand text-purple-400'
        );
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

        if (this.dom.wordCountBadge) this.dom.wordCountBadge.innerHTML = `<i class="fa-solid fa-pen-nib mr-1 text-blue-500"></i> ${words} Words`;
        if (this.dom.charCountBadge) this.dom.charCountBadge.innerHTML = `<i class="fa-solid fa-font mr-1 text-blue-500"></i> ${chars} Chars`;
        if (this.dom.readingTimeBadge) this.dom.readingTimeBadge.innerHTML = `<i class="fa-solid fa-clock mr-1 text-cyan-500"></i> ${minutes}m Read`;

        if (this.activeNote) {
            this.activeNote.word_count = words;
            this.activeNote.char_count = chars;
        }
    }

    triggerXpBurst() {
        this.xpGainedSession += 1;
        if (this.xpGainedSession % 10 === 0) {
            this.audio.playBlip();
            this.spawnXpPopup('+3 XP');
        }
    }

    spawnXpPopup(text) {
        const popup = document.createElement('div');
        popup.className = 'xp-burst fixed z-50 pointer-events-none font-bold font-mono text-xs text-yellow-400 bg-black/85 px-2.5 py-1 rounded border-2 border-yellow-400 shadow-md flex items-center gap-1.5';
        popup.innerHTML = `<i class="fa-solid fa-bolt text-yellow-400"></i> ${text}`;

        const rect = this.dom.editorArea.getBoundingClientRect();
        popup.style.left = `${Math.min(window.innerWidth - 120, rect.right - 100)}px`;
        popup.style.top = `${rect.top + 30}px`;

        document.body.appendChild(popup);
        setTimeout(() => popup.remove(), 1200);
    }

    scheduleAutoSave() {
        if (this.dom.saveStatusText) {
            this.dom.saveStatusText.innerHTML = '<span class="text-amber-500 animate-pulse"><i class="fa-solid fa-spinner fa-spin mr-1"></i> Inscribing...</span>';
        }

        clearTimeout(this.saveTimeout);
        this.saveTimeout = setTimeout(() => {
            this.saveActiveNote(false);
        }, 900);
    }

    /* ---------------- CRUD & API SYNC ---------------- */
    async saveActiveNote(manual = false) {
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
                is_favorite: this.activeNote.is_favorite || false,
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
                    this.dom.saveStatusText.innerHTML = '<span class="text-emerald-500"><i class="fa-solid fa-check mr-1"></i> Synchronized</span>';
                }

                this.renderNotesList();
                this.refreshPlayerStats();

                if (manual) {
                    this.toast.show(
                        `Scroll "${this.activeNote.title}" inscribed into database chunks!`,
                        'success',
                        'Saved to Matrix',
                        'fa-solid fa-floppy-disk text-emerald-400'
                    );
                }
            } else {
                throw new Error(data.message || 'Failed to save');
            }
        } catch (e) {
            if (this.dom.saveStatusText) {
                this.dom.saveStatusText.innerHTML = '<span class="text-red-500"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Storage error</span>';
            }
            if (manual) {
                this.toast.show('Failed to synchronize scroll with server database.', 'danger', 'Storage Error');
            }
        }
    }

    async createNewNote(selectImmediately = true) {
        this.audio.playLevelUp();
        try {
            const count = this.notes.length + 1;
            const payload = {
                title: `Quest Scroll #${count}: Arcane Expedition`,
                category: this.activeCategory !== 'all' ? this.activeCategory : 'quest',
                content: `<h2>Chapter ${count}</h2><p>Inscribe your thoughts, strategic lore, or arcane spells here...</p>`,
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

                this.toast.show(
                    `Spawned new scroll "${newNote.title}" (+25 XP awarded)`,
                    'xp',
                    'New Scroll Ready',
                    'fa-solid fa-scroll text-yellow-400'
                );
            }
        } catch (e) {
            this.toast.show('Could not spawn new scroll.', 'danger');
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

                this.toast.show(
                    this.activeNote.is_pinned ? 'Scroll pinned to the top of the Grimoire' : 'Scroll unpinned',
                    'info',
                    'Pin Status',
                    'fa-solid fa-thumbtack text-amber-400'
                );
            }
        } catch (e) {
            this.toast.show('Error updating pin status', 'danger');
        }
    }

    async toggleFavoriteActiveNote() {
        if (!this.activeNote) return;
        this.audio.playClick();

        try {
            const response = await fetch(`/api/notes/${this.activeNote.id}/favorite`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
            });

            const data = await response.json();
            if (response.ok) {
                this.activeNote.is_favorite = data.is_favorite;
                this.updateFavoriteButtonUI();
                this.renderNotesList();

                this.toast.show(
                    this.activeNote.is_favorite ? 'Scroll saved to bookmarks!' : 'Scroll removed from favorites',
                    'info',
                    'Favorite',
                    'fa-solid fa-star text-amber-400'
                );
            }
        } catch (e) {
            this.toast.show('Error updating favorite status', 'danger');
        }
    }

    async duplicateActiveNote() {
        if (!this.activeNote) return;
        this.audio.playCoin();

        try {
            const response = await fetch(`/api/notes/${this.activeNote.id}/duplicate`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
            });

            const data = await response.json();
            if (response.ok && data.status === 'success') {
                const clone = data.data;
                this.notes.unshift(clone);
                this.renderNotesList();
                this.selectNote(clone);
                this.refreshPlayerStats();

                this.toast.show(
                    `Scroll duplicated as "${clone.title}"`,
                    'success',
                    'Scroll Cloned',
                    'fa-solid fa-copy text-cyan-400'
                );
            }
        } catch (e) {
            this.toast.show('Failed to duplicate scroll', 'danger');
        }
    }

    exportActiveNote(format) {
        if (!this.activeNote) return;
        this.audio.playCoin();
        window.open(`/api/notes/${this.activeNote.id}/export/${format}`, '_blank');
        this.toast.show(`Exporting scroll as .${format.toUpperCase()}`, 'info', 'Export Started', 'fa-solid fa-download text-blue-400');
    }

    async deleteActiveNote() {
        if (!this.activeNote) return;
        if (!confirm(`Are you sure you want to banish scroll "${this.activeNote.title}" to the digital void?`)) {
            return;
        }

        const title = this.activeNote.title;
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

                this.toast.show(
                    `Scroll "${title}" banished into the void.`,
                    'danger',
                    'Scroll Banished',
                    'fa-solid fa-trash-can text-rose-400'
                );
            }
        } catch (e) {
            this.toast.show('Failed to delete scroll', 'danger');
        }
    }

    selectNote(note) {
        this.audio.playBlip();
        this.activeNote = note;
        this.loadNoteIntoEditor(note);
        this.renderNotesList();
        if (window.innerWidth < 1024) {
            this.closeMobileDrawers();
        }
    }

    loadNoteIntoEditor(note) {
        if (!note) return;

        if (this.dom.noteTitleInput) {
            this.dom.noteTitleInput.value = note.title;
        }

        if (this.dom.noteCategorySelect) {
            this.dom.noteCategorySelect.value = note.category || 'quest';
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
        this.updateFavoriteButtonUI();

        if (this.dom.chunkCountBadge) {
            const count = note.chunks ? note.chunks.length : (note.chunks_count || 1);
            this.dom.chunkCountBadge.innerHTML = `<i class="fa-solid fa-cubes mr-1 text-cyan-400"></i> ${count} Chunks`;
        }

        if (this.dom.saveStatusText) {
            this.dom.saveStatusText.innerHTML = '<span class="text-blue-500"><i class="fa-solid fa-circle-check mr-1"></i> Synchronized</span>';
        }
    }

    updatePinButtonUI() {
        if (!this.dom.pinNoteBtn || !this.activeNote) return;
        if (this.activeNote.is_pinned) {
            this.dom.pinNoteBtn.innerHTML = '<i class="fa-solid fa-thumbtack text-black mr-1.5"></i> <span class="hidden sm:inline">PINNED</span>';
            this.dom.pinNoteBtn.classList.add('bg-amber-400', 'text-black');
            this.dom.pinNoteBtn.classList.remove('bg-blue-600', 'text-white');
        } else {
            this.dom.pinNoteBtn.innerHTML = '<i class="fa-solid fa-thumbtack text-white mr-1.5"></i> <span class="hidden sm:inline">PIN</span>';
            this.dom.pinNoteBtn.classList.remove('bg-amber-400', 'text-black');
            this.dom.pinNoteBtn.classList.add('bg-blue-600', 'text-white');
        }
    }

    updateFavoriteButtonUI() {
        if (!this.dom.favoriteNoteBtn || !this.activeNote) return;
        if (this.activeNote.is_favorite) {
            this.dom.favoriteNoteBtn.innerHTML = '<i class="fa-solid fa-star text-amber-400"></i>';
            this.dom.favoriteNoteBtn.title = 'Remove Favorite';
        } else {
            this.dom.favoriteNoteBtn.innerHTML = '<i class="fa-regular fa-star text-amber-400"></i>';
            this.dom.favoriteNoteBtn.title = 'Add to Favorites';
        }
    }

    /* ---------------- RENDER NOTES LIST ---------------- */
    renderNotesList() {
        if (!this.dom.notesList) return;

        let filtered = [...this.notes];

        if (this.activeCategory !== 'all') {
            filtered = filtered.filter(n => n.category === this.activeCategory);
        }

        if (this.searchQuery) {
            filtered = filtered.filter(n =>
                n.title.toLowerCase().includes(this.searchQuery) ||
                (n.content && n.content.toLowerCase().includes(this.searchQuery))
            );
        }

        // Apply sorting
        filtered.sort((a, b) => {
            // Pinned notes always on top first
            if (a.is_pinned && !b.is_pinned) return -1;
            if (!a.is_pinned && b.is_pinned) return 1;

            switch (this.currentSort) {
                case 'title_asc':
                    return a.title.localeCompare(b.title);
                case 'word_count_desc':
                    return (b.word_count || 0) - (a.word_count || 0);
                case 'level_desc':
                    return (b.level || 1) - (a.level || 1);
                case 'updated_asc':
                    return new Date(a.updated_at || 0) - new Date(b.updated_at || 0);
                case 'updated_desc':
                default:
                    return new Date(b.updated_at || 0) - new Date(a.updated_at || 0);
            }
        });

        if (filtered.length === 0) {
            this.dom.notesList.innerHTML = `
                <div class="p-4 text-center text-xs font-mono text-blue-400/80 border-2 border-dashed border-blue-400/30 rounded-lg">
                    <i class="fa-solid fa-scroll text-xl mb-1 block opacity-50"></i>
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
                            ${note.is_pinned ? '<i title="Pinned Scroll" class="fa-solid fa-thumbtack text-amber-400 text-xs shrink-0"></i>' : ''}
                            ${note.is_favorite ? '<i title="Favorite Scroll" class="fa-solid fa-star text-amber-400 text-xs shrink-0"></i>' : ''}
                            <h4 class="font-bold text-sm truncate ${isActive ? 'text-white' : 'text-main'}">
                                ${this.escapeHtml(note.title)}
                            </h4>
                        </div>
                        <span class="text-[10px] font-mono shrink-0 px-1.5 py-0.5 rounded border border-black/40 flex items-center gap-1 ${categoryBadge.classes}">
                            <i class="${categoryBadge.icon}"></i>
                            ${categoryBadge.label}
                        </span>
                    </div>

                    <div class="flex items-center justify-between text-[11px] opacity-80 font-mono mt-2 pt-1 border-t border-black/10 dark:border-white/10">
                        <span><i class="fa-solid fa-cubes text-[10px] mr-1"></i>${chunkCount} chunks</span>
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
                return { label: 'QUEST', icon: 'fa-solid fa-khanda', classes: 'bg-amber-500/30 text-amber-900 dark:text-amber-300' };
            case 'spellbook':
                return { label: 'SPELL', icon: 'fa-solid fa-wand-magic-sparkles', classes: 'bg-purple-500/30 text-purple-900 dark:text-purple-300' };
            case 'strategy':
                return { label: 'RAID', icon: 'fa-solid fa-shield-halved', classes: 'bg-rose-500/30 text-rose-900 dark:text-rose-300' };
            case 'lore':
                return { label: 'LORE', icon: 'fa-solid fa-scroll', classes: 'bg-blue-500/30 text-blue-900 dark:text-blue-300' };
            default:
                return { label: 'NOTE', icon: 'fa-solid fa-book-journal-whills', classes: 'bg-gray-500/30 text-gray-900 dark:text-gray-300' };
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
                    <i class="fa-solid fa-gears fa-spin mr-2"></i> Inspecting database memory matrix...
                </div>
            `;
        }

        try {
            const response = await fetch(`/api/notes/${this.activeNote.id}/chunks`);
            const data = await response.json();

            if (response.ok && data.status === 'success') {
                if (this.dom.chunkModalCount) {
                    this.dom.chunkModalCount.innerHTML = `<i class="fa-solid fa-cubes mr-1 text-cyan-400"></i> ${data.total_chunks} Total Chunks`;
                }
                if (this.dom.chunkModalBytes) {
                    this.dom.chunkModalBytes.innerHTML = `<i class="fa-solid fa-database mr-1 text-blue-400"></i> ${data.total_bytes} Bytes Stored`;
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
                                <button onclick="window.notenux.copyChunkUid('${chunk.chunk_uid}')"
                                        class="arcade-btn bg-surface px-1.5 py-0.5 text-[9px] rounded hover:bg-cyan-200"
                                        title="Copy Chunk UID">
                                    <i class="fa-solid fa-copy"></i>
                                </button>
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

    copyChunkUid(uid) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(uid);
            this.toast.show(`Copied chunk ID ${uid} to clipboard`, 'success', 'Copied', 'fa-solid fa-copy text-cyan-400');
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
                if (this.dom.playerRankTitle) {
                    this.dom.playerRankTitle.innerHTML = `<i class="fa-solid ${s.rank_badge} text-yellow-400 mr-1.5"></i> ${s.rank_title}`;
                }
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
