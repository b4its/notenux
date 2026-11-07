/**
 * Notenux - Retro Gamified Dual-Sidebar Panel & Markdown-Enabled Chunked Editor
 */

const fetch = (url, options = {}) => window.fetch(url, {
    ...options,
    headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
        ...options.headers,
    },
});

function sanitizeHtml(html) {
    const template = document.createElement('template');
    template.innerHTML = html;
    const allowed = new Set('p div span br h1 h2 h3 h4 h5 h6 strong b em i u s del sub sup blockquote pre code ul ol li table thead tbody tfoot tr th td hr a img input font'.split(' '));
    for (const element of template.content.querySelectorAll('*')) {
        if (!allowed.has(element.localName)) {
            element.remove();
            continue;
        }
        for (const attribute of [...element.attributes]) {
            const { name, value } = attribute;
            const safe = ['class', 'title', 'alt'].includes(name)
                || (name === 'type' && element.localName === 'input' && value === 'checkbox')
                || (name === 'checked' && element.localName === 'input')
                || (['colspan', 'rowspan', 'width', 'height', 'size', 'start'].includes(name) && /^\d+$/.test(value))
                || (name === 'color' && /^(#[a-f0-9]{3,8}|[a-z]+)$/i.test(value))
                || (name === 'style' && /^(\s*(color|background-color|text-align|font-size|font-family|font-weight|font-style|text-decoration)\s*:\s*[a-z0-9#(),.%\s'"-]+;?\s*)+$/i.test(value))
                || (name === 'href' && element.localName === 'a' && /^(https?:\/\/|mailto:|#|\/(?!\/))/i.test(value))
                || (name === 'src' && element.localName === 'img' && /^https?:\/\//i.test(value));
            if (!safe) element.removeAttribute(name);
        }
        if (element.localName === 'input' && element.type !== 'checkbox') element.remove();
    }
    return template.innerHTML;
}

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
        const escape = value => { const span = document.createElement('span'); span.textContent = value; return span.innerHTML; };
        const titleHtml = title ? `<div class="font-bold text-xs uppercase tracking-wider mb-0.5">${escape(title)}</div>` : '';

        toast.innerHTML = `
            ${iconHtml}
            <div class="flex-1 min-w-0 pr-2">
                ${titleHtml}
                <div class="text-xs break-words">${escape(message)}</div>
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
            return sanitizeHtml(window.marked.parse(md));
        }

        let html = md;
        const codeTokens = [];
        const protectCode = value => {
            const token = `\uE000${codeTokens.length}\uE001`;
            codeTokens.push(value);
            return token;
        };

        // Code blocks ```lang ... ```
        html = html.replace(/```([a-z0-9_-]*)\n([\s\S]*?)```/gim, (match, lang, code) => {
            return protectCode(`<pre><code class="language-${lang}">${MarkdownEngine.escape(code.replace(/\n$/, ''))}</code></pre>`);
        });

        // Inline code `code`
        html = html.replace(/`([^`]+)`/gim, (_, code) => protectCode(`<code>${MarkdownEngine.escape(code)}</code>`));
        html = html.replace(/!\[([^\]]*)\]\((https?:\/\/[^\s)]+)\)/gi, (_, alt, url) => `<img alt="${MarkdownEngine.escape(alt).replace(/"/g, '&quot;')}" src="${MarkdownEngine.escape(url).replace(/"/g, '&quot;')}">`);
        html = html.replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+|mailto:[^\s)]+|#[^\s)]+)\)/gi, (_, text, url) => `<a href="${MarkdownEngine.escape(url).replace(/"/g, '&quot;')}">${text}</a>`);

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
            if (/^<(h[1-6]|ul|ol|pre|table|blockquote|hr)/i.test(trimmed) || /^\uE000\d+\uE001$/.test(trimmed)) {
                return trimmed;
            }
            return `<p>${trimmed.replace(/\n/g, '<br>')}</p>`;
        }).join('');

        html = html.replace(/\uE000(\d+)\uE001/g, (_, index) => codeTokens[Number(index)] || '');
        return sanitizeHtml(html);
    }

    static toMarkdown(html) {
        if (!html) return '';
        let md = html;

        md = md.replace(/<a\b[^>]*href="([^"]*)"[^>]*>([\s\S]*?)<\/a>/gi, '[$2]($1)');
        md = md.replace(/<img\b[^>]*>/gi, image => {
            const url = image.match(/\bsrc="([^"]*)"/i)?.[1] || '';
            const alt = image.match(/\balt="([^"]*)"/i)?.[1] || '';
            return `![${alt}](${url})`;
        });
        md = md.replace(/<ol\b[^>]*>([\s\S]*?)<\/ol>/gi, (_, list) => {
            let index = 0;
            return list.replace(/<li\b[^>]*>([\s\S]*?)<\/li>/gi, (_, item) => `${++index}. ${item}\n`) + '\n';
        });

        // Table to Markdown
        md = md.replace(/<table[^>]*>([\s\S]*?)<\/table>/gi, (match, tableContent) => {
            const rows = [];
            const trMatches = tableContent.match(/<tr[^>]*>[\s\S]*?<\/tr>/gi) || [];
            trMatches.forEach((trHtml, idx) => {
                const cellMatches = trHtml.match(/<(?:th|td)[^>]*>([\s\S]*?)<\/(?:th|td)>/gi) || [];
                const cells = cellMatches.map(c => c.replace(/<[^>]+>/g, '').trim());
                if (cells.length > 0) {
                    rows.push(`| ${cells.join(' | ')} |`);
                    if (idx === 0) {
                        rows.push(`| ${cells.map(() => '---').join(' | ')} |`);
                    }
                }
            });
            return rows.length > 0 ? ('\n\n' + rows.join('\n') + '\n\n') : '';
        });

        // Task / Checklist items to Markdown
        md = md.replace(/<li[^>]*>\s*<input[^>]*checked[^>]*>\s*([\s\S]*?)<\/li>/gi, '- [x] $1\n');
        md = md.replace(/<li[^>]*>\s*<input[^>]*type="checkbox"[^>]*>\s*([\s\S]*?)<\/li>/gi, '- [ ] $1\n');

        md = md.replace(/<h([1-6])\b[^>]*>([\s\S]*?)<\/h\1>/gi, (_, level, text) => '#'.repeat(Number(level)) + ' ' + text + '\n\n');
        md = md.replace(/<p\b[^>]*>([\s\S]*?)<\/p>/gi, '$1\n\n');
        md = md.replace(/<strong>(.*?)<\/strong>/gi, '**$1**');
        md = md.replace(/<b>(.*?)<\/b>/gi, '**$1**');
        md = md.replace(/<em>(.*?)<\/em>/gi, '*$1*');
        md = md.replace(/<i>(.*?)<\/i>/gi, '*$1*');
        md = md.replace(/<del>(.*?)<\/del>/gi, '~~$1~~');
        md = md.replace(/<blockquote>(.*?)<\/blockquote>/gi, '> $1\n\n');
        md = md.replace(/<pre\b[^>]*>\s*<code\b[^>]*>([\s\S]*?)<\/code>\s*<\/pre>/gi, '```\n$1\n```\n\n');
        md = md.replace(/<code>(.*?)<\/code>/gi, '`$1`');
        md = md.replace(/<li>(.*?)<\/li>/gi, '- $1\n');
        md = md.replace(/<ul[^>]*>/gi, '');
        md = md.replace(/<\/ul>/gi, '\n');
        md = md.replace(/<ol[^>]*>/gi, '');
        md = md.replace(/<\/ol>/gi, '\n');
        md = md.replace(/<hr[^>]*>/gi, '---\n\n');
        md = md.replace(/<br[^>]*>/gi, '\n');
        const decoder = document.createElement('textarea');
        decoder.innerHTML = md.replace(/<[^>]+>/g, '').trim();
        return decoder.value;
    }

    static escape(str) {
        return str
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }
}

class RetroSearchableSelect {
    /**
     * @param {HTMLSelectElement} select
     */
    constructor(select) {
        if (!select || select.dataset.retroSelectInit) return;
        this.select = select;
        this.select.dataset.retroSelectInit = 'true';
        this.isOpen = false;
        this.highlightIndex = -1;
        this.filteredOptions = [];

        this.init();
    }

