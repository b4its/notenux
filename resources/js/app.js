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

            const headerCols = lines[0].split('|').map(c => c.trim()).slice(1, -1);
            if (!lines[1].includes('---')) return tableBlock;

            let tableHtml = '<table class="retro-table"><thead><tr>';
            headerCols.forEach(col => {
                tableHtml += `<th>${col}</th>`;
            });
            tableHtml += '</tr></thead><tbody>';

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

        // Paragraphs
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

    static toMarkdown(html) {
        if (!html) return '';
        let md = html;
        md = md.replace(/<h1>(.*?)<\/h1>/gi, '# $1\n\n');
        md = md.replace(/<h2>(.*?)<\/h2>/gi, '## $1\n\n');
        md = md.replace(/<h3>(.*?)<\/h3>/gi, '### $1\n\n');
        md = md.replace(/<p>(.*?)<\/p>/gi, '$1\n\n');
        md = md.replace(/<strong>(.*?)<\/strong>/gi, '**$1**');
        md = md.replace(/<b>(.*?)<\/b>/gi, '**$1**');
        md = md.replace(/<em>(.*?)<\/em>/gi, '*$1*');
        md = md.replace(/<i>(.*?)<\/i>/gi, '*$1*');
        md = md.replace(/<del>(.*?)<\/del>/gi, '~~$1~~');
        md = md.replace(/<blockquote>(.*?)<\/blockquote>/gi, '> $1\n\n');
        md = md.replace(/<pre><code>([\s\S]*?)<\/code><\/pre>/gi, '```\n$1\n```\n\n');
        md = md.replace(/<code>(.*?)<\/code>/gi, '`$1`');
        md = md.replace(/<li>(.*?)<\/li>/gi, '- $1\n');
        md = md.replace(/<ul[^>]*>/gi, '');
        md = md.replace(/<\/ul>/gi, '\n');
        md = md.replace(/<hr[^>]*>/gi, '---\n\n');
        md = md.replace(/<br[^>]*>/gi, '\n');
        return md.replace(/<[^>]+>/g, '').trim();
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
        this.wordGoal = parseInt(localStorage.getItem('notenux_word_goal') || '250', 10);
        this.saveTimeout = null;
        this.xpGainedSession = 0;
        this.currentZoom = 100;
        this.isRawMarkdownMode = false;
        this.goalCelebrated = false;

        this.dom = {
            primaryNavSidebar: document.getElementById('primary-nav-sidebar'),
            notesExplorerSidebar: document.getElementById('notes-explorer-sidebar'),
            sidebarOverlay: document.getElementById('sidebar-overlay'),

            primarySidebarToggleBtn: document.getElementById('primary-sidebar-toggle-btn'),
            explorerToggleBtn: document.getElementById('explorer-toggle-btn'),
            mobileNavToggleBtn: document.getElementById('mobile-nav-toggle-btn'),
            mobileExplorerToggleBtn: document.getElementById('mobile-explorer-toggle-btn'),
            closeMobileNavBtn: document.getElementById('close-mobile-nav-btn'),
            closeMobileExplorerBtn: document.getElementById('close-mobile-explorer-btn'),

            themeToggleBtn: document.getElementById('theme-toggle-btn'),
            soundToggleBtn: document.getElementById('sound-toggle-btn'),
            inspectChunksBtn: document.getElementById('inspect-chunks-btn'),

            notesList: document.getElementById('notes-list'),
            searchInput: document.getElementById('search-notes-input'),
            sortNotesSelect: document.getElementById('sort-notes-select'),
            newNoteBtn: document.getElementById('new-note-btn'),
            categoryPills: document.querySelectorAll('.category-filter-btn'),
            navCategoryLinks: document.querySelectorAll('.nav-category-link'),

            noteTitleInput: document.getElementById('note-title-input'),
            noteCategorySelect: document.getElementById('note-category-select'),
            editorArea: document.getElementById('editor-content'),
            markdownRawEditor: document.getElementById('markdown-raw-editor'),
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

            zoomInBtn: document.getElementById('zoom-in-btn'),
            zoomOutBtn: document.getElementById('zoom-out-btn'),
            zoomResetBtn: document.getElementById('zoom-reset-btn'),
            zoomLevelDisplay: document.getElementById('zoom-level-display'),

            insertTableBtn: document.getElementById('insert-table-btn'),
            insertTaskBtn: document.getElementById('insert-task-btn'),
            insertMediaBtn: document.getElementById('insert-media-btn'),
            markdownPasteBtn: document.getElementById('markdown-paste-btn'),
            markdownViewToggleBtn: document.getElementById('markdown-view-toggle-btn'),

            questGoalText: document.getElementById('quest-goal-text'),
            questGoalSelect: document.getElementById('quest-goal-select'),

            playerLevelBadge: document.getElementById('player-level-badge'),
            playerRankTitle: document.getElementById('player-rank-title'),
            playerXpText: document.getElementById('player-xp-text'),
            playerXpBar: document.getElementById('player-xp-bar'),
            playerManaText: document.getElementById('player-mana-text'),
            wordCountBadge: document.getElementById('word-count-badge'),
            charCountBadge: document.getElementById('char-count-badge'),
            readingTimeBadge: document.getElementById('reading-time-badge'),
            chunkCountBadge: document.getElementById('chunk-count-badge'),

            chunkModal: document.getElementById('chunk-inspector-modal'),
            chunkModalClose: document.getElementById('chunk-modal-close'),
            chunkModalContent: document.getElementById('chunk-modal-content'),
            chunkModalCount: document.getElementById('chunk-modal-count'),
            chunkModalBytes: document.getElementById('chunk-modal-bytes'),
            verifyChunksBtn: document.getElementById('verify-chunks-btn'),
            chunkVerifyStatus: document.getElementById('chunk-verify-status'),

            crtToggleBtn: document.getElementById('crt-toggle-btn'),
            shortcutsModalBtn: document.getElementById('shortcuts-modal-btn'),
            shortcutsModal: document.getElementById('shortcuts-modal'),
            closeShortcutsModalBtn: document.getElementById('close-shortcuts-modal-btn'),

            importNoteBtn: document.getElementById('import-note-btn'),
            importModal: document.getElementById('import-modal'),
            closeImportModalBtn: document.getElementById('close-import-modal-btn'),
            cancelImportBtn: document.getElementById('cancel-import-btn'),
            confirmImportBtn: document.getElementById('confirm-import-btn'),
            importFileInput: document.getElementById('import-file-input'),
            importRawContent: document.getElementById('import-raw-content'),
            importTitleInput: document.getElementById('import-title-input'),
            importCategorySelect: document.getElementById('import-category-select'),

            findReplaceToggleBtn: document.getElementById('find-replace-toggle-btn'),
            findReplaceBar: document.getElementById('find-replace-bar'),
            findInput: document.getElementById('find-input'),
            replaceInput: document.getElementById('replace-input'),
            findMatchBadge: document.getElementById('find-match-badge'),
            findPrevBtn: document.getElementById('find-prev-btn'),
            findNextBtn: document.getElementById('find-next-btn'),
            replaceOneBtn: document.getElementById('replace-one-btn'),
            replaceAllBtn: document.getElementById('replace-all-btn'),
            closeFindBarBtn: document.getElementById('close-find-bar-btn'),
        };

        this.init();
    }

    init() {
        this.initTheme();
        this.initCrtMode();
        this.initSidebars();
        this.initSoundToggle();
        this.initZoom();
        this.initEditorToolbar();
        this.initPasteInterceptor();
        this.initGoalTracking();
        this.initFindReplace();
        this.initImportModal();
        this.initShortcutsModal();
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

    /* ---------------- CRT SCANLINES FX ---------------- */
    initCrtMode() {
        const crtStored = localStorage.getItem('notenux_crt') === 'true';
        if (crtStored) {
            document.body.classList.add('crt-mode');
        }

        if (this.dom.crtToggleBtn) {
            this.dom.crtToggleBtn.addEventListener('click', () => {
                this.audio.playWarp();
                const isCrt = document.body.classList.toggle('crt-mode');
                localStorage.setItem('notenux_crt', isCrt ? 'true' : 'false');
                this.toast.show(
                    isCrt ? 'CRT Scanlines & Arcade Flicker ON' : 'CRT Scanlines OFF',
                    'info',
                    'Display Matrix',
                    'fa-solid fa-tv text-purple-400'
                );
            });
        }
    }

    /* ---------------- SHORTCUTS GUIDE MODAL ---------------- */
    initShortcutsModal() {
        if (this.dom.shortcutsModalBtn && this.dom.shortcutsModal) {
            this.dom.shortcutsModalBtn.addEventListener('click', () => {
                this.audio.playClick();
                this.dom.shortcutsModal.classList.toggle('hidden');
            });
        }
        if (this.dom.closeShortcutsModalBtn && this.dom.shortcutsModal) {
            this.dom.closeShortcutsModalBtn.addEventListener('click', () => {
                this.audio.playClick();
                this.dom.shortcutsModal.classList.add('hidden');
            });
        }
    }

    /* ---------------- IMPORT MARKDOWN / FILE MODAL ---------------- */
    initImportModal() {
        if (!this.dom.importModal) return;

        if (this.dom.importNoteBtn) {
            this.dom.importNoteBtn.addEventListener('click', () => {
                this.audio.playClick();
                this.dom.importModal.classList.remove('hidden');
                if (this.dom.importRawContent) this.dom.importRawContent.focus();
            });
        }

        const closeModal = () => {
            this.audio.playClick();
            this.dom.importModal.classList.add('hidden');
        };

        if (this.dom.closeImportModalBtn) this.dom.closeImportModalBtn.addEventListener('click', closeModal);
        if (this.dom.cancelImportBtn) this.dom.cancelImportBtn.addEventListener('click', closeModal);

        if (this.dom.confirmImportBtn) {
            this.dom.confirmImportBtn.addEventListener('click', async () => {
                const file = this.dom.importFileInput?.files?.[0];
                const rawContent = this.dom.importRawContent?.value || '';
                const title = this.dom.importTitleInput?.value || '';
                const category = this.dom.importCategorySelect?.value || 'quest';

                if (!file && !rawContent.trim()) {
                    this.toast.show('Please upload a file or paste Markdown content.', 'warning');
                    return;
                }

                this.audio.playCoin();
                this.dom.confirmImportBtn.disabled = true;
                this.dom.confirmImportBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> IMPORTING...';

                try {
                    let response;
                    if (file) {
                        const formData = new FormData();
                        formData.append('file', file);
                        if (title) formData.append('title', title);
                        formData.append('category', category);

                        response = await fetch('/api/notes/import', {
                            method: 'POST',
                            headers: { 'Accept': 'application/json' },
                            body: formData,
                        });
                    } else {
                        response = await fetch('/api/notes/import', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                content: rawContent,
                                title: title,
                                category: category,
                            }),
                        });
                    }

                    const data = await response.json();
                    if (response.ok && data.status === 'success') {
                        const importedNote = data.data;
                        this.audio.playLevelUp();
                        this.toast.show(`Imported scroll "${importedNote.title}" and generated ${importedNote.chunks?.length || 1} shards!`, 'success', 'Import Success', 'fa-solid fa-file-import text-emerald-400');
                        this.dom.importModal.classList.add('hidden');
                        if (this.dom.importRawContent) this.dom.importRawContent.value = '';
                        if (this.dom.importFileInput) this.dom.importFileInput.value = '';

                        this.notes.unshift(importedNote);
                        this.renderNotesList();
                        this.selectNote(importedNote);
                        this.refreshPlayerStats();
                    } else {
                        throw new Error(data.message || 'Import failed');
                    }
                } catch (e) {
                    this.toast.show('Failed to import scroll: ' + e.message, 'danger');
                } finally {
                    this.dom.confirmImportBtn.disabled = false;
                    this.dom.confirmImportBtn.innerHTML = '<i class="fa-solid fa-bolt mr-1"></i> IMPORT & CHUNK';
                }
            });
        }
    }

    /* ---------------- IN-CANVAS FIND & REPLACE ---------------- */
    initFindReplace() {
        if (!this.dom.findReplaceBar) return;

        this.findMatches = [];
        this.currentMatchIndex = -1;

        if (this.dom.findReplaceToggleBtn) {
            this.dom.findReplaceToggleBtn.addEventListener('click', () => {
                this.audio.playClick();
                this.toggleFindBar();
            });
        }

        if (this.dom.closeFindBarBtn) {
            this.dom.closeFindBarBtn.addEventListener('click', () => {
                this.audio.playClick();
                this.toggleFindBar(false);
            });
        }

        if (this.dom.findInput) {
            this.dom.findInput.addEventListener('input', () => {
                this.performFind();
            });
            this.dom.findInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    if (e.shiftKey) {
                        this.findNext(-1);
                    } else {
                        this.findNext(1);
                    }
                }
            });
        }

        if (this.dom.findNextBtn) {
            this.dom.findNextBtn.addEventListener('click', () => this.findNext(1));
        }

        if (this.dom.findPrevBtn) {
            this.dom.findPrevBtn.addEventListener('click', () => this.findNext(-1));
        }

        if (this.dom.replaceOneBtn) {
            this.dom.replaceOneBtn.addEventListener('click', () => this.replaceCurrentMatch());
        }

        if (this.dom.replaceAllBtn) {
            this.dom.replaceAllBtn.addEventListener('click', () => this.replaceAllMatches());
        }
    }

    toggleFindBar(forceState = null) {
        if (!this.dom.findReplaceBar) return;
        const isHidden = this.dom.findReplaceBar.classList.contains('hidden');
        const show = forceState !== null ? forceState : isHidden;

        if (show) {
            this.dom.findReplaceBar.classList.remove('hidden');
            this.dom.findInput?.focus();
            this.dom.findInput?.select();
            this.performFind();
        } else {
            this.dom.findReplaceBar.classList.add('hidden');
            this.clearFindHighlights();
        }
    }

    clearFindHighlights() {
        if (!this.dom.editorArea) return;
        const marks = this.dom.editorArea.querySelectorAll('mark.search-match');
        marks.forEach(m => {
            const parent = m.parentNode;
            while (m.firstChild) {
                parent.insertBefore(m.firstChild, m);
            }
            parent.removeChild(m);
        });
        this.findMatches = [];
        this.currentMatchIndex = -1;
        if (this.dom.findMatchBadge) this.dom.findMatchBadge.textContent = '0 matches';
    }

    performFind() {
        this.clearFindHighlights();
        const query = this.dom.findInput?.value || '';
        if (!query.trim() || !this.dom.editorArea) {
            return;
        }

        const walker = document.createTreeWalker(this.dom.editorArea, NodeFilter.SHOW_TEXT, null, false);
        const textNodes = [];
        let node;
        while ((node = walker.nextNode())) {
            textNodes.push(node);
        }

        const lowerQuery = query.toLowerCase();

        for (const textNode of textNodes) {
            let str = textNode.nodeValue;
            let lowerStr = str.toLowerCase();
            let idx = lowerStr.indexOf(lowerQuery);

            if (idx !== -1) {
                const parent = textNode.parentNode;
                const frag = document.createDocumentFragment();
                let lastIdx = 0;

                while (idx !== -1) {
                    if (idx > lastIdx) {
                        frag.appendChild(document.createTextNode(str.substring(lastIdx, idx)));
                    }
                    const mark = document.createElement('mark');
                    mark.className = 'search-match';
                    mark.textContent = str.substring(idx, idx + query.length);
                    frag.appendChild(mark);

                    lastIdx = idx + query.length;
                    idx = lowerStr.indexOf(lowerQuery, lastIdx);
                }

                if (lastIdx < str.length) {
                    frag.appendChild(document.createTextNode(str.substring(lastIdx)));
                }

                parent.replaceChild(frag, textNode);
            }
        }

        this.findMatches = Array.from(this.dom.editorArea.querySelectorAll('mark.search-match'));
        if (this.dom.findMatchBadge) {
            this.dom.findMatchBadge.textContent = `${this.findMatches.length} matches`;
        }

        if (this.findMatches.length > 0) {
            this.currentMatchIndex = 0;
            this.highlightCurrentMatch();
        }
    }

    highlightCurrentMatch() {
        this.findMatches.forEach(m => m.classList.remove('search-match-active'));
        if (this.currentMatchIndex >= 0 && this.currentMatchIndex < this.findMatches.length) {
            const active = this.findMatches[this.currentMatchIndex];
            active.classList.add('search-match-active');
            active.scrollIntoView({ behavior: 'smooth', block: 'center' });
            if (this.dom.findMatchBadge) {
                this.dom.findMatchBadge.textContent = `${this.currentMatchIndex + 1} of ${this.findMatches.length}`;
            }
        }
    }

    findNext(direction = 1) {
        if (this.findMatches.length === 0) return;
        this.audio.playClick();
        this.currentMatchIndex = (this.currentMatchIndex + direction + this.findMatches.length) % this.findMatches.length;
        this.highlightCurrentMatch();
    }

    replaceCurrentMatch() {
        if (this.findMatches.length === 0 || this.currentMatchIndex < 0) return;
        this.audio.playCoin();
        const replacement = this.dom.replaceInput?.value || '';
        const match = this.findMatches[this.currentMatchIndex];
        if (match) {
            const textNode = document.createTextNode(replacement);
            match.parentNode.replaceChild(textNode, match);
            this.handleEditorInput();
            this.performFind();
            this.toast.show('Replaced match', 'info');
        }
    }

    replaceAllMatches() {
        if (this.findMatches.length === 0) return;
        this.audio.playLevelUp();
        const replacement = this.dom.replaceInput?.value || '';
        const count = this.findMatches.length;
        this.findMatches.forEach(match => {
            const textNode = document.createTextNode(replacement);
            match.parentNode.replaceChild(textNode, match);
        });
        this.handleEditorInput();
        this.performFind();
        this.toast.show(`Replaced all ${count} occurrences!`, 'success', 'Batch Replace');
    }

    /* ---------------- DUAL SIDEBAR INTERACTION ---------------- */
    initSidebars() {
        const primaryCollapsed = localStorage.getItem('notenux_primary_collapsed') === 'true';
        const explorerCollapsed = localStorage.getItem('notenux_explorer_collapsed') === 'true';

        if (primaryCollapsed && window.innerWidth >= 1024) {
            this.dom.primaryNavSidebar?.classList.add('collapsed');
        }
        if (explorerCollapsed && window.innerWidth >= 1024) {
            this.dom.notesExplorerSidebar?.classList.add('collapsed');
        }

        this.dom.primarySidebarToggleBtn?.addEventListener('click', () => {
            this.audio.playBlip();
            this.dom.primaryNavSidebar?.classList.toggle('collapsed');
            const isColl = this.dom.primaryNavSidebar?.classList.contains('collapsed');
            localStorage.setItem('notenux_primary_collapsed', isColl ? 'true' : 'false');
            this.toast.show(isColl ? 'Main navigation collapsed' : 'Main navigation expanded', 'info');
        });

        this.dom.explorerToggleBtn?.addEventListener('click', () => {
            this.audio.playBlip();
            this.dom.notesExplorerSidebar?.classList.toggle('collapsed');
            const isColl = this.dom.notesExplorerSidebar?.classList.contains('collapsed');
            localStorage.setItem('notenux_explorer_collapsed', isColl ? 'true' : 'false');
            this.toast.show(isColl ? 'Scroll Explorer collapsed' : 'Scroll Explorer expanded', 'info');
        });

        this.dom.mobileNavToggleBtn?.addEventListener('click', () => {
            this.audio.playBlip();
            this.dom.primaryNavSidebar?.classList.toggle('-translate-x-full');
            this.dom.notesExplorerSidebar?.classList.add('-translate-x-full');
            this.updateMobileOverlay();
        });

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

    /* ---------------- QUEST GOAL TRACKING ---------------- */
    initGoalTracking() {
        if (this.dom.questGoalSelect) {
            this.dom.questGoalSelect.value = String(this.wordGoal);
            this.dom.questGoalSelect.addEventListener('change', (e) => {
                this.wordGoal = parseInt(e.target.value, 10);
                localStorage.setItem('notenux_word_goal', String(this.wordGoal));
                this.goalCelebrated = false;
                this.updateWordAndCharMetrics();
                this.toast.show(`Daily Quest Target set to ${this.wordGoal} words`, 'info', 'Quest Goal');
            });
        }
    }

    /* ---------------- KEYBOARD SHORTCUTS ---------------- */
    bindKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            const isInputTarget = ['INPUT', 'TEXTAREA'].includes(e.target.tagName) || e.target.isContentEditable;

            // Save: Ctrl + S
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
                e.preventDefault();
                this.saveActiveNote(true);
                return;
            }

            // New Scroll: Ctrl + N
            if ((e.ctrlKey || e.metaKey) && !e.altKey && e.key.toLowerCase() === 'n') {
                e.preventDefault();
                this.createNewNote(true);
                return;
            }

            // Find & Replace: Ctrl + F
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'f') {
                e.preventDefault();
                this.toggleFindBar();
                return;
            }

            // Duplicate Note: Ctrl + D
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'd') {
                e.preventDefault();
                this.duplicateActiveNote();
                return;
            }

            // Toggle RAW Markdown View: Ctrl + M
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'm') {
                e.preventDefault();
                this.toggleMarkdownRawView();
                return;
            }

            // Toggle Export Menu: Ctrl + E
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'e') {
                e.preventDefault();
                this.dom.exportMenu?.classList.toggle('hidden');
                return;
            }

            // Zen Mode: Alt + Z
            if (e.altKey && e.key.toLowerCase() === 'z') {
                e.preventDefault();
                this.toggleZenMode();
                return;
            }

            // Chunk Inspector: Ctrl + Alt + C
            if ((e.ctrlKey || e.metaKey) && e.altKey && e.key.toLowerCase() === 'c') {
                e.preventDefault();
                this.openChunkInspector();
                return;
            }

            // Verify Integrity: Ctrl + Alt + V
            if ((e.ctrlKey || e.metaKey) && e.altKey && e.key.toLowerCase() === 'v') {
                e.preventDefault();
                this.verifyChunksIntegrity();
                return;
            }

            // Shortcuts Guide: ? or F1 (when not typing in an input)
            if ((e.key === '?' && !isInputTarget) || e.key === 'F1') {
                e.preventDefault();
                this.dom.shortcutsModal?.classList.toggle('hidden');
                return;
            }

            // Escape: Close all modals & bars
            if (e.key === 'Escape') {
                this.closeChunkInspector();
                this.toggleFindBar(false);
                if (this.dom.importModal) this.dom.importModal.classList.add('hidden');
                if (this.dom.shortcutsModal) this.dom.shortcutsModal.classList.add('hidden');
                if (this.dom.exportMenu) this.dom.exportMenu.classList.add('hidden');
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
            if (this.dom.editorArea && (!this.activeNote.content || this.activeNote.content === '')) {
                this.activeNote.content = this.dom.editorArea.innerHTML;
            }
            this.renderNotesList();
            this.loadNoteIntoEditor(this.activeNote);
        } else {
            this.createNewNote(false);
        }
    }

    /* ---------------- EVENTS ---------------- */
    bindEvents() {
        this.dom.searchInput?.addEventListener('input', (e) => {
            this.searchQuery = e.target.value.toLowerCase().trim();
            this.renderNotesList();
        });

        this.dom.sortNotesSelect?.addEventListener('change', (e) => {
            this.currentSort = e.target.value;
            this.renderNotesList();
            this.toast.show(`Scrolls sorted by ${e.target.options[e.target.selectedIndex].text}`, 'info', 'Sorting');
        });

        this.dom.categoryPills.forEach(pill => {
            pill.addEventListener('click', () => {
                this.audio.playBlip();
                this.setCategory(pill.dataset.category || 'all');
            });
        });

        this.dom.navCategoryLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                this.audio.playBlip();
                const cat = link.dataset.category || 'all';
                this.setCategory(cat);
                if (window.innerWidth < 1024) {
                    this.dom.primaryNavSidebar?.classList.add('-translate-x-full');
                    this.dom.notesExplorerSidebar?.classList.remove('-translate-x-full');
                    this.updateMobileOverlay();
                }
            });
        });

        this.dom.newNoteBtn?.addEventListener('click', () => {
            this.createNewNote(true);
        });

        this.dom.noteTitleInput?.addEventListener('input', () => {
            if (!this.activeNote) return;
            this.activeNote.title = this.dom.noteTitleInput.value;
            this.updateActiveNoteInList();
            this.scheduleAutoSave();
        });

        this.dom.noteCategorySelect?.addEventListener('change', (e) => {
            if (!this.activeNote) return;
            this.activeNote.category = e.target.value;
            this.updateActiveNoteInList();
            this.scheduleAutoSave();
            this.toast.show(`Scroll moved to category "${e.target.value.toUpperCase()}"`, 'info', 'Category');
        });

        this.dom.editorArea?.addEventListener('input', () => {
            this.handleEditorInput();
        });

        this.dom.markdownRawEditor?.addEventListener('input', () => {
            if (!this.activeNote) return;
            const mdText = this.dom.markdownRawEditor.value;
            const html = MarkdownEngine.parse(mdText);
            this.dom.editorArea.innerHTML = html;
            this.handleEditorInput();
        });

        this.dom.saveNowBtn?.addEventListener('click', () => {
            this.saveActiveNote(true);
        });

        this.dom.pinNoteBtn?.addEventListener('click', () => {
            this.togglePinActiveNote();
        });

        this.dom.favoriteNoteBtn?.addEventListener('click', () => {
            this.toggleFavoriteActiveNote();
        });

        this.dom.duplicateNoteBtn?.addEventListener('click', () => {
            this.duplicateActiveNote();
        });

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
            btn.addEventListener('click', () => {
                const format = btn.dataset.exportFormat || 'md';
                this.exportActiveNote(format);
                this.dom.exportMenu?.classList.add('hidden');
            });
        });

        this.dom.zenModeBtn?.addEventListener('click', () => {
            this.toggleZenMode();
        });

        this.dom.deleteNoteBtn?.addEventListener('click', () => {
            this.deleteActiveNote();
        });

        this.dom.insertTableBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            this.insertRetroTable();
        });

        this.dom.insertTaskBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            this.insertInteractiveTask();
        });

        this.dom.insertMediaBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            this.insertMedia();
        });

        this.dom.markdownPasteBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            this.promptMarkdownInput();
        });

        this.dom.markdownViewToggleBtn?.addEventListener('click', () => {
            this.toggleMarkdownRawView();
        });

        this.dom.inspectChunksBtn?.addEventListener('click', () => {
            this.openChunkInspector();
        });

        this.dom.chunkModalClose?.addEventListener('click', () => {
            this.audio.playBlip();
            this.closeChunkInspector();
        });

        this.dom.verifyChunksBtn?.addEventListener('click', () => {
            this.verifyChunksIntegrity();
        });

        this.dom.fontFamilySelect?.addEventListener('change', (e) => {
            this.audio.playClick();
            this.applyFontFamily(e.target.value);
            this.toast.show(`Font changed to ${e.target.options[e.target.selectedIndex].text}`, 'info', 'Typography');
            this.scheduleAutoSave();
        });

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

    insertMedia() {
        this.audio.playClick();
        const choice = prompt('Enter Image URL (or type "sword", "crystal", "shield" for retro presets):', 'crystal');
        if (!choice) return;

        let src = choice;
        let alt = 'Media Item';

        if (choice === 'crystal') {
            src = 'https://images.unsplash.com/photo-1518709268805-4e9042af9f23?auto=format&fit=crop&w=400&q=80';
            alt = 'Cyan Mana Crystal';
        } else if (choice === 'sword') {
            src = 'https://images.unsplash.com/photo-1595590424283-b8f17842773f?auto=format&fit=crop&w=400&q=80';
            alt = 'Ancient Relic Blade';
        } else if (choice === 'shield') {
            src = 'https://images.unsplash.com/photo-1544716278-ca5e3f4abd8c?auto=format&fit=crop&w=400&q=80';
            alt = 'Guardian Shield';
        }

        const imgHtml = `<p><img src="${src}" alt="${alt}" class="rounded-lg border-2 border-blue-500 max-h-72 shadow-md my-2"></p><p><br></p>`;
        document.execCommand('insertHTML', false, imgHtml);
        this.dom.editorArea.focus();
        this.handleEditorInput();
        this.toast.show('Artifact image injected into scroll', 'success', 'Media Ready');
    }

    toggleMarkdownRawView() {
        this.audio.playBlip();
        this.isRawMarkdownMode = !this.isRawMarkdownMode;

        if (this.isRawMarkdownMode) {
            const md = MarkdownEngine.toMarkdown(this.dom.editorArea.innerHTML);
            this.dom.markdownRawEditor.value = md;
            this.dom.editorArea.classList.add('hidden');
            this.dom.markdownRawEditor.classList.remove('hidden');
            this.dom.markdownViewToggleBtn.innerHTML = '<i class="fa-solid fa-eye mr-1 text-cyan-400"></i> WYSIWYG';
            this.toast.show('Raw Markdown View activated', 'info');
        } else {
            const html = MarkdownEngine.parse(this.dom.markdownRawEditor.value);
            this.dom.editorArea.innerHTML = html;
            this.dom.markdownRawEditor.classList.add('hidden');
            this.dom.editorArea.classList.remove('hidden');
            this.dom.markdownViewToggleBtn.innerHTML = '<i class="fa-brands fa-markdown mr-1 text-cyan-400"></i> RAW MD';
            this.toast.show('WYSIWYG Rich Editor restored', 'info');
            this.handleEditorInput();
        }
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
        this.saveDraftLocal();
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

        // Quest Word Goal progress calculation
        if (this.dom.questGoalText) {
            const percent = Math.min(100, Math.round((words / this.wordGoal) * 100));
            this.dom.questGoalText.textContent = `Goal: ${words}/${this.wordGoal}w (${percent}%)`;

            if (percent >= 100 && !this.goalCelebrated) {
                this.goalCelebrated = true;
                this.audio.playLevelUp();
                this.toast.show(
                    `🎉 Daily Quest Target achieved! (${words}/${this.wordGoal} words written)`,
                    'xp',
                    'Quest Complete',
                    'fa-solid fa-bullseye text-yellow-400'
                );
            }
        }

        if (this.activeNote) {
            this.activeNote.word_count = words;
            this.activeNote.char_count = chars;
        }
    }

    saveDraftLocal() {
        if (!this.activeNote) return;
        try {
            localStorage.setItem(`notenux_draft_${this.activeNote.id}`, JSON.stringify({
                title: this.activeNote.title,
                content: this.activeNote.content,
                timestamp: Date.now(),
            }));
        } catch (e) {
            // LocalStorage full or disabled
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

                // Clear draft backup since persisted
                localStorage.removeItem(`notenux_draft_${this.activeNote.id}`);

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
                    this.activeNote.is_pinned ? 'Scroll pinned to top of Grimoire' : 'Scroll unpinned',
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

    async selectNote(note) {
        this.audio.playBlip();
        this.activeNote = note;
        this.renderNotesList();

        // If content is not yet in memory (due to optimized lightweight list queries), fetch on demand
        if (note.content === undefined || note.content === null) {
            try {
                const response = await fetch(`/api/notes/${note.id}`);
                const data = await response.json();
                if (response.ok && data.data) {
                    this.activeNote = data.data;
                    const idx = this.notes.findIndex(n => n.id === note.id);
                    if (idx !== -1) {
                        this.notes[idx] = data.data;
                    }
                }
            } catch (e) {
                // Fallback
            }
        }

        this.loadNoteIntoEditor(this.activeNote);
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

        if (this.dom.markdownRawEditor) {
            this.dom.markdownRawEditor.value = MarkdownEngine.toMarkdown(note.content || '');
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

        filtered.sort((a, b) => {
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

    /* ---------------- CHUNK INSPECTOR & INTEGRITY AUDITOR ---------------- */
    async openChunkInspector() {
        if (!this.activeNote) return;
        this.audio.playCoin();

        if (this.dom.chunkModal) {
            this.dom.chunkModal.classList.remove('hidden');
        }

        if (this.dom.chunkVerifyStatus) {
            this.dom.chunkVerifyStatus.className = 'hidden';
            this.dom.chunkVerifyStatus.textContent = '';
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

                this.cachedChunks = data.chunks;
                this.renderChunkCards(data.chunks);
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

    renderChunkCards(chunks) {
        if (!this.dom.chunkModalContent) return;

        this.dom.chunkModalContent.innerHTML = chunks.map((chunk, index) => `
            <div data-chunk-id="${chunk.id}" class="chunk-card p-3 mb-2.5 border-2 border-blue-400 dark:border-blue-700 bg-blue-50 dark:bg-slate-900/90 rounded-lg font-mono text-xs shadow-[2px_2px_0px_#000]">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-blue-200 dark:border-blue-800 pb-2 mb-2">
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <span class="bg-blue-600 text-white font-bold px-1.5 py-0.5 rounded text-[10px]">SHARD #${chunk.chunk_index}</span>
                        <code class="text-cyan-600 dark:text-cyan-400 font-bold">${this.escapeHtml(chunk.chunk_uid)}</code>
                        <button onclick="window.notenux.copyChunkUid('${chunk.chunk_uid}')"
                                class="arcade-btn bg-surface px-1.5 py-0.5 text-[9px] rounded hover:bg-cyan-200"
                                title="Copy Chunk Identity Key">
                            <i class="fa-solid fa-copy mr-1"></i> COPY UID
                        </button>
                    </div>
                    <div class="flex items-center gap-2 text-[11px] opacity-75">
                        <span class="bg-slate-200 dark:bg-slate-800 px-1.5 py-0.5 rounded uppercase font-bold text-blue-600 dark:text-cyan-300">${chunk.chunk_type}</span>
                        <span>${chunk.byte_size} bytes</span>
                    </div>
                </div>
                <div class="text-[10px] text-gray-500 dark:text-gray-400 truncate mb-1">
                    SHA-256 Checksum: <span class="font-mono text-cyan-600 dark:text-cyan-400">${chunk.checksum || 'N/A'}</span>
                </div>
                <div id="chunk-view-${chunk.id}" class="bg-white dark:bg-black/50 p-2.5 rounded border border-gray-300 dark:border-gray-800 max-h-28 overflow-y-auto text-main text-[11px] whitespace-pre-wrap leading-relaxed">
                    ${this.escapeHtml(chunk.content)}
                </div>
            </div>
        `).join('');
    }

    async moveChunk(index, direction) {
        if (!this.cachedChunks || this.cachedChunks.length <= 1) return;
        const newIndex = index + direction;
        if (newIndex < 0 || newIndex >= this.cachedChunks.length) return;

        this.audio.playClick();
        const item = this.cachedChunks.splice(index, 1)[0];
        this.cachedChunks.splice(newIndex, 0, item);

        const orderedIds = this.cachedChunks.map(c => c.id);

        try {
            const response = await fetch(`/api/notes/${this.activeNote.id}/chunks/reorder`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ order: orderedIds }),
            });

            const data = await response.json();
            if (response.ok && data.status === 'success') {
                this.toast.show('Shards reordered and scroll reassembled!', 'success');
                this.cachedChunks = data.data.chunks;
                this.renderChunkCards(this.cachedChunks);
                if (data.data.note) {
                    this.activeNote = data.data.note;
                    this.loadNoteIntoEditor(this.activeNote);
                }
            }
        } catch (e) {
            this.toast.show('Failed to reorder shards', 'danger');
        }
    }

    async deleteChunk(chunkId) {
        if (!this.activeNote) return;
        if (!confirm('Are you sure you want to delete this memory shard?')) return;

        this.audio.playDelete();
        try {
            const response = await fetch(`/api/notes/${this.activeNote.id}/chunks/${chunkId}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                },
            });

            const data = await response.json();
            if (response.ok && data.status === 'success') {
                this.toast.show('Shard deleted and parent scroll reassembled!', 'success');
                this.openChunkInspector();
                if (data.data && data.data.note) {
                    this.activeNote = data.data.note;
                    this.loadNoteIntoEditor(this.activeNote);
                }
            } else {
                throw new Error(data.message || 'Error deleting chunk');
            }
        } catch (e) {
            this.toast.show('Failed to delete shard', 'danger');
        }
    }

    toggleChunkEditor(chunkId) {
        this.audio.playClick();
        const viewEl = document.getElementById(`chunk-view-${chunkId}`);
        const editEl = document.getElementById(`chunk-edit-${chunkId}`);
        if (viewEl && editEl) {
            viewEl.classList.toggle('hidden');
            editEl.classList.toggle('hidden');
        }
    }

    async saveIndividualChunk(chunkId) {
        if (!this.activeNote) return;
        const editEl = document.getElementById(`chunk-edit-${chunkId}`);
        const textarea = editEl?.querySelector('textarea');
        if (!textarea) return;

        const newContent = textarea.value;
        this.audio.playCoin();

        try {
            const response = await fetch(`/api/notes/${this.activeNote.id}/chunks/${chunkId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ content: newContent }),
            });

            const data = await response.json();
            if (response.ok && data.status === 'success') {
                this.toast.show('Shard updated & checksum recomputed!', 'success', 'Chunk Saved');
                this.openChunkInspector();
                if (data.data && data.data.note) {
                    this.activeNote = data.data.note;
                    this.loadNoteIntoEditor(this.activeNote);
                }
            } else {
                throw new Error(data.message || 'Error updating chunk');
            }
        } catch (e) {
            this.toast.show('Failed to save individual chunk', 'danger');
        }
    }

    async verifyChunksIntegrity() {
        if (!this.activeNote) return;
        this.audio.playClick();

        if (this.dom.chunkVerifyStatus) {
            this.dom.chunkVerifyStatus.className = 'px-4 py-2 text-xs font-mono bg-blue-100 dark:bg-blue-900/50 text-blue-900 dark:text-cyan-300 border-b border-blue-400';
            this.dom.chunkVerifyStatus.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> Running cryptographic SHA-256 integrity verification...';
        }

        try {
            const response = await fetch(`/api/notes/${this.activeNote.id}/chunks/verify`);
            const data = await response.json();

            if (response.ok && data.status === 'success') {
                const report = data.data;
                if (report.verified) {
                    this.audio.playLevelUp();
                    this.dom.chunkVerifyStatus.className = 'px-4 py-2 text-xs font-mono bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 border-b border-emerald-500 font-bold';
                    this.dom.chunkVerifyStatus.innerHTML = `<i class="fa-solid fa-circle-check mr-1.5"></i> All ${report.total_chunks} shards verified! 100% cryptographic checksum & byte match.`;
                    this.toast.show('Cryptographic verification successful: All shards intact.', 'success', 'Integrity Verified');
                } else {
                    this.audio.playDelete();
                    this.dom.chunkVerifyStatus.className = 'px-4 py-2 text-xs font-mono bg-rose-100 dark:bg-rose-950/60 text-rose-800 dark:text-rose-300 border-b border-rose-500 font-bold';
                    this.dom.chunkVerifyStatus.innerHTML = `<i class="fa-solid fa-triangle-exclamation mr-1.5"></i> Warning: ${report.corrupted_chunks} shard(s) checksum mismatch detected!`;
                    this.toast.show('Integrity warning: Shard discrepancy detected.', 'danger', 'Integrity Warning');
                }
            }
        } catch (e) {
            if (this.dom.chunkVerifyStatus) {
                this.dom.chunkVerifyStatus.className = 'px-4 py-2 text-xs font-mono bg-rose-100 text-rose-800 border-b border-rose-500';
                this.dom.chunkVerifyStatus.textContent = 'Integrity audit service unavailable.';
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