    init() {
        this.select.classList.add('retro-native-select-hidden');

        this.container = document.createElement('div');
        this.container.className = 'retro-select-container relative inline-block';
        if (this.select.className.includes('w-full')) {
            this.container.classList.add('w-full');
        }

        this.select.parentNode.insertBefore(this.container, this.select);
        this.container.appendChild(this.select);

        this.trigger = document.createElement('button');
        this.trigger.type = 'button';
        this.trigger.className = 'retro-select-trigger arcade-btn';
        if (this.select.className.includes('w-full')) {
            this.trigger.classList.add('w-full');
        }
        this.trigger.innerHTML = `
            <span class="retro-select-label truncate"></span>
            <i class="fa-solid fa-chevron-down text-[9px] opacity-70 ml-1.5 transition-transform duration-150"></i>
        `;
        this.labelEl = this.trigger.querySelector('.retro-select-label');
        this.chevronEl = this.trigger.querySelector('.fa-chevron-down');
        this.container.appendChild(this.trigger);

        this.dropdown = document.createElement('div');
        this.dropdown.className = 'retro-select-dropdown hidden absolute z-50';
        this.dropdown.innerHTML = `
            <div class="retro-select-search-box">
                <div class="relative flex items-center">
                    <i class="fa-solid fa-magnifying-glass absolute left-2 text-[10px] text-slate-400"></i>
                    <input type="text"
                           placeholder="Search..."
                           autocomplete="off"
                           class="retro-select-search-input" />
                </div>
            </div>
            <div class="retro-select-options-list"></div>
            <div class="retro-select-no-results hidden">No matches found</div>
        `;
        this.searchInput = this.dropdown.querySelector('.retro-select-search-input');
        this.optionsList = this.dropdown.querySelector('.retro-select-options-list');
        this.noResultsEl = this.dropdown.querySelector('.retro-select-no-results');
        this.container.appendChild(this.dropdown);

        this.refreshOptions();
        this.bindEvents();

        this.observer = new MutationObserver(() => this.refreshOptions());
        this.observer.observe(this.select, { childList: true, subtree: true });

        const instance = this;
        const originalDescriptor = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, 'value');
        if (originalDescriptor) {
            Object.defineProperty(this.select, 'value', {
                get() {
                    return originalDescriptor.get.call(this);
                },
                set(val) {
                    originalDescriptor.set.call(this, val);
                    instance.syncFromNative();
                },
                configurable: true
            });
        }
    }

    refreshOptions() {
        this.syncFromNative();
        this.renderOptions(this.searchInput?.value || '');
    }

    renderOptions(filterText = '') {
        const query = filterText.trim().toLowerCase();
        this.optionsList.innerHTML = '';
        this.filteredOptions = [];

        Array.from(this.select.options).forEach((opt) => {
            const text = opt.textContent;
            const matches = !query || text.toLowerCase().includes(query);
            if (!matches) return;

            const isSelected = opt.value === this.select.value;
            const item = document.createElement('div');
            item.className = `retro-select-option-item ${isSelected ? 'selected' : ''}`;
            item.dataset.value = opt.value;
            item.innerHTML = `
                <span class="truncate">${this.escapeHtml(text)}</span>
                ${isSelected ? '<i class="fa-solid fa-check text-[10px] text-cyan-500"></i>' : ''}
            `;

            item.addEventListener('click', (e) => {
                e.stopPropagation();
                this.selectValue(opt.value);
            });

            this.optionsList.appendChild(item);
            this.filteredOptions.push({ element: item, value: opt.value, text });
        });

        if (this.filteredOptions.length === 0) {
            this.noResultsEl.classList.remove('hidden');
        } else {
            this.noResultsEl.classList.add('hidden');
        }

        this.highlightIndex = -1;
    }

    selectValue(value) {
        if (this.select.value !== value) {
            this.select.value = value;
            this.select.dispatchEvent(new Event('change', { bubbles: true }));
        }
        this.syncFromNative();
        this.close();
        this.trigger?.focus?.();
    }

    syncFromNative() {
        const selectedOption = this.select.querySelector(`option[value="${this.select.value}"]`) || this.select.options[this.select.selectedIndex];
        if (this.labelEl && selectedOption) {
            this.labelEl.textContent = selectedOption.textContent;
        }
        if (this.isOpen) {
            this.renderOptions(this.searchInput?.value || '');
        }
    }

    open() {
        document.querySelectorAll('.retro-select-dropdown:not(.hidden)').forEach(d => {
            if (d !== this.dropdown) d.classList.add('hidden');
        });

        this.isOpen = true;
        this.dropdown.classList.remove('hidden');
        this.chevronEl.classList.add('rotate-180');
        this.searchInput.value = '';
        this.renderOptions('');

        const rect = this.dropdown.getBoundingClientRect?.() || { right: 0 };
        if (rect.right > (window.innerWidth || 1024) - 10) {
            this.dropdown.style.left = 'auto';
            this.dropdown.style.right = '0';
        } else {
            this.dropdown.style.left = '0';
            this.dropdown.style.right = 'auto';
        }

        setTimeout(() => this.searchInput.focus(), 50);
    }

    close() {
        this.isOpen = false;
        this.dropdown.classList.add('hidden');
        this.chevronEl.classList.remove('rotate-180');
        this.highlightIndex = -1;
    }

    toggle() {
        if (this.isOpen) this.close();
        else this.open();
    }

    bindEvents() {
        this.trigger.addEventListener('click', (e) => {
            e.stopPropagation();
            this.toggle();
        });

        this.searchInput.addEventListener('input', () => {
            this.renderOptions(this.searchInput.value);
        });

        this.searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                this.moveHighlight(1);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                this.moveHighlight(-1);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (this.highlightIndex >= 0 && this.filteredOptions[this.highlightIndex]) {
                    this.selectValue(this.filteredOptions[this.highlightIndex].value);
                } else if (this.filteredOptions.length > 0) {
                    this.selectValue(this.filteredOptions[0].value);
                }
            } else if (e.key === 'Escape') {
                this.close();
                this.trigger?.focus?.();
            }
        });

        this.select.addEventListener('change', () => {
            this.syncFromNative();
        });

        document.addEventListener('click', (e) => {
            if (!this.container.contains(e.target)) {
                this.close();
            }
        });
    }

    moveHighlight(direction) {
        if (this.filteredOptions.length === 0) return;
        this.highlightIndex += direction;
        if (this.highlightIndex < 0) this.highlightIndex = 0;
        if (this.highlightIndex >= this.filteredOptions.length) this.highlightIndex = this.filteredOptions.length - 1;

        this.filteredOptions.forEach((opt, idx) => {
            opt.element.classList.toggle('highlighted', idx === this.highlightIndex);
            if (idx === this.highlightIndex) {
                opt.element.scrollIntoView({ block: 'nearest' });
            }
        });
    }

    escapeHtml(str) {
        return (str || '').replace(/[&<>"']/g, m => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[m]));
    }

    static initAll(root = document) {
        root.querySelectorAll('select').forEach(select => {
            if (!select.dataset.retroSelectInit && !select.classList.contains('no-retro-select')) {
                new RetroSearchableSelect(select);
            }
        });
    }
}

class NotenuxPanel {
    constructor() {
        this.audio = new RetroAudio();
        this.toast = new RetroToast(this.audio);
        this.notes = [];
        this.categories = [];
        this.activeNote = null;
        this.activeCategory = 'all';
        this.searchQuery = '';
        this.searchResultIds = null;
        this.currentSort = 'updated_desc';
        this.wordGoal = parseInt(localStorage.getItem('notenux_word_goal') || '250', 10);
        this.saveTimeout = null;
        this.xpGainedSession = 0;
        this.currentZoom = 100;
        this.isRawMarkdownMode = false;
        this.goalCelebrated = false;
        this.saveQueue = Promise.resolve();
        this.selectionVersion = 0;
        this.dirtyNotes = new Set();

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
            quickEditCategoryBtn: document.getElementById('quick-edit-category-btn'),
            quickDeleteCategoryBtn: document.getElementById('quick-delete-category-btn'),
            quickForgeCategoryBtn: document.getElementById('quick-forge-category-btn'),
            quickEditCategoryModal: document.getElementById('quick-edit-category-modal'),
            quickDeleteCategoryModal: document.getElementById('quick-delete-category-modal'),
            quickForgeCategoryModal: document.getElementById('quick-forge-category-modal'),
            quickEditCategoryForm: document.getElementById('quick-edit-category-form'),
            quickForgeCategoryForm: document.getElementById('quick-forge-category-form'),
            sidebarCategoriesList: document.getElementById('sidebar-categories-list'),
            categoryFilterPillsRow: document.getElementById('category-filter-pills-row'),
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
            copyMarkdownBtn: document.getElementById('copy-markdown-btn'),
            copyHtmlBtn: document.getElementById('copy-html-btn'),
            zenModeBtn: document.getElementById('zen-mode-btn'),
            archiveNoteBtn: document.getElementById('archive-note-btn'),
            deleteNoteBtn: document.getElementById('delete-note-btn'),
            deleteConfirmModal: document.getElementById('delete-confirm-modal'),
            deleteModalScrollTitle: document.getElementById('delete-modal-scroll-title'),
            closeDeleteModalX: document.getElementById('close-delete-modal-x'),
            cancelDeleteModalBtn: document.getElementById('cancel-delete-modal-btn'),
            confirmDeleteModalBtn: document.getElementById('confirm-delete-modal-btn'),

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
        this.initAiAssistant();
        this.bindKeyboardShortcuts();
        window.addEventListener('beforeunload', event => {
            if (this.dirtyNotes.size > 0) {
                this.saveDraftLocal();
                event.preventDefault();
                event.returnValue = '';
            }
        });
        this.loadNotesFromDOM();
        this.refreshPlayerStats();
    }

    initAiAssistant() {
        const get = id => document.getElementById(id);
        const generate = get('ai-generate-btn');
        if (!generate) return;
        const result = get('ai-result');
        const status = get('ai-status');
        const cancel = get('ai-cancel-btn');
        const append = get('ai-append-btn');
        const replace = get('ai-replace-btn');
        let controller;
        let context;
        let requestVersion = 0;
        cancel.addEventListener('click', () => controller?.abort());
        generate.addEventListener('click', async () => {
            if (!this.activeNote || controller) return;
            const action = get('ai-action').value;
            const instruction = get('ai-instruction').value.trim();
            const content = MarkdownEngine.toMarkdown(this.activeNote.content || '');
            if (!content.trim() || content.length > 30000 || (['custom', 'translate'].includes(action) && !instruction)) {
                status.textContent = 'Isi naskah 1–30.000 karakter dan instruksi / bahasa tujuan bila diperlukan.';
                return;
            }
            context = { id: this.activeNote.id, content: this.activeNote.content };
            const version = ++requestVersion;
            controller = new AbortController();
            generate.disabled = true;
            cancel.disabled = false;
            append.disabled = replace.disabled = true;
            result.value = '';
            result.readOnly = true;
            status.textContent = 'AI sedang menyiapkan hasil…';
            try {
                const response = await fetch(`/api/notes/${context.id}/assistant`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action, instruction, content }),
                    signal: controller.signal,
                });
                const data = await response.json();
                if (!response.ok) throw new Error(data.message || 'Permintaan AI gagal.');
                if (version !== requestVersion) return;
                result.value = data.data.content;
                result.readOnly = false;
                append.disabled = replace.disabled = false;
                status.textContent = 'Tinjau hasil sebelum menerapkannya. Naskah belum diubah.';
            } catch (error) {
                status.textContent = error.name === 'AbortError' ? 'Permintaan dibatalkan. Naskah tidak diubah.' : error.message;
            } finally {
                controller = null;
                generate.disabled = false;
                cancel.disabled = true;
            }
        });
        const apply = mode => {
            if (!context || this.activeNote?.id !== context.id || this.activeNote.content !== context.content) {
                status.textContent = 'Naskah telah berubah. Generate ulang untuk menghindari penimpaan perubahan.';
                return;
            }
            if (!result.value.trim()) return;
            const html = MarkdownEngine.parse(result.value);
            this.dom.editorArea.innerHTML = mode === 'append' ? this.dom.editorArea.innerHTML + html : html;
            if (this.isRawMarkdownMode) this.dom.markdownRawEditor.value = MarkdownEngine.toMarkdown(this.dom.editorArea.innerHTML);
            this.handleEditorInput();
            append.disabled = replace.disabled = true;
            status.textContent = 'Hasil diterapkan. Autosave dijadwalkan.';
        };
        append.addEventListener('click', () => apply('append'));
        replace.addEventListener('click', () => apply('replace'));
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
                this.dom.themeToggleBtn.innerHTML = '<i class="fa-solid fa-sun text-amber-800 mr-1.5"></i> <span class="hidden sm:inline">DAY</span>';
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
        this.dom.shortcutsModal?.addEventListener('click', (e) => {
            if (e.target === this.dom.shortcutsModal) {
                this.audio.playClick();
                this.dom.shortcutsModal.classList.add('hidden');
            }
        });
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
        this.dom.importModal.addEventListener('click', (e) => {
            if (e.target === this.dom.importModal) {
                closeModal();
            }
        });

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
            if (this.isRawMarkdownMode) this.toggleMarkdownRawView();
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
        this.dom.editorArea.normalize();
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
            if (window.innerWidth >= 1024) {
                this.dom.primaryNavSidebar?.classList.toggle('collapsed');
                const isColl = this.dom.primaryNavSidebar?.classList.contains('collapsed');
                localStorage.setItem('notenux_primary_collapsed', isColl ? 'true' : 'false');
                this.toast.show(isColl ? 'Main navigation collapsed' : 'Main navigation expanded', 'info');
            } else {
                this.dom.primaryNavSidebar?.classList.toggle('-translate-x-full');
                this.dom.notesExplorerSidebar?.classList.add('-translate-x-full');
                this.updateMobileOverlay();
            }
        });

        this.dom.mobileExplorerToggleBtn?.addEventListener('click', () => {
            this.audio.playBlip();
            if (window.innerWidth >= 1024) {
                this.dom.notesExplorerSidebar?.classList.toggle('collapsed');
                const isColl = this.dom.notesExplorerSidebar?.classList.contains('collapsed');
                localStorage.setItem('notenux_explorer_collapsed', isColl ? 'true' : 'false');
                this.toast.show(isColl ? 'Scroll Explorer collapsed' : 'Scroll Explorer expanded', 'info');
            } else {
                this.dom.notesExplorerSidebar?.classList.toggle('-translate-x-full');
                this.dom.primaryNavSidebar?.classList.add('-translate-x-full');
                this.updateMobileOverlay();
            }
        });

        this.dom.closeMobileNavBtn?.addEventListener('click', () => {
            this.audio.playBlip();
            if (window.innerWidth >= 1024) {
                this.dom.primaryNavSidebar?.classList.add('collapsed');
                localStorage.setItem('notenux_primary_collapsed', 'true');
                this.toast.show('Main navigation collapsed', 'info');
            } else {
                this.dom.primaryNavSidebar?.classList.add('-translate-x-full');
                this.updateMobileOverlay();
            }
        });

        this.dom.closeMobileExplorerBtn?.addEventListener('click', () => {
            this.audio.playBlip();
            if (window.innerWidth >= 1024) {
                this.dom.notesExplorerSidebar?.classList.add('collapsed');
                localStorage.setItem('notenux_explorer_collapsed', 'true');
                this.toast.show('Scroll Explorer collapsed', 'info');
            } else {
                this.dom.notesExplorerSidebar?.classList.add('-translate-x-full');
                this.updateMobileOverlay();
            }
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
                this.currentZoom = Math.min(200, this.currentZoom + 15);
                updateZoomUI();
                this.toast.show(`Content Zoom: ${this.currentZoom}%`, 'info', null, 'fa-solid fa-magnifying-glass-plus text-cyan-400');
            }
        });

        this.dom.zoomOutBtn?.addEventListener('click', () => {
            if (this.currentZoom > 60) {
                this.currentZoom = Math.max(60, this.currentZoom - 15);
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
    adjustZoom(delta) {
        this.currentZoom = Math.min(200, Math.max(60, this.currentZoom + delta));
        if (this.dom.editorZoomWrapper) {
            this.dom.editorZoomWrapper.style.transform = `scale(${this.currentZoom / 100})`;
            this.dom.editorZoomWrapper.style.width = `${10000 / this.currentZoom}%`;
        }
        if (this.dom.zoomLevelDisplay) this.dom.zoomLevelDisplay.textContent = `${this.currentZoom}%`;
    }

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
                this.closeDeleteConfirmModal();
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
        const initialCatEl = document.getElementById('initial-categories-data');
        if (initialCatEl) {
            try {
                this.categories = JSON.parse(initialCatEl.textContent);
            } catch (e) {
                this.categories = [];
            }
        }

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
            this.createNewNote(true);
        }
    }

    /* ---------------- EVENTS ---------------- */
    bindEvents() {
        this.dom.searchInput?.addEventListener('input', (e) => {
            this.searchQuery = e.target.value.toLowerCase().trim();
            this.searchResultIds = null;
            clearTimeout(this.searchTimeout);
            if (this.searchQuery) this.searchTimeout = setTimeout(() => this.searchNotes(), 250);
            this.renderNotesList();
        });

        this.dom.sortNotesSelect?.addEventListener('change', (e) => {
            this.currentSort = e.target.value;
            this.renderNotesList();
            const text = e.target.options[e.target.selectedIndex]?.text || e.target.value;
            this.toast.show(`Scrolls sorted by ${text}`, 'info', 'Sorting');
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

        // Quick Category Management Actions
        this.dom.quickForgeCategoryBtn?.addEventListener('click', () => {
            this.openQuickForgeCategoryModal();
        });

        this.dom.quickEditCategoryBtn?.addEventListener('click', () => {
            this.openQuickEditCategoryModal();
        });

        this.dom.quickDeleteCategoryBtn?.addEventListener('click', () => {
            this.openQuickDeleteCategoryModal();
        });

        // Close quick category modals
        document.getElementById('close-quick-edit-cat-btn')?.addEventListener('click', () => this.closeQuickCategoryModals());
        document.getElementById('cancel-quick-edit-cat-btn')?.addEventListener('click', () => this.closeQuickCategoryModals());
        document.getElementById('close-quick-delete-cat-btn')?.addEventListener('click', () => this.closeQuickCategoryModals());
        document.getElementById('cancel-quick-delete-cat-btn')?.addEventListener('click', () => this.closeQuickCategoryModals());
        document.getElementById('close-quick-forge-cat-btn')?.addEventListener('click', () => this.closeQuickCategoryModals());
        document.getElementById('cancel-quick-forge-cat-btn')?.addEventListener('click', () => this.closeQuickCategoryModals());

        // Backdrop click for quick category modals
        [this.dom.quickEditCategoryModal, this.dom.quickDeleteCategoryModal, this.dom.quickForgeCategoryModal].forEach(modal => {
            modal?.addEventListener('click', (e) => {
                if (e.target === modal) this.closeQuickCategoryModals();
            });
        });

        // Quick Forge Form submit
        this.dom.quickForgeCategoryForm?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.submitQuickForgeCategory();
        });

        // Quick Edit Form submit
        this.dom.quickEditCategoryForm?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.submitQuickEditCategory();
        });

        // Quick Delete confirm button
        document.getElementById('confirm-quick-delete-cat-btn')?.addEventListener('click', () => {
            this.confirmQuickDeleteCategory();
        });

        // Sidebar category action delegation (Edit & Delete)
        this.dom.sidebarCategoriesList?.addEventListener('click', (e) => {
            const editBtn = e.target.closest('.sidebar-edit-category-btn');
            if (editBtn) {
                e.preventDefault();
                e.stopPropagation();
                this.openQuickEditCategoryModal(editBtn.dataset.slug);
                return;
            }

            const deleteBtn = e.target.closest('.sidebar-delete-category-btn');
            if (deleteBtn) {
                e.preventDefault();
                e.stopPropagation();
                this.openQuickDeleteCategoryModal(deleteBtn.dataset.slug);
                return;
            }
        });

        this.dom.editorArea?.addEventListener('input', () => {
            this.handleEditorInput();
        });

        // Interactive Checklist State Persistence
        this.dom.editorArea?.addEventListener('change', (e) => {
            if (e.target && e.target.type === 'checkbox') {
                if (e.target.checked) {
                    e.target.setAttribute('checked', 'checked');
                } else {
                    e.target.removeAttribute('checked');
                }
                this.audio.playBlip();
                this.handleEditorInput();
            }
        });

        // Ctrl + Mouse Wheel Canvas Zoom
        this.dom.editorArea?.addEventListener('wheel', (e) => {
            if (e.ctrlKey || e.metaKey) {
                e.preventDefault();
                if (e.deltaY < 0) {
                    this.adjustZoom(10);
                } else {
                    this.adjustZoom(-10);
                }
            }
        }, { passive: false });

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

        this.dom.archiveNoteBtn?.addEventListener('click', () => {
            this.archiveActiveNote();
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

        this.dom.copyMarkdownBtn?.addEventListener('click', () => {
            if (!this.activeNote) return;
            this.audio.playCoin();
            const md = MarkdownEngine.toMarkdown(this.dom.editorArea?.innerHTML || this.activeNote.content || '');
            if (navigator.clipboard) {
                navigator.clipboard.writeText(md);
                this.toast.show('Scroll Markdown copied to clipboard!', 'success', 'Markdown Copied', 'fa-brands fa-markdown text-cyan-400');
            }
            this.dom.exportMenu?.classList.add('hidden');
        });

        this.dom.copyHtmlBtn?.addEventListener('click', () => {
            if (!this.activeNote) return;
            this.audio.playCoin();
            const html = this.dom.editorArea?.innerHTML || this.activeNote.content || '';
            if (navigator.clipboard) {
                navigator.clipboard.writeText(html);
                this.toast.show('Scroll HTML copied to clipboard!', 'success', 'HTML Copied', 'fa-solid fa-code text-emerald-400');
            }
            this.dom.exportMenu?.classList.add('hidden');
        });

        this.dom.zenModeBtn?.addEventListener('click', () => {
            this.toggleZenMode();
        });

        // Visual Delete Confirmation Modal triggers
        this.dom.deleteNoteBtn?.addEventListener('click', () => {
            this.openDeleteConfirmModal();
        });

        this.dom.closeDeleteModalX?.addEventListener('click', () => {
            this.closeDeleteConfirmModal();
        });

        this.dom.cancelDeleteModalBtn?.addEventListener('click', () => {
            this.closeDeleteConfirmModal();
        });

        this.dom.confirmDeleteModalBtn?.addEventListener('click', () => {
            this.executeDeleteActiveNote();
        });

        this.dom.deleteConfirmModal?.addEventListener('click', (e) => {
            if (e.target === this.dom.deleteConfirmModal) {
                this.closeDeleteConfirmModal();
            }
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

        this.dom.chunkModal?.addEventListener('click', (e) => {
            if (e.target === this.dom.chunkModal) {
                this.audio.playBlip();
                this.closeChunkInspector();
            }
        });

        this.dom.verifyChunksBtn?.addEventListener('click', () => {
            this.verifyChunksIntegrity();
        });

        this.dom.fontFamilySelect?.addEventListener('change', (e) => {
            this.audio.playClick();
            this.applyFontFamily(e.target.value);
            const text = e.target.options[e.target.selectedIndex]?.text || e.target.value;
            this.toast.show(`Font changed to ${text}`, 'info', 'Typography');
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
        document.querySelectorAll('[data-command], [data-color], #insert-table-btn, #insert-task-btn, #insert-media-btn').forEach(button => {
            button.addEventListener('mousedown', event => event.preventDefault());
        });
        document.querySelectorAll('[data-command]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                this.audio.playClick();
                const command = btn.dataset.command;
                const value = btn.dataset.value || null;

                if (command === 'createLink') {
                    const url = prompt('Enter link URL (https://...):');
                    if (url && /^(https?:\/\/|mailto:|#)/i.test(url)) {
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

            if (textHtml) {
                e.preventDefault();
                const parser = new DOMParser();
                const doc = parser.parseFromString(textHtml, 'text/html');
                doc.querySelectorAll('table').forEach(tbl => {
                    tbl.classList.add('retro-table');
                });
                document.execCommand('insertHTML', false, sanitizeHtml(doc.body.innerHTML));
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

        if (!/^https?:\/\//i.test(src)) {
            this.toast.show('Gunakan URL gambar HTTP atau HTTPS.', 'warning');
            return;
        }
        const image = document.createElement('img');
        image.src = src;
        image.alt = alt;
        image.className = 'rounded-lg border-2 border-blue-500 max-h-72 shadow-md my-2';
        const imgHtml = `<p>${image.outerHTML}</p><p><br></p>`;
        document.execCommand('insertHTML', false, imgHtml);
        this.dom.editorArea.focus();
        this.handleEditorInput();
        this.toast.show('Artifact image injected into scroll', 'success', 'Media Ready');
    }

    toggleMarkdownRawView() {
        this.toggleFindBar(false);
        this.audio.playBlip();
        this.isRawMarkdownMode = !this.isRawMarkdownMode;

        if (this.isRawMarkdownMode) {
            const md = MarkdownEngine.toMarkdown(this.dom.editorArea.innerHTML);
            this.rawViewOriginalHtml = this.dom.editorArea.innerHTML;
            this.rawViewOriginalMarkdown = md;
            this.dom.markdownRawEditor.value = md;
            this.dom.editorArea.classList.add('hidden');
            this.dom.markdownRawEditor.classList.remove('hidden');
            this.dom.markdownViewToggleBtn.innerHTML = '<i class="fa-solid fa-eye mr-1 text-cyan-400"></i> WYSIWYG';
            this.toast.show('Raw Markdown View activated', 'info');
        } else {
            const html = this.dom.markdownRawEditor.value === this.rawViewOriginalMarkdown
                ? this.rawViewOriginalHtml : MarkdownEngine.parse(this.dom.markdownRawEditor.value);
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

        const content = this.dom.editorArea.cloneNode(true);
        content.querySelectorAll('mark.search-match').forEach(mark => mark.replaceWith(...mark.childNodes));
        this.activeNote.content = content.innerHTML;
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
                    `Daily Quest Target achieved! (${words}/${this.wordGoal} words written)`,
                    'xp',
                    'Quest Complete',
                    'fa-solid fa-trophy text-yellow-400'
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
        if (!this.activeNote) return;
        this.dirtyNotes.add(this.activeNote.id);
        this.saveDraftLocal();
        if (this.dom.saveStatusText) {
            this.dom.saveStatusText.innerHTML = '<span class="text-amber-500 animate-pulse"><i class="fa-solid fa-spinner fa-spin mr-1"></i> Inscribing...</span>';
        }

        clearTimeout(this.saveTimeout);
        this.saveTimeout = setTimeout(() => {
            this.saveActiveNote(false);
        }, 900);
    }

    /* ---------------- CRUD & API SYNC ---------------- */
    saveActiveNote(manual = false) {
        if (!this.activeNote) return Promise.resolve(false);
        clearTimeout(this.saveTimeout);
        const note = this.activeNote;
        const snapshot = { ...note };
        const task = this.saveQueue.then(() => this.persistNote(note, snapshot, manual));
        this.saveQueue = task.catch(() => false);
        return task;
    }

    async persistNote(note, snapshot, manual) {

        try {
            const payload = {
                title: snapshot.title || 'Untitled Scroll',
                category: snapshot.category || 'quest',
                content: snapshot.content || '',
                font_family: snapshot.font_family || 'pixel',
                font_size: snapshot.font_size || '16px',
                theme_color: snapshot.theme_color || 'blue',
            };

            const response = await fetch(`/api/notes/${note.id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify(payload),
            });

            const data = await response.json();
            if (response.ok && data.status === 'success') {
                const unchanged = ['title', 'content', 'category', 'font_family', 'font_size', 'theme_color'].every(key => note[key] === snapshot[key]);
                if (unchanged) Object.assign(note, data.data, { is_pinned: note.is_pinned, is_favorite: note.is_favorite });
                if (unchanged) this.dirtyNotes.delete(note.id);

                // Clear draft backup since persisted
                if (unchanged) localStorage.removeItem(`notenux_draft_${note.id}`);

                if (this.dom.saveStatusText && this.activeNote?.id === note.id && unchanged) {
                    this.dom.saveStatusText.innerHTML = '<span class="text-emerald-500"><i class="fa-solid fa-check mr-1"></i> Synchronized</span>';
                }

                this.renderNotesList();
                this.refreshPlayerStats();

                if (manual) {
                    this.toast.show(
                        `Scroll "${note.title}" inscribed into database chunks!`,
                        'success',
                        'Saved to Matrix',
                        'fa-solid fa-floppy-disk text-emerald-400'
                    );
                }
                return true;
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
            return false;
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
        const note = this.activeNote;
        this.audio.playClick();

        try {
            const response = await fetch(`/api/notes/${note.id}/pin`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
            });

            const data = await response.json();
            if (response.ok) {
                note.is_pinned = data.is_pinned;
                if (this.activeNote?.id === note.id) this.updatePinButtonUI();
                this.renderNotesList();

                this.toast.show(
                    note.is_pinned ? 'Scroll pinned to top of Grimoire' : 'Scroll unpinned',
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
        const note = this.activeNote;
        this.audio.playClick();

        try {
            const response = await fetch(`/api/notes/${note.id}/favorite`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
            });

            const data = await response.json();
            if (response.ok) {
                note.is_favorite = data.is_favorite;
                if (this.activeNote?.id === note.id) this.updateFavoriteButtonUI();
                this.renderNotesList();

                this.toast.show(
                    note.is_favorite ? 'Scroll saved to bookmarks!' : 'Scroll removed from favorites',
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
        if (!await this.saveActiveNote()) return;
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

    async exportActiveNote(format) {
        if (!this.activeNote) return;
        if (!await this.saveActiveNote()) return;
        this.audio.playCoin();
        window.location.assign(`/api/notes/${this.activeNote.id}/export/${format}`);
        this.toast.show(`Exporting scroll as .${format.toUpperCase()}`, 'info', 'Export Started', 'fa-solid fa-download text-blue-400');
    }

    archiveActiveNote() {
        if (!this.activeNote) return;
        this.activeNote.category = 'archive';
        if (this.dom.noteCategorySelect) {
            this.dom.noteCategorySelect.value = 'archive';
        }
        this.updateActiveNoteInList();
        this.saveActiveNote(true);
        this.toast.show(
            `Scroll "${this.activeNote.title}" moved to Library Vault!`,
            'info',
            'Scroll Archived',
            'fa-solid fa-box-archive text-amber-500'
        );
    }

    openDeleteConfirmModal() {
        if (!this.activeNote) {
            this.toast.show('No active scroll selected to banish', 'warning');
            return;
        }

        if (this.dom.deleteModalScrollTitle) {
            this.dom.deleteModalScrollTitle.textContent = this.activeNote.title || 'Untitled Scroll';
        }

        if (this.dom.deleteConfirmModal) {
            this.dom.deleteConfirmModal.classList.remove('hidden');
            this.audio.playBlip();
            this.dom.cancelDeleteModalBtn?.focus();
        }
    }

    closeDeleteConfirmModal() {
        if (this.dom.deleteConfirmModal) {
            this.dom.deleteConfirmModal.classList.add('hidden');
        }
    }

    async executeDeleteActiveNote() {
        if (!this.activeNote) return;

        const noteId = this.activeNote.id;
        clearTimeout(this.saveTimeout);
        await this.saveQueue;
        const title = this.activeNote.title || 'Untitled Scroll';
        this.closeDeleteConfirmModal();
        this.audio.playDelete();

        try {
            const response = await fetch(`/api/notes/${noteId}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                },
            });

            if (response.ok) {
                this.notes = this.notes.filter(n => n.id !== noteId);
                this.activeNote = null;
                this.dirtyNotes.delete(noteId);
                localStorage.removeItem(`notenux_draft_${noteId}`);

                this.renderNotesList();
                if (this.notes.length > 0) {
                    await this.selectNote(this.notes[0]);
                } else {
                    this.createNewNote(true);
                }
                this.refreshPlayerStats();

                this.toast.show(
                    `Scroll "${title}" banished to the digital void!`,
                    'danger',
                    'Scroll Banished',
                    'fa-solid fa-skull-crossbones text-rose-500'
                );
            } else {
                this.toast.show('Failed to banish scroll from database', 'danger');
            }
        } catch (e) {
            this.toast.show('Network error while banishing scroll', 'danger');
        }
    }

    deleteActiveNote() {
        this.openDeleteConfirmModal();
    }

    async selectNote(note) {
        const version = ++this.selectionVersion;
        if (this.activeNote && this.dirtyNotes.has(this.activeNote.id)) {
            if (!await this.saveActiveNote()) return;
        }
        if (version !== this.selectionVersion) return;
        this.audio.playBlip();

        // If content is not yet in memory (due to optimized lightweight list queries), fetch on demand
        if (note.content === undefined || note.content === null) {
            try {
                const response = await fetch(`/api/notes/${note.id}`);
                const data = await response.json();
                if (response.ok && data.data) {
                    if (version !== this.selectionVersion) return;
                    note = data.data;
                    const idx = this.notes.findIndex(n => n.id === note.id);
                    if (idx !== -1) {
                        this.notes[idx] = data.data;
                    }
                } else {
                    throw new Error('Load failed');
                }
            } catch (e) {
                this.toast.show('Tidak dapat memuat naskah. Coba lagi.', 'danger');
                return;
            }
        }

        if (version !== this.selectionVersion) return;
        this.activeNote = note;
        this.closeChunkInspector();
        this.renderNotesList();
        this.loadNoteIntoEditor(note);
        if (window.innerWidth < 1024) {
            this.closeMobileDrawers();
        }
    }

    loadNoteIntoEditor(note) {
        if (!note) return;
        this.clearFindHighlights();
        try {
            const draft = JSON.parse(localStorage.getItem(`notenux_draft_${note.id}`) || 'null');
            if (draft && draft.timestamp > new Date(note.updated_at || 0).getTime()) {
                Object.assign(note, { title: draft.title, content: draft.content });
                this.dirtyNotes.add(note.id);
                this.toast.show('Draft lokal dipulihkan. Simpan untuk sinkronisasi.', 'warning');
            }
        } catch (_) {}

        if (this.dom.noteTitleInput) {
            this.dom.noteTitleInput.value = note.title;
        }

        if (this.dom.noteCategorySelect) {
            this.dom.noteCategorySelect.value = note.category || 'quest';
        }

        if (this.dom.editorArea) {
            this.dom.editorArea.innerHTML = sanitizeHtml(note.content || '');
        }

        if (this.dom.markdownRawEditor) {
            this.dom.markdownRawEditor.value = MarkdownEngine.toMarkdown(note.content || '');
            this.rawViewOriginalMarkdown = this.dom.markdownRawEditor.value;
            this.rawViewOriginalHtml = this.dom.editorArea.innerHTML;
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
            this.dom.pinNoteBtn.innerHTML = '<i class="fa-solid fa-thumbtack text-black"></i>';
            this.dom.pinNoteBtn.classList.add('bg-amber-400', 'text-black', 'border-black');
            this.dom.pinNoteBtn.classList.remove('bg-surface', 'text-slate-700', 'dark:text-slate-300', 'bg-blue-600', 'text-white');
            this.dom.pinNoteBtn.title = 'Unpin Scroll';
        } else {
            this.dom.pinNoteBtn.innerHTML = '<i class="fa-solid fa-thumbtack text-slate-600 dark:text-slate-300"></i>';
            this.dom.pinNoteBtn.classList.remove('bg-amber-400', 'text-black', 'bg-blue-600', 'text-white');
            this.dom.pinNoteBtn.classList.add('bg-surface', 'text-slate-700', 'dark:text-slate-300');
            this.dom.pinNoteBtn.title = 'Pin Scroll to Top';
        }
    }

    updateFavoriteButtonUI() {
        if (!this.dom.favoriteNoteBtn || !this.activeNote) return;
        if (this.activeNote.is_favorite) {
            this.dom.favoriteNoteBtn.innerHTML = '<i class="fa-solid fa-star text-amber-500"></i>';
            this.dom.favoriteNoteBtn.title = 'Remove Favorite';
        } else {
            this.dom.favoriteNoteBtn.innerHTML = '<i class="fa-regular fa-star text-amber-500"></i>';
            this.dom.favoriteNoteBtn.title = 'Add to Favorites';
        }
    }

    async searchNotes() {
        const query = this.searchQuery;
        try {
            const response = await fetch(`/api/notes?q=${encodeURIComponent(query)}`);
            const data = await response.json();
            if (!response.ok) throw new Error('Search failed');
            if (query !== this.searchQuery) return;
            this.searchResultIds = new Set(data.data.map(note => note.id));
            for (const summary of data.data) {
                if (!this.notes.some(note => note.id === summary.id)) this.notes.push(summary);
            }
            this.renderNotesList();
        } catch (_) {
            if (query === this.searchQuery) this.toast.show('Pencarian server gagal. Hasil lokal ditampilkan.', 'warning');
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
                this.searchResultIds?.has(n.id) ||
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
            const badgeClasses = isActive
                ? 'bg-black/30 text-white border-white/50'
                : categoryBadge.classes;

            return `
                <div data-note-id="${note.id}"
                     class="note-list-card cursor-pointer p-3 rounded-lg border-2 transition-all mb-2 select-none
                            ${isActive
                                ? 'bg-blue-600 text-white border-black dark:border-cyan-400 shadow-[3px_3px_0px_#000]'
                                : 'bg-surface hover:bg-slate-100 dark:hover:bg-blue-900/40 border-black/20 dark:border-blue-900/60 shadow-[2px_2px_0px_rgba(0,0,0,0.15)] text-main'
                            }">
                    <div class="flex items-center justify-between gap-1 mb-1">
                        <div class="flex items-center gap-1.5 overflow-hidden">
                            ${note.is_pinned ? '<i title="Pinned Scroll" class="fa-solid fa-thumbtack text-amber-400 text-xs shrink-0"></i>' : ''}
                            ${note.is_favorite ? '<i title="Favorite Scroll" class="fa-solid fa-star text-amber-400 text-xs shrink-0"></i>' : ''}
                            <h4 class="font-bold text-sm truncate ${isActive ? 'text-white' : 'text-main'}">
                                ${this.escapeHtml(note.title)}
                            </h4>
                        </div>
                        <span class="text-[10px] font-mono shrink-0 px-1.5 py-0.5 rounded border border-black/30 flex items-center gap-1 ${badgeClasses}">
                            <i class="${categoryBadge.icon}"></i>
                            ${categoryBadge.label}
                        </span>
                    </div>

                    <div class="flex items-center justify-between text-[11px] font-mono mt-2 pt-1 border-t ${isActive ? 'border-white/20 text-blue-100' : 'border-black/10 dark:border-white/10 text-slate-500 dark:text-slate-400'}">
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
        const colorMap = {
            amber: 'bg-amber-100 text-amber-900 dark:bg-amber-950/70 dark:text-amber-300 border-amber-300 dark:border-amber-800',
            purple: 'bg-purple-100 text-purple-900 dark:bg-purple-950/70 dark:text-purple-300 border-purple-300 dark:border-purple-800',
            rose: 'bg-rose-100 text-rose-900 dark:bg-rose-950/70 dark:text-rose-300 border-rose-300 dark:border-rose-800',
            cyan: 'bg-cyan-100 text-cyan-900 dark:bg-cyan-950/70 dark:text-cyan-300 border-cyan-300 dark:border-cyan-800',
            emerald: 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950/70 dark:text-emerald-300 border-emerald-300 dark:border-emerald-800',
            blue: 'bg-blue-100 text-blue-900 dark:bg-blue-950/70 dark:text-blue-300 border-blue-300 dark:border-blue-800',
            gray: 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-300 border-slate-300 dark:border-slate-700',
        };

        const found = this.categories?.find(c => c.slug === category);
        if (found) {
            return {
                label: (found.name || found.slug).toUpperCase(),
                icon: found.icon || 'fa-solid fa-folder',
                classes: colorMap[found.color] || colorMap.cyan,
            };
        }

        switch (category) {
            case 'quest':
                return { label: 'QUEST', icon: 'fa-solid fa-khanda', classes: colorMap.amber };
            case 'spellbook':
                return { label: 'SPELL', icon: 'fa-solid fa-wand-magic-sparkles', classes: colorMap.purple };
            case 'strategy':
                return { label: 'RAID', icon: 'fa-solid fa-shield-halved', classes: colorMap.rose };
            case 'lore':
                return { label: 'LORE', icon: 'fa-solid fa-scroll', classes: colorMap.cyan };
            case 'archive':
                return { label: 'ARCHIVE', icon: 'fa-solid fa-box-archive', classes: colorMap.amber };
            default:
                return { label: (category || 'NOTE').toUpperCase(), icon: 'fa-solid fa-book-journal-whills', classes: colorMap.gray };
        }
    }

    /* ---------------- CATEGORY FORGE & MANAGEMENT ---------------- */
    closeQuickCategoryModals() {
        this.dom.quickEditCategoryModal?.classList.add('hidden');
        this.dom.quickDeleteCategoryModal?.classList.add('hidden');
        this.dom.quickForgeCategoryModal?.classList.add('hidden');
    }

    openQuickForgeCategoryModal() {
        this.closeQuickCategoryModals();
        if (this.dom.quickForgeCategoryForm) {
            this.dom.quickForgeCategoryForm.reset();
        }
        this.dom.quickForgeCategoryModal?.classList.remove('hidden');
        document.getElementById('quick-forge-cat-name')?.focus();
    }

    async submitQuickForgeCategory() {
        const name = document.getElementById('quick-forge-cat-name')?.value?.trim();
        const slug = document.getElementById('quick-forge-cat-slug')?.value?.trim();
        const color = document.getElementById('quick-forge-cat-color')?.value || 'emerald';
        const icon = document.getElementById('quick-forge-cat-icon')?.value?.trim() || 'fa-solid fa-folder';
        const description = document.getElementById('quick-forge-cat-desc')?.value?.trim();

        if (!name) {
            this.toast.show('Nama kategori wajib diisi.', 'warning');
            return;
        }

        try {
            const response = await fetch('/api/categories', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ name, slug, color, icon, description }),
            });

            const data = await response.json();
            if (response.ok && data.data) {
                const newCat = data.data;
                this.categories.push(newCat);
                if (this.activeNote) {
                    this.activeNote.category = newCat.slug;
                    this.saveActiveNote(true);
                }
                this.syncCategoriesDOM();
                this.renderNotesList();
                this.closeQuickCategoryModals();
                this.audio.playPowerup();
                this.toast.show(`Category "${newCat.name}" forged successfully!`, 'success', 'Category Created');
            } else {
                const errorMsg = data.message || 'Gagal membuat kategori.';
                this.toast.show(errorMsg, 'danger', 'Forge Failed');
            }
        } catch (e) {
            this.toast.show('Network error during category creation.', 'danger');
        }
    }

    openQuickEditCategoryModal(slug = null) {
        this.closeQuickCategoryModals();
        const targetSlug = slug || this.dom.noteCategorySelect?.value || this.activeNote?.category || 'quest';
        const category = this.categories.find(c => c.slug === targetSlug) || {
            id: null,
            name: targetSlug.toUpperCase(),
            slug: targetSlug,
            color: 'cyan',
            icon: 'fa-solid fa-folder',
            description: '',
        };

        const idEl = document.getElementById('quick-edit-cat-id');
        const nameEl = document.getElementById('quick-edit-cat-name');
        const slugEl = document.getElementById('quick-edit-cat-slug');
        const colorEl = document.getElementById('quick-edit-cat-color');
        const iconEl = document.getElementById('quick-edit-cat-icon');
        const descEl = document.getElementById('quick-edit-cat-desc');

        if (idEl) idEl.value = category.id || '';
        if (nameEl) nameEl.value = category.name || '';
        if (slugEl) slugEl.value = category.slug || '';
        if (colorEl) colorEl.value = category.color || 'cyan';
        if (iconEl) iconEl.value = category.icon || 'fa-solid fa-folder';
        if (descEl) descEl.value = category.description || '';

        this.dom.quickEditCategoryModal?.classList.remove('hidden');
        nameEl?.focus();
    }

    async submitQuickEditCategory() {
        const id = document.getElementById('quick-edit-cat-id')?.value;
        const name = document.getElementById('quick-edit-cat-name')?.value?.trim();
        const slug = document.getElementById('quick-edit-cat-slug')?.value?.trim();
        const color = document.getElementById('quick-edit-cat-color')?.value || 'cyan';
        const icon = document.getElementById('quick-edit-cat-icon')?.value?.trim() || 'fa-solid fa-folder';
        const description = document.getElementById('quick-edit-cat-desc')?.value?.trim();

        if (!id) {
            this.toast.show('Category identifier missing.', 'warning');
            return;
        }

        if (!name) {
            this.toast.show('Nama kategori wajib diisi.', 'warning');
            return;
        }

        try {
            const response = await fetch(`/api/categories/${id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ name, slug, color, icon, description }),
            });

            const data = await response.json();
            if (response.ok && data.data) {
                const updatedCat = data.data;
                const idx = this.categories.findIndex(c => c.id === updatedCat.id);
                const oldSlug = idx !== -1 ? this.categories[idx].slug : null;

                if (idx !== -1) {
                    this.categories[idx] = updatedCat;
                }

                // If slug changed, cascade to existing notes in memory
                if (oldSlug && oldSlug !== updatedCat.slug) {
                    this.notes.forEach(note => {
                        if (note.category === oldSlug) {
                            note.category = updatedCat.slug;
                        }
                    });
                    if (this.activeNote && this.activeNote.category === oldSlug) {
                        this.activeNote.category = updatedCat.slug;
                    }
                    if (this.activeCategory === oldSlug) {
                        this.activeCategory = updatedCat.slug;
                    }
                }

                this.syncCategoriesDOM();
                this.renderNotesList();
                this.closeQuickCategoryModals();
                this.audio.playBlip();
                this.toast.show(`Category "${updatedCat.name}" updated successfully!`, 'success', 'Category Saved');
            } else {
                const errorMsg = data.message || 'Gagal memperbarui kategori.';
                this.toast.show(errorMsg, 'danger', 'Update Failed');
            }
        } catch (e) {
            this.toast.show('Network error during category update.', 'danger');
        }
    }

    openQuickDeleteCategoryModal(slug = null) {
        this.closeQuickCategoryModals();
        const targetSlug = slug || this.dom.noteCategorySelect?.value || this.activeNote?.category || 'quest';
        const category = this.categories.find(c => c.slug === targetSlug);

        if (!category) {
            this.toast.show('Kategori tidak ditemukan.', 'warning');
            return;
        }

        if (this.categories.length <= 1) {
            this.toast.show('Tidak dapat menghapus kategori terakhir. Harus menyisakan minimal satu kategori.', 'warning');
            return;
        }

        const idEl = document.getElementById('quick-delete-cat-id');
        const badgeEl = document.getElementById('quick-delete-cat-name-badge');

        if (idEl) idEl.value = category.id;
        if (badgeEl) badgeEl.textContent = `"${category.name}"`;

        this.dom.quickDeleteCategoryModal?.classList.remove('hidden');
    }

    async confirmQuickDeleteCategory() {
        const id = document.getElementById('quick-delete-cat-id')?.value;
        if (!id) return;

        const category = this.categories.find(c => String(c.id) === String(id));
        const deletedSlug = category?.slug;
        const deletedName = category?.name || 'Category';

        this.closeQuickCategoryModals();
        this.audio.playDelete();

        try {
            const response = await fetch(`/api/categories/${id}`, {
                method: 'DELETE',
            });

            const data = await response.json();
            if (response.ok) {
                const fallbackSlug = data.data?.fallback_slug || 'general';
                const fallbackName = data.data?.fallback_name || 'General Notes';

                // Remove from in-memory categories
                this.categories = this.categories.filter(c => String(c.id) !== String(id));

                // Reassign affected notes in memory
                if (deletedSlug) {
                    this.notes.forEach(note => {
                        if (note.category === deletedSlug) {
                            note.category = fallbackSlug;
                        }
                    });

                    if (this.activeNote && this.activeNote.category === deletedSlug) {
                        this.activeNote.category = fallbackSlug;
                    }

                    if (this.activeCategory === deletedSlug) {
                        this.activeCategory = 'all';
                    }
                }

                this.syncCategoriesDOM();
                this.renderNotesList();
                this.toast.show(`Category "${deletedName}" banished. Scrolls migrated to ${fallbackName}.`, 'success', 'Category Banished');
            } else {
                const errorMsg = data.message || 'Gagal menghapus kategori.';
                this.toast.show(errorMsg, 'danger', 'Delete Failed');
            }
        } catch (e) {
            this.toast.show('Network error during category deletion.', 'danger');
        }
    }

    syncCategoriesDOM() {
        // Sync note category dropdown options
        if (this.dom.noteCategorySelect) {
            const currentVal = this.activeNote?.category || this.dom.noteCategorySelect.value;
            this.dom.noteCategorySelect.innerHTML = this.categories.map(cat =>
                `<option value="${cat.slug}" data-id="${cat.id}">[${(cat.slug || '').toUpperCase()}] ${this.escapeHtml(cat.name)}</option>`
            ).join('');
            if (currentVal && this.categories.some(c => c.slug === currentVal)) {
                this.dom.noteCategorySelect.value = currentVal;
            } else if (this.categories.length > 0) {
                this.dom.noteCategorySelect.value = this.categories[0].slug;
                if (this.activeNote) {
                    this.activeNote.category = this.categories[0].slug;
                }
            }
        }

        // Sync import category dropdown options
        if (this.dom.importCategorySelect) {
            this.dom.importCategorySelect.innerHTML = this.categories.map(cat =>
                `<option value="${cat.slug}">${this.escapeHtml(cat.name)}</option>`
            ).join('');
        }

        // Sync filter pills row
        if (this.dom.categoryFilterPillsRow) {
            let pillsHtml = `
                <button class="category-filter-btn px-2 py-0.5 rounded border border-black/40 ${this.activeCategory === 'all' ? 'bg-blue-600 text-white active-category' : 'bg-surface'} whitespace-nowrap shadow-[1px_1px_0px_#000]"
                        data-category="all">
                    ALL
                </button>
            `;

            this.categories.forEach(cat => {
                const isActive = this.activeCategory === cat.slug;
                pillsHtml += `
                    <button class="category-filter-btn px-2 py-0.5 rounded border border-black/40 ${isActive ? 'bg-blue-600 text-white active-category' : 'bg-surface hover:bg-blue-200 dark:hover:bg-blue-900'} whitespace-nowrap shadow-[1px_1px_0px_#000]"
                            data-category="${cat.slug}">
                        <i class="${cat.icon || 'fa-solid fa-folder'} mr-1"></i>${this.escapeHtml((cat.name || cat.slug).slice(0, 10).toUpperCase())}
                    </button>
                `;
            });

            this.dom.categoryFilterPillsRow.innerHTML = pillsHtml;

            // Re-bind pills
            this.dom.categoryFilterPillsRow.querySelectorAll('.category-filter-btn').forEach(pill => {
                pill.addEventListener('click', () => {
                    this.audio.playBlip();
                    this.setCategory(pill.dataset.category || 'all');
                });
            });
            this.dom.categoryPills = document.querySelectorAll('.category-filter-btn');
        }

        // Sync left sidebar list
        if (this.dom.sidebarCategoriesList) {
            this.dom.sidebarCategoriesList.innerHTML = this.categories.map(cat => `
                <div class="nav-category-item group flex items-center justify-between rounded-lg hover:bg-blue-50 dark:hover:bg-blue-900/30 transition-colors pr-1.5"
                     data-category="${cat.slug}">
                    <a href="#" class="nav-category-link flex-1 flex items-center gap-2.5 px-3 py-2 rounded-lg truncate ${this.activeCategory === cat.slug ? 'bg-blue-600 text-white font-bold' : ''}"
                       data-category="${cat.slug}">
                        <i class="${cat.icon || 'fa-solid fa-folder'} text-sm w-4 text-center shrink-0"></i>
                        <span class="nav-text-label font-bold truncate">${this.escapeHtml(cat.name)}</span>
                    </a>
                    <div class="hidden group-hover:flex items-center gap-1 shrink-0">
                        <button type="button"
                                class="sidebar-edit-category-btn text-[10px] text-cyan-600 dark:text-cyan-400 hover:scale-110 transition-transform p-1"
                                data-slug="${cat.slug}"
                                title="Edit Category">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                        <button type="button"
                                class="sidebar-delete-category-btn text-[10px] text-rose-500 hover:scale-110 transition-transform p-1"
                                data-slug="${cat.slug}"
                                title="Delete Category">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                </div>
            `).join('');

            // Re-bind sidebar links
            this.dom.sidebarCategoriesList.querySelectorAll('.nav-category-link').forEach(link => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.audio.playBlip();
                    this.setCategory(link.dataset.category || 'all');
                    if (window.innerWidth < 1024) {
                        this.dom.primaryNavSidebar?.classList.add('-translate-x-full');
                        this.dom.notesExplorerSidebar?.classList.remove('-translate-x-full');
                        this.updateMobileOverlay();
                    }
                });
            });
            this.dom.navCategoryLinks = document.querySelectorAll('.nav-category-link');
        }
    }

    /* ---------------- CHUNK INSPECTOR & INTEGRITY AUDITOR ---------------- */
    async openChunkInspector() {
        if (!this.activeNote) return;
        const noteId = this.activeNote.id;
        if (this.dirtyNotes.has(this.activeNote.id) && !await this.saveActiveNote()) return;
        if (this.activeNote?.id !== noteId) return;
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
            const response = await fetch(`/api/notes/${noteId}/chunks`);
            const data = await response.json();
            if (this.activeNote?.id !== noteId) return;

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
                        <button onclick="window.notenux.moveChunk(${index}, -1)"
                                class="arcade-btn bg-surface px-1.5 py-0.5 text-[9px] rounded hover:bg-cyan-200"
                                title="Move Shard Up">
                            <i class="fa-solid fa-chevron-up"></i>
                        </button>
                        <button onclick="window.notenux.moveChunk(${index}, 1)"
                                class="arcade-btn bg-surface px-1.5 py-0.5 text-[9px] rounded hover:bg-cyan-200"
                                title="Move Shard Down">
                            <i class="fa-solid fa-chevron-down"></i>
                        </button>
                    </div>
                    <div class="flex items-center gap-2 text-[11px] opacity-75">
                        <span class="bg-slate-200 dark:bg-slate-800 px-1.5 py-0.5 rounded uppercase font-bold text-blue-600 dark:text-cyan-300">${this.escapeHtml(chunk.chunk_type)}</span>
                        <span>${chunk.byte_size} bytes</span>
                        <button onclick="window.notenux.toggleChunkEditor(${chunk.id})" class="arcade-btn bg-surface px-2 py-0.5 text-[10px] rounded hover:bg-blue-300">
                            <i class="fa-solid fa-pen-to-square"></i> Edit
                        </button>
                        <button onclick="window.notenux.deleteChunk(${chunk.id})" class="arcade-btn bg-red-600 text-white px-2 py-0.5 text-[10px] rounded hover:bg-red-500" title="Delete Shard">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                </div>
                <div class="text-[10px] text-gray-500 dark:text-gray-400 truncate mb-1">
                    SHA-256 Checksum: <span class="font-mono text-cyan-600 dark:text-cyan-400">${chunk.checksum || 'N/A'}</span>
                </div>
                <div id="chunk-view-${chunk.id}" class="bg-white dark:bg-black/50 p-2.5 rounded border border-gray-300 dark:border-gray-800 max-h-28 overflow-y-auto text-main text-[11px] whitespace-pre-wrap leading-relaxed">
                    ${this.escapeHtml(chunk.content)}
                </div>
                <div id="chunk-edit-${chunk.id}" class="hidden mt-2">
                    <textarea class="w-full p-2 bg-white dark:bg-black/80 border border-blue-500 rounded text-[11px] font-mono text-main leading-relaxed" rows="3">${this.escapeHtml(chunk.content)}</textarea>
                    <div class="flex justify-end gap-2 mt-1">
                        <button onclick="window.notenux.toggleChunkEditor(${chunk.id})" class="arcade-btn bg-surface px-2 py-0.5 text-[10px] rounded">Cancel</button>
                        <button onclick="window.notenux.saveIndividualChunk(${chunk.id})" class="arcade-btn bg-emerald-600 text-white px-2 py-0.5 text-[10px] rounded">Save Shard</button>
                    </div>
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
    RetroSearchableSelect.initAll();
});

window.RetroSearchableSelect = RetroSearchableSelect;
