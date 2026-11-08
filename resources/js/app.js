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
    const allowed = new Set('p div span br h1 h2 h3 h4 h5 h6 strong b em i u s del sub sup blockquote pre code ul ol li table thead tbody tfoot tr th td hr a img input font figure figcaption'.split(' '));
    for (const element of template.content.querySelectorAll('*')) {
        if (!allowed.has(element.localName)) {
            element.remove();
            continue;
        }
        for (const attribute of [...element.attributes]) {
            const { name, value } = attribute;
            const safe = ['class', 'title', 'alt', 'id', 'data-align', 'data-placeholder', 'contenteditable'].includes(name)
                || (name === 'type' && element.localName === 'input' && value === 'checkbox')
                || (['checked', 'disabled'].includes(name) && element.localName === 'input')
                || (['colspan', 'rowspan', 'width', 'height', 'size', 'start', 'border', 'cellpadding', 'cellspacing'].includes(name) && /^\d+$/.test(value))
                || (['color', 'bgcolor', 'bordercolor'].includes(name) && /^(#[a-f0-9]{3,8}|[a-z]+|rgb\(\s*\d+\s*,\s*\d+\s*,\s*\d+\s*\)|rgba\(\s*\d+\s*,\s*\d+\s*,\s*\d+\s*,\s*[0-9.]+\s*\))$/i.test(value))
                || (['align', 'valign'].includes(name) && ['left', 'center', 'right', 'justify', 'top', 'middle', 'bottom', 'baseline'].includes((value || '').toLowerCase()))
                || (name === 'style' && /^(\s*(color|background-color|background|text-align|vertical-align|border|border-color|border-width|border-style|border-radius|padding|padding-left|padding-right|padding-top|padding-bottom|margin|margin-left|margin-right|margin-top|margin-bottom|width|height|min-width|max-width|min-height|max-height|font-size|font-family|font-weight|font-style|text-decoration|display)\s*:\s*[a-z0-9#(),.%\s'"-]+;?\s*)+$/i.test(value) && !/(expression|behavior|javascript|vbscript|data:|url\s*\()/i.test(value))
                || (name === 'href' && element.localName === 'a' && /^(https?:\/\/|mailto:|#|\/(?!\/))/i.test(value))
                || (name === 'target' && element.localName === 'a' && ['_blank', '_self'].includes((value || '').toLowerCase()))
                || (name === 'rel' && element.localName === 'a' && /^[a-z\s_-]+$/i.test(value))
                || (name === 'src' && element.localName === 'img' && /^(https?:\/\/|data:image\/(?:png|jpe?g|gif|webp);base64,[A-Za-z0-9+/=\s]+$)/i.test((value || '').trim()));
            if (!safe) element.removeAttribute(name);
        }
        if (element.localName === 'a' && typeof element.getAttribute === 'function' && typeof element.setAttribute === 'function') {
            const target = (element.getAttribute('target') || '').toLowerCase();
            if (target === '_blank') {
                const existingRel = (element.getAttribute('rel') || '').trim();
                if (!existingRel.includes('noopener')) {
                    element.setAttribute('rel', existingRel ? `${existingRel} noopener` : 'noopener');
                }
            }
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
        setTimeout(dismiss, duration);
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
        html = html.replace(/!\[([^\]]*)\]\(((?:https?:\/\/|data:image\/(?:png|jpe?g|gif|webp);base64,[A-Za-z0-9+/=\s]+)[^\s)]*)\)/gi, (_, alt, url) => `<img alt="${MarkdownEngine.escape(alt).replace(/"/g, '&quot;')}" src="${MarkdownEngine.escape(url).replace(/"/g, '&quot;')}">`);
        html = html.replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+|mailto:[^\s)]+|#[^\s)]+)\)/gi, (_, text, url) => `<a href="${MarkdownEngine.escape(url).replace(/"/g, '&quot;')}">${text}</a>`);

        // Markdown Tables
        html = html.replace(/((?:\|[^\n]+\|(?:\r?\n|$))+)/g, (tableBlock) => {
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
        html = html.replace(/^###### (.*$)/gim, '<h6>$1</h6>');
        html = html.replace(/^##### (.*$)/gim, '<h5>$1</h5>');
        html = html.replace(/^#### (.*$)/gim, '<h4>$1</h4>');
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

        // Figure and Figcaption to Markdown
        md = md.replace(/<figure\b[^>]*>([\s\S]*?)<\/figure>/gi, (_, figureContent) => {
            let imgMd = '';
            const imgMatch = figureContent.match(/<img\b[^>]*>/i);
            if (imgMatch) {
                const url = imgMatch[0].match(/\bsrc="([^"]*)"/i)?.[1] || '';
                const alt = imgMatch[0].match(/\balt="([^"]*)"/i)?.[1] || '';
                imgMd = `![${alt}](${url})`;
            }
            const capMatch = figureContent.match(/<figcaption\b[^>]*>([\s\S]*?)<\/figcaption>/i);
            const caption = capMatch ? capMatch[1].replace(/<[^>]+>/g, '').trim() : '';
            if (caption && caption !== 'Tambahkan caption gambar...' && caption !== 'Tambah caption...') {
                return (imgMd ? `${imgMd}\n*${caption}*` : `*${caption}*`) + '\n\n';
            }
            return (imgMd || '') + '\n\n';
        });

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
                const cells = cellMatches.map(c => {
                    let inner = c.replace(/^<(?:th|td)[^>]*>/i, '').replace(/<\/(?:th|td)>$/i, '');
                    // Convert images to markdown syntax if not already converted
                    inner = inner.replace(/<img\b[^>]*src="([^"]*)"[^>]*alt="([^"]*)"[^>]*>/gi, '![$2]($1)');
                    inner = inner.replace(/<img\b[^>]*alt="([^"]*)"[^>]*src="([^"]*)"[^>]*>/gi, '![$1]($2)');
                    inner = inner.replace(/<img\b[^>]*src="([^"]*)"[^>]*>/gi, '![]($1)');
                    inner = inner.replace(/<br\s*\/?>/gi, ' ');
                    inner = inner.replace(/<[^>]+>/g, '').trim();
                    return inner.replace(/\|/g, '&#124;');
                });
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
        return decoder.value !== undefined ? decoder.value : (decoder.innerHTML || md);
    }

    static escape(str) {
        return str
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }
}

/**
 * Built-in Plain Text Engine for converting HTML to pure unformatted plain text and vice versa
 */
class PlainTextEngine {
    static toPlainText(html) {
        if (!html || typeof html !== 'string') return '';

        let text = html;

        // Strip scripts, styles, comments
        text = text.replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '');
        text = text.replace(/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/gi, '');
        text = text.replace(/<!--[\s\S]*?-->/g, '');

        // Handle line breaks
        text = text.replace(/<br\s*[\/]?>/gi, '\n');

        // Handle task list checkboxes
        text = text.replace(/\s*<li[^>]*>\s*(?:<label[^>]*>\s*)?<input\b[^>]*?\btype=["']checkbox["'][^>]*?\bchecked\b[^>]*>\s*/gi, '\n[x] ');
        text = text.replace(/\s*<li[^>]*>\s*(?:<label[^>]*>\s*)?<input\b[^>]*?\bchecked\b[^>]*?\btype=["']checkbox["'][^>]*>\s*/gi, '\n[x] ');
        text = text.replace(/\s*<li[^>]*>\s*(?:<label[^>]*>\s*)?<input\b[^>]*?\btype=["']checkbox["'][^>]*>\s*/gi, '\n[ ] ');
        text = text.replace(/<input\b[^>]*?\btype=["']checkbox["'][^>]*?\bchecked\b[^>]*>/gi, '[x] ');
        text = text.replace(/<input\b[^>]*?\bchecked\b[^>]*?\btype=["']checkbox["'][^>]*>/gi, '[x] ');
        text = text.replace(/<input\b[^>]*?\btype=["']checkbox["'][^>]*>/gi, '[ ] ');

        // Handle ordered lists
        text = text.replace(/<ol[^>]*>([\s\S]*?)<\/ol>/gi, (_, items) => {
            let index = 1;
            return items.replace(/\s*<li[^>]*>([\s\S]*?)<\/li>\s*/gi, (_, content) => `${index++}. ${content.trim()}\n`);
        });

        // Handle unordered lists
        text = text.replace(/\s*<li[^>]*>/gi, '\n• ');
        text = text.replace(/<\/li>\s*/gi, '\n');

        // Handle table cells and rows
        text = text.replace(/<\/(td|th)>\s*<(td|th)[^>]*>/gi, '\t');
        text = text.replace(/<tr[^>]*>\s*/gi, '');
        text = text.replace(/<\/tr>\s*/gi, '\n');
        text = text.replace(/<\/?(thead|tbody|tfoot)[^>]*>\s*/gi, '');

        // Handle block elements
        text = text.replace(/<\/(p|div|h[1-6]|blockquote|pre|table|figure|figcaption)>/gi, '\n\n');
        text = text.replace(/Tambahkan caption gambar\.\.\./g, '');
        text = text.replace(/Tambah caption\.\.\./g, '');

        // Replace horizontal rules
        text = text.replace(/<hr\s*[\/]?>/gi, '\n---\n');

        // Strip remaining tags
        text = text.replace(/<[^>]+>/g, '');

        // Decode HTML entities
        text = text
            .replace(/&nbsp;/gi, ' ')
            .replace(/&amp;/gi, '&')
            .replace(/&lt;/gi, '<')
            .replace(/&gt;/gi, '>')
            .replace(/&quot;/gi, '"')
            .replace(/&#39;/gi, "'")
            .replace(/&apos;/gi, "'")
            .replace(/&#(\d+);/g, (_, dec) => String.fromCharCode(dec))
            .replace(/&#x([0-9a-f]+);/gi, (_, hex) => String.fromCharCode(parseInt(hex, 16)));

        // Normalize carriage returns and excessive newlines
        text = text.replace(/\r\n/g, '\n').replace(/\r/g, '\n');
        text = text.replace(/\n{3,}/g, '\n\n');

        return text.trim();
    }

    static toHtml(text) {
        if (!text || typeof text !== 'string' || !text.trim()) {
            return '<p><br></p>';
        }

        const paragraphs = text.split(/\r?\n\r?\n+/);
        return paragraphs.map(para => {
            const trimmed = para.trim();
            if (!trimmed) return '<p><br></p>';
            const escaped = trimmed
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
            const withBreaks = escaped.replace(/\r?\n/g, '<br>');
            return `<p>${withBreaks}</p>`;
        }).join('');
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
        this.dropdown.className = 'retro-select-dropdown hidden fixed z-[99999]';
        this.dropdown.innerHTML = `
            <div class="retro-select-search-box">
                <div class="relative flex items-center">
                    <i class="fa-solid fa-magnifying-glass absolute left-2 text-[10px] text-slate-500 dark:text-slate-400"></i>
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

        const mountTarget = (typeof document !== 'undefined' && document.body) ? document.body : this.container;
        mountTarget.appendChild(this.dropdown);

        this.refreshOptions();
        this.bindEvents();

        this.observer = new MutationObserver(() => this.refreshOptions());
        this.observer.observe(this.select, { childList: true, subtree: true, attributes: true, attributeFilter: ['disabled', 'label', 'selected'] });

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
            if (opt.disabled) return;
            const text = opt.textContent;
            const matches = !query || text.toLowerCase().includes(query);
            if (!matches) return;

            const isSelected = opt.value === this.select.value;
            const item = document.createElement('div');
            item.className = `retro-select-option-item ${isSelected ? 'selected' : ''}`;
            item.dataset.value = opt.value;
            item.innerHTML = `
                <span class="truncate">${this.escapeHtml(text)}</span>
                ${isSelected ? '<i class="fa-solid fa-check text-[10px] text-blue-600 dark:text-cyan-400"></i>' : ''}
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
        if (this.select.disabled || !Array.from(this.select.options).some(option => option.value === value && !option.disabled)) return;
        if (this.select.value !== value) {
            this.select.value = value;
            this.select.dispatchEvent(new Event('change', { bubbles: true }));
        }
        this.syncFromNative();
        this.close();
        this.trigger?.focus?.();
    }

    syncFromNative() {
        const selectedOption = Array.from(this.select.options).find(option => option.value === this.select.value) || this.select.options[this.select.selectedIndex];
        this.trigger.disabled = Boolean(this.select.disabled);
        this.trigger.setAttribute?.('aria-expanded', String(this.isOpen));
        this.trigger.setAttribute?.('aria-haspopup', 'listbox');
        if (this.labelEl && selectedOption) {
            this.labelEl.textContent = selectedOption.textContent;
        }
        if (this.isOpen) {
            this.renderOptions(this.searchInput?.value || '');
        }
    }

    open() {
        if (this.select.disabled) return;
        if (RetroSearchableSelect.activeInstance && RetroSearchableSelect.activeInstance !== this) {
            RetroSearchableSelect.activeInstance.close();
        }
        RetroSearchableSelect.activeInstance = this;
        document.querySelectorAll('.retro-select-dropdown:not(.hidden)').forEach(d => {
            if (d !== this.dropdown) d.classList.add('hidden');
        });

        this.isOpen = true;
        this.trigger.setAttribute?.('aria-expanded', 'true');
        this.dropdown.classList.remove('hidden');
        this.chevronEl?.classList?.add?.('rotate-180');
        if (this.searchInput) {
            this.searchInput.value = '';
        }
        this.renderOptions('');
        this.updatePosition();

        if (!this._boundUpdatePosition) {
            this._boundUpdatePosition = () => {
                if (this.isOpen) {
                    this.updatePosition();
                }
            };
        }
        if (typeof window !== 'undefined' && window.addEventListener) {
            window.addEventListener('scroll', this._boundUpdatePosition, true);
            window.addEventListener('resize', this._boundUpdatePosition);
        }

        setTimeout(() => { if (this.isOpen) this.searchInput?.focus?.(); }, 50);
    }

    close() {
        this.isOpen = false;
        if (RetroSearchableSelect.activeInstance === this) RetroSearchableSelect.activeInstance = null;
        this.trigger.setAttribute?.('aria-expanded', 'false');
        this.dropdown.classList.add('hidden');
        this.chevronEl?.classList?.remove?.('rotate-180');
        this.highlightIndex = -1;

        if (this._boundUpdatePosition && typeof window !== 'undefined' && window.removeEventListener) {
            window.removeEventListener('scroll', this._boundUpdatePosition, true);
            window.removeEventListener('resize', this._boundUpdatePosition);
        }
    }

    updatePosition() {
        if (!this.isOpen || !this.trigger || !this.dropdown) return;
        if (!this.trigger.getBoundingClientRect) return;

        const rect = this.trigger.getBoundingClientRect();
        if (!rect) return;

        const viewportWidth = (typeof window !== 'undefined' && window.innerWidth) ? window.innerWidth : 1024;
        const viewportHeight = (typeof window !== 'undefined' && window.innerHeight) ? window.innerHeight : 768;

        const isFullWidth = Boolean(this.select?.className && this.select.className.includes('w-full'));
        const triggerWidth = rect.width || 120;
        const minW = isFullWidth ? triggerWidth : Math.max(triggerWidth, 190);

        if (this.dropdown.style) {
            this.dropdown.style.position = 'fixed';
            this.dropdown.style.zIndex = '99999';

            if (isFullWidth) {
                this.dropdown.style.width = `${triggerWidth}px`;
                this.dropdown.style.minWidth = `${triggerWidth}px`;
            } else {
                this.dropdown.style.width = 'auto';
                this.dropdown.style.minWidth = `${Math.min(minW, viewportWidth - 24)}px`;
            }

            const dropdownHeight = this.dropdown.offsetHeight || 220;
            const spaceBelow = viewportHeight - (rect.bottom || 0);
            const spaceAbove = rect.top || 0;

            if (spaceBelow < dropdownHeight && spaceAbove > spaceBelow) {
                this.dropdown.style.top = 'auto';
                this.dropdown.style.bottom = `${Math.max(8, viewportHeight - (rect.top || 0) + 4)}px`;
            } else {
                this.dropdown.style.top = `${(rect.bottom || 0) + 4}px`;
                this.dropdown.style.bottom = 'auto';
            }

            let left = rect.left || 0;
            const targetWidth = this.dropdown.offsetWidth || minW;
            if (left + targetWidth > viewportWidth - 12) {
                left = Math.max(12, (rect.right || left) - targetWidth);
            }
            if (left < 12) {
                left = 12;
            }

            this.dropdown.style.left = `${left}px`;
            this.dropdown.style.right = 'auto';
        }
    }

    toggle() {
        if (this.isOpen) this.close();
        else this.open();
    }

    bindEvents() {
        this.trigger.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.toggle();
        });

        this.dropdown.addEventListener('click', (e) => {
            e.stopPropagation();
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
                e.preventDefault();
                e.stopPropagation();
                this.close();
                this.trigger?.focus?.();
            }
        });

        this.select.addEventListener('change', () => {
            this.syncFromNative();
        });

        document.addEventListener('click', (e) => {
            if (!this.container.contains(e.target) && !this.dropdown.contains(e.target)) {
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

class RetroPasswordToggle {
    /**
     * @param {HTMLButtonElement} button
     */
    constructor(button) {
        if (!button || button.dataset.boundPasswordToggle) return;
        this.button = button;
        this.button.dataset.boundPasswordToggle = 'true';
        this.init();
    }

    init() {
        this.button.addEventListener('click', (e) => {
            e.preventDefault();
            this.toggle();
        });
    }

    toggle() {
        const targetId = this.button.getAttribute('data-target');
        const input = targetId ? document.getElementById(targetId) : this.button.parentElement?.querySelector('input');
        if (!input) return;

        const icon = this.button.querySelector('.toggle-password-icon') || this.button.querySelector('i');
        const isPassword = input.type === 'password';

        if (isPassword) {
            input.type = 'text';
            this.button.setAttribute('data-state', 'open');
            this.button.setAttribute('title', 'Sembunyikan kata sandi (Close password)');
            this.button.setAttribute('aria-label', 'Sembunyikan kata sandi');
            if (icon) {
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            }
        } else {
            input.type = 'password';
            this.button.setAttribute('data-state', 'closed');
            this.button.setAttribute('title', 'Tampilkan kata sandi (Show password)');
            this.button.setAttribute('aria-label', 'Tampilkan kata sandi');
            if (icon) {
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        if (typeof window !== 'undefined' && window.notenux && window.notenux.audio) {
            window.notenux.audio.playClick();
        }
    }

    static initAll(root = document) {
        if (!root || !root.querySelectorAll) return;
        root.querySelectorAll('.toggle-password-btn, .password-toggle-btn').forEach(btn => {
            new RetroPasswordToggle(btn);
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
        this.isPlainTextMode = false;
        this.plainTextOriginalText = '';
        this.plainTextOriginalHtml = '';
        this.goalCelebrated = false;
        this.saveQueue = Promise.resolve();
        this.selectionVersion = 0;
        this.dirtyNotes = new Set();
        this.activeTableCell = null;
        this.activeImage = null;
        this.activeImageFigure = null;

        this.undoStack = [];
        this.redoStack = [];
        this.maxHistory = 80;
        this.isPerformingHistory = false;
        this.historyDebounce = null;

        this.isBulkSelectNotes = false;
        this.selectedNoteIds = new Set();

        this.dom = {
            undoBtn: document.getElementById('editor-undo-btn'),
            redoBtn: document.getElementById('editor-redo-btn'),
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
            toggleBulkSelectNotesBtn: document.getElementById('toggle-bulk-select-notes-btn'),
            bulkToggleLabel: document.getElementById('bulk-toggle-label'),
            notesBulkBar: document.getElementById('notes-bulk-bar'),
            bulkSelectAllNotesCb: document.getElementById('bulk-select-all-notes-cb'),
            bulkSelectedNotesCount: document.getElementById('bulk-selected-notes-count'),
            bulkDeleteNotesBtn: document.getElementById('bulk-delete-notes-btn'),
            bulkPinNotesBtn: document.getElementById('bulk-pin-notes-btn'),
            bulkFavNotesBtn: document.getElementById('bulk-fav-notes-btn'),
            bulkMoveCategorySelect: document.getElementById('bulk-move-category-select'),
            bulkCancelNotesBtn: document.getElementById('bulk-cancel-notes-btn'),

            bulkDeleteNotesModal: document.getElementById('bulk-delete-notes-modal'),
            bulkDeleteNotesCountBadge: document.getElementById('bulk-delete-notes-count-badge'),
            closeBulkDeleteNotesX: document.getElementById('close-bulk-delete-notes-x'),
            cancelBulkDeleteNotesBtn: document.getElementById('cancel-bulk-delete-notes-btn'),
            confirmBulkDeleteNotesBtn: document.getElementById('confirm-bulk-delete-notes-btn'),

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
            copyPlainBtn: document.getElementById('copy-plain-btn'),
            copyHtmlBtn: document.getElementById('copy-html-btn'),
            copyJsonBtn: document.getElementById('copy-json-btn'),
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
            mediaFileInput: document.getElementById('media-file-input'),
            customTextColorPicker: document.getElementById('custom-text-color-picker'),
            customHiliteColorPicker: document.getElementById('custom-hilite-color-picker'),
            markdownPasteBtn: document.getElementById('markdown-paste-btn'),
            markdownViewToggleBtn: document.getElementById('markdown-view-toggle-btn'),
            editorToolbarDecks: document.getElementById('editor-toolbar-decks'),
            editorDeck1: document.getElementById('editor-deck-1'),
            editorDeck2: document.getElementById('editor-deck-2'),
            editorDeckOperations: document.getElementById('editor-deck-operations'),
            dynamicIslandContainer: document.getElementById('studio-dynamic-island'),
            dynamicIslandTray: document.getElementById('dynamic-island-tray'),
            dynamicIslandToggleBtn: document.getElementById('dynamic-island-toggle-btn'),
            islandChevronIcon: document.getElementById('island-chevron-icon'),
            islandCloseBtn: document.getElementById('island-close-btn'),
            islandChipsTrack: document.getElementById('island-chips-track'),
            islandScrollLeftBtn: document.getElementById('island-scroll-left-btn'),
            islandScrollRightBtn: document.getElementById('island-scroll-right-btn'),
            editorCanvasCard: document.getElementById('editor-canvas-card'),
            plainTextToggleBtn: document.getElementById('plain-text-toggle-btn'),
            plainTextContainer: document.getElementById('plain-text-container'),
            plainTextEditor: document.getElementById('plain-text-editor'),
            plainTextCopyBtn: document.getElementById('plain-text-copy-btn'),
            plainTextClearBtn: document.getElementById('plain-text-clear-btn'),
            plainTextExitBtn: document.getElementById('plain-text-exit-btn'),
            plainTextCharCount: document.getElementById('plain-text-char-count'),
            plainTextWordCount: document.getElementById('plain-text-word-count'),
            plainTextLineCount: document.getElementById('plain-text-line-count'),
            notepadModeBanner: document.getElementById('notepad-mode-banner'),

            tableToolkitBar: document.getElementById('table-toolkit-bar'),
            tableCellCoord: document.getElementById('table-cell-coord'),
            tableAddRowAboveBtn: document.getElementById('table-add-row-above-btn'),
            tableAddRowBelowBtn: document.getElementById('table-add-row-below-btn'),
            tableDelRowBtn: document.getElementById('table-del-row-btn'),
            tableAddColLeftBtn: document.getElementById('table-add-col-left-btn'),
            tableAddColRightBtn: document.getElementById('table-add-col-right-btn'),
            tableDelColBtn: document.getElementById('table-del-col-btn'),
            tableToggleHeaderBtn: document.getElementById('table-toggle-header-btn'),
            tableAlignLeftBtn: document.getElementById('table-align-left-btn'),
            tableAlignCenterBtn: document.getElementById('table-align-center-btn'),
            tableAlignRightBtn: document.getElementById('table-align-right-btn'),
            tableCellBgPicker: document.getElementById('table-cell-bg-picker'),
            tableCellTextPicker: document.getElementById('table-cell-text-picker'),
            tableClearColorsBtn: document.getElementById('table-clear-colors-btn'),
            tableInsertImgBtn: document.getElementById('table-insert-img-btn'),
            tableCellImgInput: document.getElementById('table-cell-img-input'),
            tableDeleteTableBtn: document.getElementById('table-delete-table-btn'),
            tableCloseToolkitBtn: document.getElementById('table-close-toolkit-btn'),

            imageToolkitBar: document.getElementById('image-toolkit-bar'),
            imageAlignBadge: document.getElementById('image-align-badge'),
            imgAlignLeftBtn: document.getElementById('img-align-left-btn'),
            imgAlignCenterBtn: document.getElementById('img-align-center-btn'),
            imgAlignRightBtn: document.getElementById('img-align-right-btn'),
            imgCaptionBtn: document.getElementById('img-caption-btn'),
            imgDeleteBtn: document.getElementById('img-delete-btn'),
            closeImageBarBtn: document.getElementById('close-image-bar-btn'),

            questGoalText: document.getElementById('quest-goal-text'),
            questGoalSelect: document.getElementById('quest-goal-select'),

            openProfileModalBtn: document.getElementById('open-profile-modal-btn'),
            sidebarAvatarProfileBtn: document.getElementById('sidebar-avatar-profile-btn'),
            profileSettingsModal: document.getElementById('profile-settings-modal'),
            closeProfileModalX: document.getElementById('close-profile-modal-x'),
            closeProfileModalBtn: document.getElementById('close-profile-modal-btn'),
            saveProfileBtn: document.getElementById('save-profile-btn'),
            profileSettingsForm: document.getElementById('profile-settings-form'),
            profileFormError: document.getElementById('profile-form-error'),
            topNavUsername: document.getElementById('top-nav-username'),

            playerLevelBadge: document.getElementById('player-level-badge'),
            playerRankTitle: document.getElementById('player-rank-title'),
            playerXpText: document.getElementById('player-xp-text'),
            playerXpBar: document.getElementById('player-xp-bar'),
            playerManaText: document.getElementById('player-mana-text'),
            wordCountBadge: document.getElementById('word-count-badge'),
            charCountBadge: document.getElementById('char-count-badge'),
            readingTimeBadge: document.getElementById('reading-time-badge'),
            chunkCountBadge: document.getElementById('chunk-count-badge'),
            scrollCreatedBadge: document.getElementById('scroll-created-badge'),
            scrollCreatedText: document.getElementById('scroll-created-text'),
            scrollUpdatedBadge: document.getElementById('scroll-updated-badge'),
            scrollUpdatedText: document.getElementById('scroll-updated-text'),

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

            markdownModal: document.getElementById('markdown-modal'),
            closeMarkdownModalBtn: document.getElementById('close-markdown-modal-btn'),
            cancelMarkdownModalBtn: document.getElementById('cancel-markdown-modal-btn'),
            confirmMarkdownModalBtn: document.getElementById('confirm-markdown-modal-btn'),
            markdownModalTextarea: document.getElementById('markdown-modal-textarea'),
            markdownModalPreview: document.getElementById('markdown-modal-preview'),
            mdInsertSampleBtn: document.getElementById('md-insert-sample-btn'),
            mdTabSplitBtn: document.getElementById('md-tab-split-btn'),
            mdTabEditorBtn: document.getElementById('md-tab-editor-btn'),
            mdTabPreviewBtn: document.getElementById('md-tab-preview-btn'),
            mdModalEditorPane: document.getElementById('md-modal-editor-pane'),
            mdModalPreviewPane: document.getElementById('md-modal-preview-pane'),
            mdModalGrid: document.getElementById('md-modal-grid'),
            mdModalWordCount: document.getElementById('md-modal-word-count'),
            mdModalCharCount: document.getElementById('md-modal-char-count'),

            clearEditorBtn: document.getElementById('clear-editor-btn'),
            clearConfirmModal: document.getElementById('clear-confirm-modal'),
            closeClearModalX: document.getElementById('close-clear-modal-x'),
            cancelClearModalBtn: document.getElementById('cancel-clear-modal-btn'),
            clearFormattingOnlyBtn: document.getElementById('clear-formatting-only-btn'),
            clearAllContentBtn: document.getElementById('clear-all-content-btn'),

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
        this.initDynamicIsland();
        this.initPasteInterceptor();
        this.initTableToolkit();
        this.initImageToolkit();
        this.initGoalTracking();
        this.initFindReplace();
        this.initImportModal();
        this.initShortcutsModal();
        this.initMarkdownModal();
        this.initClearControls();
        this.bindEvents();
        this.initPasswordToggles();
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

    /* ---------------- DYNAMIC ISLAND ENGINE ---------------- */
    initDynamicIsland() {
        if (!this.dom.dynamicIslandToggleBtn || !this.dom.dynamicIslandTray) return;

        this.dom.dynamicIslandToggleBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.toggleDynamicIsland();
        });

        if (this.dom.islandCloseBtn) {
            this.dom.islandCloseBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.closeDynamicIsland();
            });
        }

        // Horizontal mouse wheel scrolling over tools and chips track ("munculnya digulir, dan itu interaktif")
        const scrollableElements = [
            this.dom.editorDeck1,
            this.dom.editorDeck2,
            this.dom.islandChipsTrack,
            this.dom.editorDeckOperations,
        ];
        scrollableElements.forEach(el => {
            if (!el) return;
            el.addEventListener('wheel', (e) => {
                if (e.deltaY !== 0 && !e.shiftKey) {
                    e.preventDefault();
                    el.scrollLeft += e.deltaY;
                }
            }, { passive: false });
        });

        // Horizontal scroll track buttons
        if (this.dom.islandScrollLeftBtn) {
            this.dom.islandScrollLeftBtn.addEventListener('click', () => {
                if (this.dom.islandChipsTrack) this.dom.islandChipsTrack.scrollBy({ left: -160, behavior: 'smooth' });
                if (this.dom.editorDeck1) this.dom.editorDeck1.scrollBy({ left: -160, behavior: 'smooth' });
                if (this.dom.editorDeck2) this.dom.editorDeck2.scrollBy({ left: -160, behavior: 'smooth' });
                if (this.dom.editorDeckOperations) this.dom.editorDeckOperations.scrollBy({ left: -160, behavior: 'smooth' });
            });
        }

        if (this.dom.islandScrollRightBtn) {
            this.dom.islandScrollRightBtn.addEventListener('click', () => {
                if (this.dom.islandChipsTrack) this.dom.islandChipsTrack.scrollBy({ left: 160, behavior: 'smooth' });
                if (this.dom.editorDeck1) this.dom.editorDeck1.scrollBy({ left: 160, behavior: 'smooth' });
                if (this.dom.editorDeck2) this.dom.editorDeck2.scrollBy({ left: 160, behavior: 'smooth' });
                if (this.dom.editorDeckOperations) this.dom.editorDeckOperations.scrollBy({ left: 160, behavior: 'smooth' });
            });
        }

        // Chip navigation for quick section scrolling
        const chips = document.querySelectorAll('.island-chip-btn');
        chips.forEach(chip => {
            chip.addEventListener('click', () => {
                chips.forEach(c => c.classList.remove('active'));
                chip.classList.add('active');
                const section = chip.getAttribute('data-island-section');
                if (section === 'formatting' && this.dom.editorDeck1) {
                    this.dom.editorDeck1.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'start' });
                } else if (section === 'inserts') {
                    const inserts = document.getElementById('editor-deck-2-inserts');
                    if (inserts) {
                        inserts.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'start' });
                    } else if (this.dom.editorDeck2) {
                        this.dom.editorDeck2.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'start' });
                    }
                } else if (section === 'studio') {
                    const palette = document.getElementById('editor-deck-2-palette');
                    if (palette) {
                        palette.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'start' });
                    } else if (this.dom.editorDeck2) {
                        this.dom.editorDeck2.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'start' });
                    }
                } else if (section === 'operations' && this.dom.editorDeckOperations) {
                    this.dom.editorDeckOperations.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'start' });
                }
            });
        });
    }

    openDynamicIsland() {
        if (!this.dom.dynamicIslandTray) return;
        this.dom.dynamicIslandTray.classList.remove('collapsed');
        if (this.dom.dynamicIslandToggleBtn) {
            this.dom.dynamicIslandToggleBtn.setAttribute('aria-expanded', 'true');
        }
        if (this.dom.islandChevronIcon) {
            this.dom.islandChevronIcon.classList.add('rotate-180');
        }
        if (this.dom.dynamicIslandContainer) {
            this.dom.dynamicIslandContainer.classList.add('island-expanded');
        }
    }

    closeDynamicIsland() {
        if (!this.dom.dynamicIslandTray) return;
        this.dom.dynamicIslandTray.classList.add('collapsed');
        if (this.dom.dynamicIslandToggleBtn) {
            this.dom.dynamicIslandToggleBtn.setAttribute('aria-expanded', 'false');
        }
        if (this.dom.islandChevronIcon) {
            this.dom.islandChevronIcon.classList.remove('rotate-180');
        }
        if (this.dom.dynamicIslandContainer) {
            this.dom.dynamicIslandContainer.classList.remove('island-expanded');
        }
    }

    toggleDynamicIsland() {
        if (!this.dom.dynamicIslandTray) return;
        if (this.dom.dynamicIslandTray.classList.contains('collapsed')) {
            this.openDynamicIsland();
        } else {
            this.closeDynamicIsland();
        }
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
        this.updateFavicon(theme);

        if (notify) {
            this.toast.show(
                theme === 'dark' ? 'Retro Night Dark Theme engaged' : 'Arcade Day Light Theme engaged',
                'info',
                'Theme Switched',
                theme === 'dark' ? 'fa-solid fa-moon text-cyan-400' : 'fa-solid fa-sun text-amber-400'
            );
        }
    }

    updateFavicon(theme) {
        const favicon = document.getElementById('app-favicon') || document.querySelector('link[rel="icon"]');
        const appleIcon = document.getElementById('app-apple-touch-icon') || document.querySelector('link[rel="apple-touch-icon"]');
        const lightFaviconUrl = window.notenuxFaviconLight || '/assets/logo/light/icon_light.png';
        const darkFaviconUrl = window.notenuxFaviconDark || '/assets/logo/dark/icon_dark.png';
        const targetFavicon = theme === 'dark' ? darkFaviconUrl : lightFaviconUrl;
        if (favicon) {
            favicon.setAttribute('href', targetFavicon);
        }
        if (appleIcon) {
            appleIcon.setAttribute('href', targetFavicon);
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
                const category = this.dom.importCategorySelect?.value || 'catatan';

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

    /* ---------------- MARKDOWN MODAL & LIVE PREVIEW ---------------- */
    initMarkdownModal() {
        if (!this.dom.markdownModal) return;

        if (this.dom.markdownPasteBtn) {
            this.dom.markdownPasteBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.openMarkdownModal();
            });
        }

        const closeModal = () => this.closeMarkdownModal();
        if (this.dom.closeMarkdownModalBtn) this.dom.closeMarkdownModalBtn.addEventListener('click', closeModal);
        if (this.dom.cancelMarkdownModalBtn) this.dom.cancelMarkdownModalBtn.addEventListener('click', closeModal);
        this.dom.markdownModal.addEventListener('click', (e) => {
            if (e.target === this.dom.markdownModal) closeModal();
        });

        if (this.dom.markdownModalTextarea) {
            this.dom.markdownModalTextarea.addEventListener('input', () => {
                this.renderMarkdownModalPreview();
            });
        }

        if (this.dom.mdInsertSampleBtn && this.dom.markdownModalTextarea) {
            this.dom.mdInsertSampleBtn.addEventListener('click', () => {
                this.dom.markdownModalTextarea.value = `# 📜 Arcane Manuscript Outline\n\nMenulis catatan dengan format **Markdown** instan:\n\n- [x] Susun rencana utama\n- [x] Inisialisasi cryptographic chunk matrix\n- [ ] Publikasikan dokumen\n\n### Formula Singkat\n\`\`\`javascript\nconst power = (intellect) => Math.pow(intellect, 2);\n\`\`\`\n\n| Item | Kategori | Level |\n| --- | --- | --- |\n| Wand | Arcane | 10 |\n| Shield | Defense | 8 |`;
                this.renderMarkdownModalPreview();
            });
        }

        const setViewMode = (mode) => {
            if (!this.dom.mdModalGrid || !this.dom.mdModalEditorPane || !this.dom.mdModalPreviewPane) return;
            const splitBtn = this.dom.mdTabSplitBtn;
            const editorBtn = this.dom.mdTabEditorBtn;
            const previewBtn = this.dom.mdTabPreviewBtn;

            [splitBtn, editorBtn, previewBtn].forEach(b => {
                if (b) {
                    b.classList.remove('bg-cyan-600', 'text-white', 'font-bold');
                    b.classList.add('bg-surface');
                }
            });

            if (mode === 'split') {
                if (splitBtn) {
                    splitBtn.classList.add('bg-cyan-600', 'text-white', 'font-bold');
                    splitBtn.classList.remove('bg-surface');
                }
                this.dom.mdModalGrid.className = 'p-4 overflow-y-auto flex-1 grid grid-cols-1 md:grid-cols-2 gap-4 text-xs font-mono';
                this.dom.mdModalEditorPane.classList.remove('hidden');
                this.dom.mdModalPreviewPane.classList.remove('hidden');
            } else if (mode === 'editor') {
                if (editorBtn) {
                    editorBtn.classList.add('bg-cyan-600', 'text-white', 'font-bold');
                    editorBtn.classList.remove('bg-surface');
                }
                this.dom.mdModalGrid.className = 'p-4 overflow-y-auto flex-1 flex flex-col text-xs font-mono';
                this.dom.mdModalEditorPane.classList.remove('hidden');
                this.dom.mdModalPreviewPane.classList.add('hidden');
            } else if (mode === 'preview') {
                if (previewBtn) {
                    previewBtn.classList.add('bg-cyan-600', 'text-white', 'font-bold');
                    previewBtn.classList.remove('bg-surface');
                }
                this.dom.mdModalGrid.className = 'p-4 overflow-y-auto flex-1 flex flex-col text-xs font-mono';
                this.dom.mdModalEditorPane.classList.add('hidden');
                this.dom.mdModalPreviewPane.classList.remove('hidden');
            }
        };

        if (this.dom.mdTabSplitBtn) this.dom.mdTabSplitBtn.addEventListener('click', () => setViewMode('split'));
        if (this.dom.mdTabEditorBtn) this.dom.mdTabEditorBtn.addEventListener('click', () => setViewMode('editor'));
        if (this.dom.mdTabPreviewBtn) this.dom.mdTabPreviewBtn.addEventListener('click', () => setViewMode('preview'));

        if (this.dom.confirmMarkdownModalBtn) {
            this.dom.confirmMarkdownModalBtn.addEventListener('click', () => {
                const mdText = this.dom.markdownModalTextarea?.value || '';
                if (!mdText.trim()) {
                    this.toast.show('Silakan ketik atau tempel teks Markdown terlebih dahulu.', 'warning');
                    return;
                }

                const html = MarkdownEngine.parse(mdText);
                const modeInput = document.querySelector('input[name="md_apply_mode"]:checked');
                const mode = modeInput ? modeInput.value : 'cursor';

                if (mode === 'replace' && this.dom.editorArea) {
                    this.dom.editorArea.innerHTML = html;
                } else if (this.dom.editorArea) {
                    this.dom.editorArea.focus();
                    const sel = window.getSelection();
                    if (sel && sel.rangeCount > 0 && this.dom.editorArea.contains(sel.anchorNode)) {
                        document.execCommand('insertHTML', false, html);
                    } else {
                        this.dom.editorArea.innerHTML += html;
                    }
                }

                this.handleEditorInput();
                this.closeMarkdownModal();
                this.toast.show('Markdown berhasil dikonversi dan diterapkan ke catatan!', 'success', 'Markdown Applied', 'fa-solid fa-file-code text-cyan-400');
            });
        }
    }

    openMarkdownModal() {
        if (!this.activeNote) {
            this.toast.show('Pilih atau buat gulungan catatan terlebih dahulu.', 'warning');
            return;
        }
        if (!this.dom.markdownModal) return;
        this.audio.playClick();
        this.dom.markdownModal.classList.remove('hidden');
        if (this.dom.markdownModalTextarea) {
            this.dom.markdownModalTextarea.focus();
            this.renderMarkdownModalPreview();
        }
    }

    closeMarkdownModal() {
        if (!this.dom.markdownModal) return;
        this.audio.playBlip();
        this.dom.markdownModal.classList.add('hidden');
    }

    renderMarkdownModalPreview() {
        if (!this.dom.markdownModalTextarea || !this.dom.markdownModalPreview) return;
        const text = this.dom.markdownModalTextarea.value || '';
        const words = text.trim() ? text.trim().split(/\s+/).length : 0;
        const chars = text.length;

        if (this.dom.mdModalWordCount) this.dom.mdModalWordCount.textContent = `${words} kata`;
        if (this.dom.mdModalCharCount) this.dom.mdModalCharCount.textContent = `${chars} karakter`;

        if (!text.trim()) {
            this.dom.markdownModalPreview.innerHTML = '<p class="text-slate-400 italic">Preview hasil terjemahan Markdown akan otomatis muncul di sini...</p>';
            return;
        }

        const html = MarkdownEngine.parse(text);
        this.dom.markdownModalPreview.innerHTML = html;
    }

    /* ---------------- CLEAR CONTROLS & CONFIRMATION MODAL ---------------- */
    initClearControls() {
        if (this.dom.clearEditorBtn) {
            this.dom.clearEditorBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.audio.playClick();

                const selection = window.getSelection();
                const hasSelection = selection && !selection.isCollapsed && selection.toString().trim().length > 0;

                if (hasSelection && this.dom.editorArea?.contains(selection.anchorNode)) {
                    const plain = selection.toString();
                    document.execCommand('insertText', false, plain);
                    this.handleEditorInput();
                    this.toast.show('Format teks terseleksi berhasil dibersihkan!', 'success', 'Format Cleared', 'fa-solid fa-broom text-amber-400');
                } else {
                    this.openClearModal();
                }
            });
        }

        const closeClearModal = () => this.closeClearModal();
        if (this.dom.closeClearModalX) this.dom.closeClearModalX.addEventListener('click', closeClearModal);
        if (this.dom.cancelClearModalBtn) this.dom.cancelClearModalBtn.addEventListener('click', closeClearModal);
        if (this.dom.clearConfirmModal) {
            this.dom.clearConfirmModal.addEventListener('click', (e) => {
                if (e.target === this.dom.clearConfirmModal) closeClearModal();
            });
        }

        if (this.dom.clearFormattingOnlyBtn) {
            this.dom.clearFormattingOnlyBtn.addEventListener('click', () => {
                if (!this.dom.editorArea) return;
                this.audio.playWarp();
                const plainText = this.dom.editorArea.innerText || '';
                const paragraphs = plainText.split(/\n\n+/).filter(Boolean);
                this.dom.editorArea.innerHTML = paragraphs.map(p => `<p>${this.escapeHtml(p.trim())}</p>`).join('') || '<p><br></p>';
                this.handleEditorInput();
                this.closeClearModal();
                this.toast.show('Semua format teks berhasil dinormalisasi menjadi teks polos.', 'success', 'Format Cleared', 'fa-solid fa-broom text-amber-400');
            });
        }

        if (this.dom.clearAllContentBtn) {
            this.dom.clearAllContentBtn.addEventListener('click', () => {
                if (!this.dom.editorArea) return;
                this.audio.playBlip();
                this.dom.editorArea.innerHTML = '<p><br></p>';
                this.handleEditorInput();
                this.closeClearModal();
                this.toast.show('Isi catatan berhasil dikosongkan.', 'info', 'Editor Cleared', 'fa-solid fa-trash-can text-rose-400');
            });
        }
    }

    openClearModal() {
        if (!this.dom.clearConfirmModal) return;
        this.dom.clearConfirmModal.classList.remove('hidden');
    }

    closeClearModal() {
        if (!this.dom.clearConfirmModal) return;
        this.dom.clearConfirmModal.classList.add('hidden');
    }

    /* ---------------- PASSWORD VISIBILITY TOGGLE ---------------- */
    initPasswordToggles() {
        RetroPasswordToggle.initAll();
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
            if (this.isPlainTextMode) this.togglePlainTextMode(false);
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
        if (!this.dom.editorArea || typeof this.dom.editorArea.querySelectorAll !== 'function') return;
        const marks = this.dom.editorArea.querySelectorAll('mark.search-match');
        marks.forEach(m => {
            const parent = m.parentNode;
            while (m.firstChild) {
                parent.insertBefore(m.firstChild, m);
            }
            parent.removeChild(m);
        });
        this.dom.editorArea.normalize?.();
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
                    ? '<i class="fa-solid fa-volume-high text-blue-600 dark:text-cyan-400 mr-1.5"></i> <span class="hidden sm:inline">SFX: ON</span>'
                    : '<i class="fa-solid fa-volume-xmark text-gray-500 dark:text-gray-400 mr-1.5"></i> <span class="hidden sm:inline">SFX: OFF</span>';
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
            const isForeignInput = ['INPUT', 'TEXTAREA'].includes(e.target.tagName) && e.target !== this.dom.editorArea;

            // Undo: Ctrl + Z (without Shift)
            if ((e.ctrlKey || e.metaKey) && !e.shiftKey && e.key.toLowerCase() === 'z') {
                if (!isForeignInput) {
                    e.preventDefault();
                    this.undo();
                    return;
                }
            }

            // Redo: Ctrl + Y or Ctrl + Shift + Z
            if (((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'y') ||
                ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key.toLowerCase() === 'z')) {
                if (!isForeignInput) {
                    e.preventDefault();
                    this.redo();
                    return;
                }
            }

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

            // Toggle Plain Text / Notepad Mode: Alt + N
            if (e.altKey && !e.ctrlKey && !e.metaKey && e.key.toLowerCase() === 'n') {
                e.preventDefault();
                this.togglePlainTextMode();
                return;
            }

            // Toggle Dynamic Island Tools: Alt + T
            if (e.altKey && !e.ctrlKey && !e.metaKey && e.key.toLowerCase() === 't') {
                e.preventDefault();
                this.toggleDynamicIsland();
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
                this.closeBulkDeleteNotesModal();
                this.closeQuickCategoryModals();
                this.closeClearModal();
                this.closeMarkdownModal();
                this.closeChunkInspector();
                this.closeProfileModal();
                this.toggleFindBar(false);
                document.querySelectorAll('.retro-searchable-dropdown').forEach(d => d.classList.add('hidden'));
                if (this.dom.importModal) this.dom.importModal.classList.add('hidden');
                if (this.dom.shortcutsModal) this.dom.shortcutsModal.classList.add('hidden');
                if (this.dom.exportMenu) this.dom.exportMenu.classList.add('hidden');
                if (this.dom.imageToolkitBar) this.dom.imageToolkitBar.classList.add('hidden');
                if (this.dom.tableToolkitBar) this.dom.tableToolkitBar.classList.add('hidden');
                if (this.dom.dynamicIslandTray && !this.dom.dynamicIslandTray.classList.contains('collapsed')) {
                    this.closeDynamicIsland();
                }
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
            this.clearEditorToEmptyState();
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

        // Bulk Select Notes Mode toggles and actions
        this.dom.toggleBulkSelectNotesBtn?.addEventListener('click', () => {
            this.toggleBulkSelectNotesMode();
        });

        this.dom.bulkCancelNotesBtn?.addEventListener('click', () => {
            this.toggleBulkSelectNotesMode(false);
        });

        this.dom.bulkSelectAllNotesCb?.addEventListener('change', (e) => {
            this.selectAllFilteredNotes(e.target.checked);
        });

        this.dom.bulkPinNotesBtn?.addEventListener('click', () => {
            this.executeBulkPinNotes();
        });

        this.dom.bulkFavNotesBtn?.addEventListener('click', () => {
            this.executeBulkFavoriteNotes();
        });

        this.dom.bulkMoveCategorySelect?.addEventListener('change', async (e) => {
            const targetCategory = e.target.value;
            if (!targetCategory) return;
            await this.executeBulkMoveCategory(targetCategory);
            e.target.value = '';
        });

        this.dom.bulkDeleteNotesBtn?.addEventListener('click', () => {
            this.openBulkDeleteNotesModal();
        });

        this.dom.closeBulkDeleteNotesX?.addEventListener('click', () => {
            this.closeBulkDeleteNotesModal();
        });

        this.dom.cancelBulkDeleteNotesBtn?.addEventListener('click', () => {
            this.closeBulkDeleteNotesModal();
        });

        this.dom.confirmBulkDeleteNotesBtn?.addEventListener('click', () => {
            this.executeBulkDeleteNotes();
        });

        this.dom.bulkDeleteNotesModal?.addEventListener('click', (e) => {
            if (e.target === this.dom.bulkDeleteNotesModal) {
                this.closeBulkDeleteNotesModal();
            }
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

        this.dom.editorArea?.addEventListener('click', (e) => {
            if (e.target?.closest?.('#empty-state-new-btn') || e.target?.id === 'empty-state-new-btn') {
                e.preventDefault();
                this.createNewNote(true);
            }
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

        this.dom.plainTextEditor?.addEventListener('input', () => {
            if (!this.activeNote) return;
            const plain = this.dom.plainTextEditor.value;
            const html = this.plainTextToHtml(plain);
            if (this.dom.editorArea) {
                this.dom.editorArea.innerHTML = html;
            }
            this.updatePlainTextStats();
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

        this.dom.copyPlainBtn?.addEventListener('click', () => {
            if (!this.activeNote) {
                this.toast.show('Pilih atau buat gulungan catatan terlebih dahulu.', 'warning');
                return;
            }
            this.dom.exportMenu?.classList.add('hidden');
            this.copyEntirePlainText();
        });

        this.dom.copyMarkdownBtn?.addEventListener('click', async () => {
            if (!this.activeNote) {
                this.toast.show('Pilih atau buat gulungan catatan terlebih dahulu.', 'warning');
                return;
            }
            let md = '';
            if (this.isPlainTextMode && this.dom.plainTextEditor) {
                md = MarkdownEngine.toMarkdown(this.plainTextToHtml(this.dom.plainTextEditor.value));
            } else if (this.isRawMarkdownMode && this.dom.markdownRawEditor) {
                md = this.dom.markdownRawEditor.value;
            } else {
                md = MarkdownEngine.toMarkdown(this.dom.editorArea?.innerHTML || this.activeNote.content || '');
            }
            await this.copyTextToClipboard(md, {
                message: 'Scroll Markdown copied to clipboard!',
                type: 'success',
                title: 'Markdown Copied',
                icon: 'fa-brands fa-markdown text-cyan-400',
            });
            this.dom.exportMenu?.classList.add('hidden');
        });

        this.dom.copyHtmlBtn?.addEventListener('click', async () => {
            if (!this.activeNote) {
                this.toast.show('Pilih atau buat gulungan catatan terlebih dahulu.', 'warning');
                return;
            }
            let html = '';
            if (this.isPlainTextMode && this.dom.plainTextEditor) {
                html = this.plainTextToHtml(this.dom.plainTextEditor.value);
            } else if (this.isRawMarkdownMode && this.dom.markdownRawEditor) {
                html = MarkdownEngine.parse(this.dom.markdownRawEditor.value);
            } else {
                html = this.dom.editorArea?.innerHTML || this.activeNote.content || '';
            }
            await this.copyTextToClipboard(html, {
                message: 'Scroll HTML copied to clipboard!',
                type: 'success',
                title: 'HTML Copied',
                icon: 'fa-solid fa-code text-emerald-400',
            });
            this.dom.exportMenu?.classList.add('hidden');
        });

        this.dom.copyJsonBtn?.addEventListener('click', async () => {
            if (!this.activeNote) {
                this.toast.show('Pilih atau buat gulungan catatan terlebih dahulu.', 'warning');
                return;
            }
            let currentContent = '';
            if (this.isPlainTextMode && this.dom.plainTextEditor) {
                currentContent = this.plainTextToHtml(this.dom.plainTextEditor.value);
            } else if (this.isRawMarkdownMode && this.dom.markdownRawEditor) {
                currentContent = MarkdownEngine.parse(this.dom.markdownRawEditor.value);
            } else {
                currentContent = this.dom.editorArea?.innerHTML || this.activeNote.content || '';
            }
            const data = {
                id: this.activeNote.id,
                uuid: this.activeNote.uuid,
                title: this.activeNote.title,
                category: this.activeNote.category,
                content: currentContent,
                font_family: this.activeNote.font_family,
                font_size: this.activeNote.font_size,
                theme_color: this.activeNote.theme_color,
                word_count: this.activeNote.word_count,
                char_count: this.activeNote.char_count,
                reading_time: this.activeNote.reading_time,
                level: this.activeNote.level,
                xp: this.activeNote.xp,
                is_pinned: Boolean(this.activeNote.is_pinned),
                is_favorite: Boolean(this.activeNote.is_favorite),
                chunks: this.activeNote.chunks || [],
            };
            const jsonText = JSON.stringify(data, null, 2);
            await this.copyTextToClipboard(jsonText, {
                message: 'Scroll JSON copied to clipboard!',
                type: 'success',
                title: 'JSON Copied',
                icon: 'fa-solid fa-cube text-purple-400',
            });
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

        // Hero Profile & Security Modal Triggers
        this.dom.openProfileModalBtn?.addEventListener('click', () => {
            this.openProfileModal();
        });
        this.dom.sidebarAvatarProfileBtn?.addEventListener('click', () => {
            this.openProfileModal();
        });
        this.dom.closeProfileModalX?.addEventListener('click', () => {
            this.closeProfileModal();
        });
        this.dom.closeProfileModalBtn?.addEventListener('click', () => {
            this.closeProfileModal();
        });
        this.dom.saveProfileBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            this.saveProfile();
        });
        this.dom.profileSettingsModal?.addEventListener('click', (e) => {
            if (e.target === this.dom.profileSettingsModal) {
                this.closeProfileModal();
            }
        });

        this.dom.undoBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            if (!this.activeNote) {
                this.toast.show('Pilih atau buat gulungan catatan terlebih dahulu.', 'warning');
                return;
            }
            this.undo();
        });

        this.dom.redoBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            if (!this.activeNote) {
                this.toast.show('Pilih atau buat gulungan catatan terlebih dahulu.', 'warning');
                return;
            }
            this.redo();
        });

        this.dom.insertTableBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            if (!this.activeNote) {
                this.toast.show('Pilih atau buat gulungan catatan terlebih dahulu.', 'warning');
                return;
            }
            this.insertRetroTable();
        });

        this.dom.insertTaskBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            if (!this.activeNote) {
                this.toast.show('Pilih atau buat gulungan catatan terlebih dahulu.', 'warning');
                return;
            }
            this.insertInteractiveTask();
        });

        this.dom.insertMediaBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            if (!this.activeNote) {
                this.toast.show('Pilih atau buat gulungan catatan terlebih dahulu.', 'warning');
                return;
            }
            this.insertMedia();
        });

        this.dom.markdownPasteBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            if (!this.activeNote) {
                this.toast.show('Pilih atau buat gulungan catatan terlebih dahulu.', 'warning');
                return;
            }
            this.promptMarkdownInput();
        });

        this.dom.markdownViewToggleBtn?.addEventListener('click', () => {
            this.toggleMarkdownRawView();
        });

        this.dom.plainTextToggleBtn?.addEventListener('click', () => {
            this.togglePlainTextMode();
        });

        this.dom.plainTextExitBtn?.addEventListener('click', () => {
            this.togglePlainTextMode(false);
        });

        this.dom.plainTextCopyBtn?.addEventListener('click', () => {
            this.copyEntirePlainText();
        });

        this.dom.plainTextClearBtn?.addEventListener('click', () => {
            if (!this.dom.plainTextEditor || !this.dom.plainTextEditor.value.trim()) return;
            this.audio?.playDelete?.();
            this.dom.plainTextEditor.value = '';
            if (this.dom.editorArea) this.dom.editorArea.innerHTML = '<p><br></p>';
            this.updatePlainTextStats();
            this.handleEditorInput();
            this.toast.show('Teks Notepad berhasil dikosongkan.', 'info', 'Notepad Dikosongkan');
        });

        this.dom.plainTextEditor?.addEventListener('keydown', (e) => {
            if (e.key === 'Tab') {
                e.preventDefault();
                const ta = this.dom.plainTextEditor;
                const start = ta.selectionStart || 0;
                const end = ta.selectionEnd || 0;
                if (typeof ta.setRangeText === 'function') {
                    ta.setRangeText('\t', start, end, 'end');
                } else {
                    ta.value = ta.value.substring(0, start) + '\t' + ta.value.substring(end);
                    ta.selectionStart = ta.selectionEnd = start + 1;
                }
                ta.dispatchEvent(new Event('input'));
            }
        });

        this.dom.inspectChunksBtn?.addEventListener('click', () => {
            this.openChunkInspector();
        });
        this.dom.chunkCountBadge?.addEventListener('click', () => this.openChunkInspector());

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
        document.querySelectorAll('[data-command], [data-color], #insert-table-btn, #insert-task-btn, #insert-media-btn, #img-align-left-btn, #img-align-center-btn, #img-align-right-btn, #img-caption-btn, #img-delete-btn').forEach(button => {
            button.addEventListener('mousedown', event => event.preventDefault());
        });
        document.querySelectorAll('[data-command]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                if (!this.activeNote) {
                    this.toast.show('Pilih atau buat gulungan catatan terlebih dahulu.', 'warning');
                    return;
                }
                this.audio.playClick();
                const command = btn.dataset.command;
                const value = btn.dataset.value || null;

                if (command === 'undo') {
                    this.undo();
                    return;
                }
                if (command === 'redo') {
                    this.redo();
                    return;
                }

                if (['justifyLeft', 'justifyCenter', 'justifyRight'].includes(command) && this.activeImage) {
                    const align = command === 'justifyLeft' ? 'left' : (command === 'justifyRight' ? 'right' : 'center');
                    this.alignActiveImage(align);
                    return;
                }

                this.pushUndoSnapshot();

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
                this.pushUndoSnapshot();
            });
        });

        document.querySelectorAll('[data-color]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                if (!this.activeNote) {
                    this.toast.show('Pilih atau buat gulungan catatan terlebih dahulu.', 'warning');
                    return;
                }
                this.audio.playClick();
                this.pushUndoSnapshot();
                const color = btn.dataset.color;
                const type = btn.dataset.colorType || 'foreColor';
                document.execCommand(type, false, color);
                this.dom.editorArea.focus();
                this.handleEditorInput();
                this.pushUndoSnapshot();
            });
        });

        if (this.dom.customTextColorPicker) {
            const applyCustomText = (e) => {
                if (!this.activeNote) {
                    this.toast.show('Pilih atau buat gulungan catatan terlebih dahulu.', 'warning');
                    return;
                }
                this.pushUndoSnapshot();
                document.execCommand('foreColor', false, e.target.value);
                this.dom.editorArea.focus();
                this.handleEditorInput();
                this.pushUndoSnapshot();
            };
            this.dom.customTextColorPicker.addEventListener('input', applyCustomText);
            this.dom.customTextColorPicker.addEventListener('change', applyCustomText);
        }

        if (this.dom.customHiliteColorPicker) {
            const applyCustomHilite = (e) => {
                if (!this.activeNote) {
                    this.toast.show('Pilih atau buat gulungan catatan terlebih dahulu.', 'warning');
                    return;
                }
                this.pushUndoSnapshot();
                document.execCommand('hiliteColor', false, e.target.value);
                this.dom.editorArea.focus();
                this.handleEditorInput();
                this.pushUndoSnapshot();
            };
            this.dom.customHiliteColorPicker.addEventListener('input', applyCustomHilite);
            this.dom.customHiliteColorPicker.addEventListener('change', applyCustomHilite);
        }

        if (this.dom.mediaFileInput) {
            this.dom.mediaFileInput.addEventListener('change', (e) => {
                const file = e.target.files?.[0];
                if (!file) return;
                if (!file.type.startsWith('image/')) {
                    this.toast.show('Hanya file gambar yang didukung.', 'warning');
                    return;
                }
                this.insertImageFile(file);
                e.target.value = '';
            });
        }
    }

    getActiveTableCell() {
        if (this.activeTableCell && (typeof this.dom.editorArea?.contains !== 'function' || this.dom.editorArea.contains(this.activeTableCell))) {
            return this.activeTableCell;
        }
        const sel = window.getSelection ? window.getSelection() : null;
        if (sel && sel.rangeCount > 0) {
            let node = sel.getRangeAt(0).startContainer;
            if (node && node.nodeType === 3) node = node.parentNode;
            const cell = node?.closest ? node.closest('td, th') : null;
            if (cell && (typeof this.dom.editorArea?.contains !== 'function' || this.dom.editorArea.contains(cell))) {
                return cell;
            }
        }
        return null;
    }

    insertImageFile(imageFile) {
        if (!imageFile) return false;
        const targetCell = this.getActiveTableCell();
        const reader = new FileReader();
        reader.onload = (loadEvent) => {
            const dataUrl = loadEvent.target?.result;
            if (dataUrl) {
                const img = document.createElement('img');
                img.src = dataUrl;
                img.alt = imageFile.name || 'Pasted Image';

                if (targetCell) {
                    img.className = 'max-w-full max-h-48 object-contain rounded border border-blue-400 my-1 inline-block cursor-pointer';
                    targetCell.appendChild(img);
                    this.handleEditorInput();
                    this.setActiveImage(img);
                    this.toast.show('Image inserted into table cell!', 'success', 'Cell Image Inscribed', 'fa-solid fa-table-cells text-cyan-400');
                } else {
                    img.className = 'retro-image rounded-lg border-2 border-blue-500 max-h-96 shadow-md my-2 object-contain inline-block cursor-pointer';
                    const figure = document.createElement('figure');
                    figure.className = 'retro-image-figure retro-align-center text-center my-3 mx-auto max-w-full block';
                    if (figure.setAttribute) figure.setAttribute('data-align', 'center');
                    if (figure.dataset) figure.dataset.align = 'center';
                    if (figure.style) {
                        figure.style.textAlign = 'center';
                        figure.style.marginLeft = 'auto';
                        figure.style.marginRight = 'auto';
                    }
                    figure.appendChild(img);

                    const figcaption = document.createElement('figcaption');
                    figcaption.className = 'retro-image-caption text-xs font-mono text-slate-500 dark:text-slate-400 mt-1.5 italic outline-none focus:ring-1 focus:ring-blue-400 rounded px-2 py-0.5 inline-block text-center cursor-text';
                    if (figcaption.setAttribute) {
                        figcaption.setAttribute('contenteditable', 'true');
                        figcaption.setAttribute('data-placeholder', 'Tambahkan caption gambar...');
                    }
                    figcaption.textContent = 'Tambahkan caption gambar...';
                    figure.appendChild(figcaption);

                    const breakP = document.createElement('p');
                    breakP.innerHTML = '<br>';
                    const htmlToInsert = figure.outerHTML + breakP.outerHTML;

                    document.execCommand('insertHTML', false, htmlToInsert);
                    this.handleEditorInput();
                    const insertedImg = this.dom.editorArea?.querySelector ? (this.dom.editorArea.querySelector(`img[src="${dataUrl}"]`) || img) : img;
                    this.setActiveImage(insertedImg);
                    this.toast.show('Image pasted into scroll!', 'success', 'Image Inscribed', 'fa-solid fa-image text-cyan-400');
                }
            }
        };
        reader.readAsDataURL(imageFile);
        return true;
    }

    /* ---------------- CLIPBOARD PASTE INTERCEPTOR (.MD, IMAGES & TABLES) ---------------- */
    initPasteInterceptor() {
        if (!this.dom.editorArea) return;

        const handleImageFile = (imageFile) => this.insertImageFile(imageFile);

        this.dom.editorArea.addEventListener('paste', (e) => {
            const clipboardData = e.clipboardData || window.clipboardData;
            if (!clipboardData) return;

            // Check if clipboard contains an image file (e.g. screenshot or copied image file)
            const items = clipboardData.items ? Array.from(clipboardData.items) : [];
            const imageItem = items.find(item => item.type && item.type.startsWith('image/'));
            if (imageItem) {
                const file = imageItem.getAsFile();
                if (file) {
                    e.preventDefault();
                    handleImageFile(file);
                    return;
                }
            }

            const files = clipboardData.files ? Array.from(clipboardData.files) : [];
            const imageFile = files.find(file => file.type && file.type.startsWith('image/'));
            if (imageFile) {
                e.preventDefault();
                handleImageFile(imageFile);
                return;
            }

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
                doc.querySelectorAll('img').forEach(img => {
                    if (!img.className) {
                        img.className = 'rounded-lg border-2 border-blue-500 max-h-96 shadow-md my-2';
                    }
                });
                document.execCommand('insertHTML', false, sanitizeHtml(doc.body.innerHTML));
                this.handleEditorInput();
                const hasImg = doc.querySelector('img') !== null;
                const hasTable = doc.querySelector('table') !== null;
                if (hasImg) {
                    this.toast.show('Pasted image formatted into scroll!', 'success', 'Image Inscribed', 'fa-solid fa-image text-cyan-400');
                } else if (hasTable) {
                    this.toast.show('Pasted HTML Table formatted cleanly!', 'info', 'Table Injected', 'fa-solid fa-table text-cyan-400');
                } else {
                    this.toast.show('Pasted HTML content formatted cleanly!', 'info');
                }
            }
        });

        // Support drag and drop images directly into editor
        this.dom.editorArea.addEventListener('dragover', (e) => {
            if (e.dataTransfer?.types?.includes('Files')) {
                e.preventDefault();
            }
        });
        this.dom.editorArea.addEventListener('drop', (e) => {
            const files = e.dataTransfer?.files ? Array.from(e.dataTransfer.files) : [];
            const imageFile = files.find(f => f.type && f.type.startsWith('image/'));
            if (imageFile) {
                e.preventDefault();
                const cell = e.target?.closest ? e.target.closest('td, th') : null;
                if (cell && (typeof this.dom.editorArea.contains !== 'function' || this.dom.editorArea.contains(cell))) {
                    this.setActiveTableCell(cell);
                }
                handleImageFile(imageFile);
            }
        });

        // Support pasting image into raw markdown editor if active
        if (this.dom.markdownRawEditor) {
            this.dom.markdownRawEditor.addEventListener('paste', (e) => {
                const clipboardData = e.clipboardData || window.clipboardData;
                if (!clipboardData) return;
                const items = clipboardData.items ? Array.from(clipboardData.items) : [];
                const imageItem = items.find(item => item.type && item.type.startsWith('image/'));
                const file = imageItem ? imageItem.getAsFile() : (clipboardData.files?.[0]?.type?.startsWith('image/') ? clipboardData.files[0] : null);
                if (file) {
                    e.preventDefault();
                    const reader = new FileReader();
                    reader.onload = (loadEvent) => {
                        const dataUrl = loadEvent.target?.result;
                        if (dataUrl) {
                            const markdownImage = `\n![Pasted Image](${dataUrl})\n`;
                            const textarea = this.dom.markdownRawEditor;
                            const start = textarea.selectionStart || 0;
                            const end = textarea.selectionEnd || 0;
                            textarea.setRangeText(markdownImage, start, end, 'end');
                            textarea.dispatchEvent(new Event('input'));
                            this.toast.show('Image pasted as Markdown into scroll!', 'success', 'Image Inscribed', 'fa-solid fa-image text-cyan-400');
                        }
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
    }

    insertRetroTable() {
        this.audio.playCoin();
        this.pushUndoSnapshot();
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

    /* ---------------- DETAILED TABLE CUSTOMIZATION TOOLKIT ---------------- */
    setActiveTableCell(cell) {
        if (this.activeTableCell && this.activeTableCell !== cell) {
            this.activeTableCell.classList.remove('active-table-cell');
        }
        this.activeTableCell = cell;
        if (cell) {
            cell.classList.add('active-table-cell');
            const tr = cell.closest ? cell.closest('tr') : null;
            const table = cell.closest ? cell.closest('table') : null;
            if (tr && table) {
                const rowIndex = (tr.rowIndex !== undefined ? tr.rowIndex : 0) + 1;
                const colIndex = (cell.cellIndex !== undefined ? cell.cellIndex : 0) + 1;
                const totalRows = table.rows ? table.rows.length : (table.querySelectorAll ? table.querySelectorAll('tr').length : 1);
                const totalCols = tr.cells ? tr.cells.length : (tr.querySelectorAll ? tr.querySelectorAll('td, th').length : 1);
                if (this.dom.tableCellCoord) {
                    this.dom.tableCellCoord.textContent = `R${rowIndex}:${totalRows} | C${colIndex}:${totalCols}`;
                }
                if (this.dom.tableToolkitBar) {
                    this.dom.tableToolkitBar.classList.remove('hidden');
                }
            }
        } else {
            if (this.dom.tableToolkitBar) {
                this.dom.tableToolkitBar.classList.add('hidden');
            }
        }
    }

    initTableToolkit() {
        if (!this.dom.editorArea) return;

        const detectCellFromSelection = () => {
            const sel = window.getSelection ? window.getSelection() : null;
            if (sel && sel.rangeCount > 0) {
                let node = sel.getRangeAt(0).startContainer;
                if (node && node.nodeType === 3) node = node.parentNode;
                const cell = node?.closest ? node.closest('td, th') : null;
                if (cell && (typeof this.dom.editorArea.contains !== 'function' || this.dom.editorArea.contains(cell))) {
                    this.setActiveTableCell(cell);
                    return;
                }
            }
            if (!document.activeElement?.closest('#table-toolkit-bar')) {
                this.setActiveTableCell(null);
            }
        };

        this.dom.editorArea.addEventListener('click', (e) => {
            const cell = e.target?.closest ? e.target.closest('td, th') : null;
            if (cell && (typeof this.dom.editorArea.contains !== 'function' || this.dom.editorArea.contains(cell))) {
                this.setActiveTableCell(cell);
            } else {
                this.setActiveTableCell(null);
            }
        });

        this.dom.editorArea.addEventListener('keyup', () => {
            detectCellFromSelection();
        });

        // Add Row Above
        this.dom.tableAddRowAboveBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            this.tableAddRow('above');
        });

        // Add Row Below
        this.dom.tableAddRowBelowBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            this.tableAddRow('below');
        });

        // Delete Row
        this.dom.tableDelRowBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            this.tableDeleteRow();
        });

        // Add Col Left
        this.dom.tableAddColLeftBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            this.tableAddColumn('left');
        });

        // Add Col Right
        this.dom.tableAddColRightBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            this.tableAddColumn('right');
        });

        // Delete Col
        this.dom.tableDelColBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            this.tableDeleteColumn();
        });

        // Toggle Header (TH / TD)
        this.dom.tableToggleHeaderBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            this.tableToggleHeader();
        });

        // Alignments
        this.dom.tableAlignLeftBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            this.tableSetAlign('left');
        });
        this.dom.tableAlignCenterBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            this.tableSetAlign('center');
        });
        this.dom.tableAlignRightBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            this.tableSetAlign('right');
        });

        // Colors
        this.dom.tableCellBgPicker?.addEventListener('input', (e) => {
            this.tableSetCellBg(e.target.value);
        });
        this.dom.tableCellTextPicker?.addEventListener('input', (e) => {
            this.tableSetCellColor(e.target.value);
        });
        this.dom.tableClearColorsBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            this.tableResetColors();
        });

        // Insert Image into Active Cell
        this.dom.tableInsertImgBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            this.dom.tableCellImgInput?.click();
        });
        this.dom.tableCellImgInput?.addEventListener('change', (e) => {
            const file = e.target.files?.[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = (loadEvt) => {
                    if (loadEvt.target?.result) {
                        this.tableInsertImage(loadEvt.target.result);
                    }
                };
                reader.readAsDataURL(file);
                e.target.value = '';
            }
        });

        // Delete Table
        this.dom.tableDeleteTableBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            this.tableDeleteTable();
        });

        // Close Toolkit Bar
        this.dom.tableCloseToolkitBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            this.setActiveTableCell(null);
        });
    }

    tableAddRow(position = 'below') {
        const cell = this.activeTableCell;
        if (!cell) {
            this.toast.show('Pilih sel tabel terlebih dahulu!', 'warning');
            return;
        }
        const tr = cell.closest ? cell.closest('tr') : null;
        const table = tr?.closest ? tr.closest('table') : null;
        if (!tr || !table) return;

        this.pushUndoSnapshot();

        const colCount = tr.cells ? tr.cells.length : 1;
        const newTr = document.createElement('tr');
        const isHeader = position === 'above' && (tr.closest ? tr.closest('thead') !== null : false);

        for (let i = 0; i < colCount; i++) {
            const newCell = document.createElement(isHeader ? 'th' : 'td');
            newCell.innerHTML = '<br>';
            newTr.appendChild(newCell);
        }

        if (position === 'above') {
            tr.parentNode.insertBefore(newTr, tr);
        } else {
            tr.parentNode.insertBefore(newTr, tr.nextSibling);
        }

        const targetCellIndex = Math.min(cell.cellIndex || 0, (newTr.cells ? newTr.cells.length - 1 : 0));
        if (newTr.cells && newTr.cells[targetCellIndex]) {
            this.setActiveTableCell(newTr.cells[targetCellIndex]);
        }
        this.handleEditorInput();
        this.audio?.playClick?.();
        this.toast.show(position === 'above' ? 'Baris ditambahkan di atas' : 'Baris ditambahkan di bawah', 'success');
    }

    tableDeleteRow() {
        const cell = this.activeTableCell;
        if (!cell) {
            this.toast.show('Pilih sel tabel terlebih dahulu!', 'warning');
            return;
        }
        const tr = cell.closest ? cell.closest('tr') : null;
        const table = tr?.closest ? tr.closest('table') : null;
        if (!tr || !table) return;

        this.pushUndoSnapshot();

        const allRows = table.querySelectorAll ? table.querySelectorAll('tr') : [];
        if (allRows.length <= 1) {
            table.remove();
            this.setActiveTableCell(null);
            this.handleEditorInput();
            this.toast.show('Tabel dihapus karena baris terakhir dibuang', 'info');
            return;
        }

        const nextTr = tr.nextElementSibling || tr.previousElementSibling;
        const colIdx = cell.cellIndex || 0;
        tr.remove();

        if (nextTr && nextTr.cells && nextTr.cells.length > 0) {
            const targetCell = nextTr.cells[Math.min(colIdx, nextTr.cells.length - 1)];
            this.setActiveTableCell(targetCell);
        } else {
            this.setActiveTableCell(null);
        }

        this.handleEditorInput();
        this.audio?.playClick?.();
        this.toast.show('Baris berhasil dihapus', 'info');
    }

    tableAddColumn(position = 'right') {
        const cell = this.activeTableCell;
        if (!cell) {
            this.toast.show('Pilih sel tabel terlebih dahulu!', 'warning');
            return;
        }
        const table = cell.closest ? cell.closest('table') : null;
        if (!table) return;

        this.pushUndoSnapshot();

        const colIdx = cell.cellIndex !== undefined ? cell.cellIndex : 0;
        const rows = table.querySelectorAll ? table.querySelectorAll('tr') : [];
        const activeRow = cell.closest ? cell.closest('tr') : null;
        let newActiveCell = null;

        rows.forEach(r => {
            const isHeader = (r.closest ? r.closest('thead') !== null : false) || (r.querySelector ? r.querySelector('th') !== null : false);
            const newCell = document.createElement(isHeader ? 'th' : 'td');
            newCell.innerHTML = '<br>';
            if (position === 'left') {
                r.insertBefore(newCell, r.cells ? r.cells[colIdx] || null : null);
            } else {
                r.insertBefore(newCell, r.cells ? r.cells[colIdx + 1] || null : null);
            }
            if (r === activeRow) {
                newActiveCell = newCell;
            }
        });

        if (newActiveCell) {
            this.setActiveTableCell(newActiveCell);
        }
        this.handleEditorInput();
        this.audio?.playClick?.();
        this.toast.show(position === 'left' ? 'Kolom ditambahkan di kiri' : 'Kolom ditambahkan di kanan', 'success');
    }

    tableDeleteColumn() {
        const cell = this.activeTableCell;
        if (!cell) {
            this.toast.show('Pilih sel tabel terlebih dahulu!', 'warning');
            return;
        }
        const table = cell.closest ? cell.closest('table') : null;
        if (!table) return;

        this.pushUndoSnapshot();

        const colIdx = cell.cellIndex !== undefined ? cell.cellIndex : 0;
        const rows = table.querySelectorAll ? table.querySelectorAll('tr') : [];
        const firstRow = rows[0];
        if (!firstRow || (firstRow.cells && firstRow.cells.length <= 1)) {
            table.remove();
            this.setActiveTableCell(null);
            this.handleEditorInput();
            this.toast.show('Tabel dihapus karena kolom terakhir dibuang', 'info');
            return;
        }

        const activeRow = cell.closest ? cell.closest('tr') : null;
        rows.forEach(r => {
            if (r.cells && r.cells[colIdx]) {
                r.cells[colIdx].remove();
            }
        });

        if (activeRow && activeRow.cells && activeRow.cells.length > 0) {
            const targetIdx = Math.max(0, Math.min(colIdx, activeRow.cells.length - 1));
            this.setActiveTableCell(activeRow.cells[targetIdx]);
        } else {
            this.setActiveTableCell(null);
        }

        this.handleEditorInput();
        this.audio?.playClick?.();
        this.toast.show('Kolom berhasil dihapus', 'info');
    }

    tableToggleHeader() {
        const cell = this.activeTableCell;
        if (!cell) return;
        const tr = cell.closest ? cell.closest('tr') : null;
        if (!tr || !tr.cells) return;

        this.pushUndoSnapshot();

        const cells = Array.from(tr.cells);
        const isHeader = cells.every(c => c.tagName.toLowerCase() === 'th');
        const targetTag = isHeader ? 'td' : 'th';

        cells.forEach((c) => {
            const replacement = document.createElement(targetTag);
            replacement.innerHTML = c.innerHTML;
            if (c.attributes) {
                Array.from(c.attributes).forEach(attr => {
                    replacement.setAttribute(attr.name, attr.value);
                });
            }
            tr.replaceChild(replacement, c);
            if (c === cell) {
                this.setActiveTableCell(replacement);
            }
        });

        this.handleEditorInput();
        this.audio?.playClick?.();
        this.toast.show(isHeader ? 'Diubah menjadi Baris Data (TD)' : 'Diubah menjadi Baris Header (TH)', 'info');
    }

    tableSetAlign(align) {
        if (!this.activeTableCell) return;
        this.pushUndoSnapshot();
        this.activeTableCell.style.textAlign = align;
        this.handleEditorInput();
        this.toast.show(`Perataan teks: ${align}`, 'info');
    }

    tableSetCellBg(color) {
        if (!this.activeTableCell) return;
        this.pushUndoSnapshot();
        this.activeTableCell.style.backgroundColor = color;
        this.handleEditorInput();
    }

    tableSetCellColor(color) {
        if (!this.activeTableCell) return;
        this.pushUndoSnapshot();
        this.activeTableCell.style.color = color;
        this.handleEditorInput();
    }

    tableResetColors() {
        if (!this.activeTableCell) return;
        this.pushUndoSnapshot();
        this.activeTableCell.style.removeProperty('background-color');
        this.activeTableCell.style.removeProperty('color');
        this.handleEditorInput();
        this.toast.show('Warna sel dikembalikan ke bawaan', 'info');
    }

    tableInsertImage(dataUrl) {
        if (!this.activeTableCell) {
            this.toast.show('Pilih sel tabel terlebih dahulu!', 'warning');
            return;
        }
        this.pushUndoSnapshot();
        const img = document.createElement('img');
        img.src = dataUrl;
        img.alt = 'Table Image';
        img.className = 'max-w-full max-h-48 object-contain rounded border border-blue-400 my-1 inline-block';
        this.activeTableCell.appendChild(img);
        this.handleEditorInput();
        this.audio?.playCoin?.();
        this.toast.show('Gambar berhasil disisipkan ke dalam sel tabel!', 'success', 'Gambar Tersimpan', 'fa-solid fa-image text-cyan-400');
    }

    tableDeleteTable() {
        const table = this.activeTableCell?.closest ? this.activeTableCell.closest('table') : null;
        if (table) {
            this.pushUndoSnapshot();
            table.remove();
            this.setActiveTableCell(null);
            this.handleEditorInput();
            this.audio?.playDelete?.();
            this.toast.show('Tabel berhasil dihapus', 'info', 'Tabel Dihapus', 'fa-solid fa-trash-can text-red-400');
        }
    }

    /* ---------------- IMAGE ARTIFACT MATRIX TOOLKIT ---------------- */
    setActiveImage(img) {
        if (this.activeImage && this.activeImage !== img) {
            this.activeImage.classList?.remove?.('active-image-artifact');
            if (this.activeImageFigure) {
                this.activeImageFigure.classList?.remove?.('active-image-figure');
            }
        }

        this.activeImage = img;

        if (img) {
            img.classList?.add?.('active-image-artifact');
            this.activeImageFigure = img.closest ? img.closest('figure') : null;
            if (this.activeImageFigure) {
                this.activeImageFigure.classList?.add?.('active-image-figure');
                const align = this.activeImageFigure.dataset?.align
                    || (this.activeImageFigure.classList?.contains?.('retro-align-left') ? 'left' : (this.activeImageFigure.classList?.contains?.('retro-align-right') ? 'right' : 'center'));
                if (this.dom.imageAlignBadge) {
                    this.dom.imageAlignBadge.textContent = align.toUpperCase();
                }
            } else {
                if (this.dom.imageAlignBadge) {
                    this.dom.imageAlignBadge.textContent = 'CENTER';
                }
            }
            if (this.dom.imageToolkitBar) {
                this.dom.imageToolkitBar.classList?.remove?.('hidden');
            }
        } else {
            this.activeImageFigure = null;
            if (this.dom.imageToolkitBar) {
                this.dom.imageToolkitBar.classList?.add?.('hidden');
            }
        }
    }

    alignActiveImage(align) {
        if (!this.activeImage) return;
        const validAlign = ['left', 'center', 'right'].includes(align) ? align : 'center';
        this.audio?.playClick?.();
        this.pushUndoSnapshot();

        let figure = this.activeImageFigure || (this.activeImage.closest ? this.activeImage.closest('figure') : null);

        // If not inside a <figure>, wrap it into a <figure>
        if (!figure && this.activeImage.parentNode) {
            figure = document.createElement('figure');
            figure.className = `retro-image-figure retro-align-${validAlign} text-${validAlign} my-3 block`;
            if (figure.setAttribute) figure.setAttribute('data-align', validAlign);
            if (figure.dataset) figure.dataset.align = validAlign;
            this.activeImage.parentNode.insertBefore(figure, this.activeImage);
            figure.appendChild(this.activeImage);
            this.activeImageFigure = figure;
        }

        if (figure) {
            figure.classList?.remove?.('retro-align-left', 'retro-align-center', 'retro-align-right', 'text-left', 'text-center', 'text-right');
            figure.classList?.add?.(`retro-align-${validAlign}`, `text-${validAlign}`);
            if (figure.setAttribute) figure.setAttribute('data-align', validAlign);
            if (figure.dataset) figure.dataset.align = validAlign;

            if (figure.style) {
                if (validAlign === 'left') {
                    figure.style.textAlign = 'left';
                    figure.style.marginLeft = '0';
                    figure.style.marginRight = 'auto';
                } else if (validAlign === 'right') {
                    figure.style.textAlign = 'right';
                    figure.style.marginLeft = 'auto';
                    figure.style.marginRight = '0';
                } else {
                    figure.style.textAlign = 'center';
                    figure.style.marginLeft = 'auto';
                    figure.style.marginRight = 'auto';
                }
            }
            this.activeImageFigure = figure;
        }

        if (this.dom.imageAlignBadge) {
            this.dom.imageAlignBadge.textContent = validAlign.toUpperCase();
        }

        this.handleEditorInput();
        const alignLabel = validAlign === 'left' ? 'Kiri' : (validAlign === 'right' ? 'Kanan' : 'Tengah');
        this.toast.show(`Gambar diratakan ke ${alignLabel}`, 'info', 'Perataan Gambar');
    }

    promptActiveImageCaption() {
        if (!this.activeImage) return;
        this.audio?.playClick?.();

        let figure = this.activeImageFigure || (this.activeImage.closest ? this.activeImage.closest('figure') : null);
        if (!figure && this.activeImage.parentNode) {
            figure = document.createElement('figure');
            figure.className = 'retro-image-figure retro-align-center text-center my-3 mx-auto max-w-full block';
            if (figure.setAttribute) figure.setAttribute('data-align', 'center');
            this.activeImage.parentNode.insertBefore(figure, this.activeImage);
            figure.appendChild(this.activeImage);
            this.activeImageFigure = figure;
        }

        const placeholder = 'Tambahkan caption gambar...';
        let figcaption = figure?.querySelector ? figure.querySelector('figcaption') : null;
        const currentText = figcaption ? (figcaption.textContent || '').trim() : '';
        const initialVal = (currentText && currentText !== placeholder) ? currentText : '';

        const captionInput = prompt('Masukkan caption untuk gambar ini (kosongkan untuk menghapus):', initialVal);
        if (captionInput === null) return; // User cancelled prompt

        this.pushUndoSnapshot();

        if (captionInput.trim() === '') {
            if (figcaption) {
                figcaption.remove();
            }
            this.toast.show('Caption gambar dihapus', 'info', 'Caption Dihapus');
        } else {
            if (!figcaption) {
                figcaption = document.createElement('figcaption');
                figcaption.className = 'retro-image-caption text-xs font-mono text-slate-500 dark:text-slate-400 mt-1.5 italic outline-none focus:ring-1 focus:ring-blue-400 rounded px-2 py-0.5 inline-block text-center cursor-text';
                if (figcaption.setAttribute) {
                    figcaption.setAttribute('contenteditable', 'true');
                    figcaption.setAttribute('data-placeholder', placeholder);
                }
                figure.appendChild(figcaption);
            }
            figcaption.textContent = captionInput.trim();
            this.toast.show('Caption gambar berhasil disimpan', 'success', 'Caption Disimpan');
        }

        this.handleEditorInput();
    }

    deleteActiveImage() {
        if (!this.activeImage) return;
        this.audio?.playDelete?.();
        this.pushUndoSnapshot();

        const figure = this.activeImageFigure || (this.activeImage.closest ? this.activeImage.closest('figure') : null);
        if (figure) {
            figure.remove();
        } else {
            this.activeImage.remove();
        }

        this.setActiveImage(null);
        this.handleEditorInput();
        this.toast.show('Gambar berhasil dihapus dari catatan', 'info', 'Gambar Dihapus');
    }

    initImageToolkit() {
        if (!this.dom.editorArea) return;

        this.dom.editorArea.addEventListener('click', (e) => {
            const img = e.target?.closest ? e.target.closest('img') : (e.target?.tagName === 'IMG' ? e.target : null);
            const figure = e.target?.closest ? e.target.closest('figure') : null;
            const figcaption = e.target?.closest ? e.target.closest('figcaption') : null;

            if (img && (typeof this.dom.editorArea.contains !== 'function' || this.dom.editorArea.contains(img))) {
                this.setActiveImage(img);
            } else if (figure && (typeof this.dom.editorArea.contains !== 'function' || this.dom.editorArea.contains(figure))) {
                const foundImg = figure.querySelector ? figure.querySelector('img') : null;
                this.setActiveImage(foundImg || img);
            } else if (!figcaption && !e.target?.closest?.('#image-toolkit-bar')) {
                this.setActiveImage(null);
            }
        });

        // figcaption placeholder auto-clear on focus
        this.dom.editorArea.addEventListener('focusin', (e) => {
            const figcaption = e.target?.closest ? e.target.closest('figcaption') : null;
            if (figcaption && figcaption.textContent?.trim() === 'Tambahkan caption gambar...') {
                figcaption.textContent = '';
            }
        });

        this.dom.editorArea.addEventListener('focusout', (e) => {
            const figcaption = e.target?.closest ? e.target.closest('figcaption') : null;
            if (figcaption && figcaption.textContent?.trim() === '') {
                figcaption.textContent = 'Tambahkan caption gambar...';
            }
        });

        this.dom.imgAlignLeftBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            this.alignActiveImage('left');
        });

        this.dom.imgAlignCenterBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            this.alignActiveImage('center');
        });

        this.dom.imgAlignRightBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            this.alignActiveImage('right');
        });

        this.dom.imgCaptionBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            this.promptActiveImageCaption();
        });

        this.dom.imgDeleteBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            this.deleteActiveImage();
        });

        this.dom.closeImageBarBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            this.setActiveImage(null);
        });
    }

    insertInteractiveTask() {
        this.audio.playClick();
        this.pushUndoSnapshot();
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

        this.pushUndoSnapshot();

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
        image.className = 'retro-image rounded-lg border-2 border-blue-500 max-h-72 shadow-md my-2 object-contain inline-block cursor-pointer';

        const figure = document.createElement('figure');
        figure.className = 'retro-image-figure retro-align-center text-center my-3 mx-auto max-w-full block';
        if (figure.setAttribute) figure.setAttribute('data-align', 'center');
        if (figure.dataset) figure.dataset.align = 'center';
        if (figure.style) {
            figure.style.textAlign = 'center';
            figure.style.marginLeft = 'auto';
            figure.style.marginRight = 'auto';
        }
        figure.appendChild(image);

        const figcaption = document.createElement('figcaption');
        figcaption.className = 'retro-image-caption text-xs font-mono text-slate-500 dark:text-slate-400 mt-1.5 italic outline-none focus:ring-1 focus:ring-blue-400 rounded px-2 py-0.5 inline-block text-center cursor-text';
        if (figcaption.setAttribute) {
            figcaption.setAttribute('contenteditable', 'true');
            figcaption.setAttribute('data-placeholder', 'Tambahkan caption gambar...');
        }
        figcaption.textContent = alt || 'Tambahkan caption gambar...';
        figure.appendChild(figcaption);

        const breakP = document.createElement('p');
        breakP.innerHTML = '<br>';
        const imgHtml = figure.outerHTML + breakP.outerHTML;
        document.execCommand('insertHTML', false, imgHtml);
        this.dom.editorArea.focus();
        this.handleEditorInput();
        const insertedImg = this.dom.editorArea?.querySelector ? (this.dom.editorArea.querySelector(`img[src="${src}"]`) || image) : image;
        this.setActiveImage(insertedImg);
        this.toast.show('Artifact image injected into scroll', 'success', 'Media Ready');
    }

    toggleMarkdownRawView() {
        if (!this.activeNote) {
            this.toast.show('Pilih atau buat gulungan catatan terlebih dahulu.', 'warning');
            return;
        }
        this.toggleFindBar(false);
        this.audio?.playBlip?.();

        if (this.isPlainTextMode) {
            this.togglePlainTextMode(false);
        }

        this.isRawMarkdownMode = !this.isRawMarkdownMode;

        if (this.isRawMarkdownMode) {
            const md = MarkdownEngine.toMarkdown(this.dom.editorArea.innerHTML);
            this.rawViewOriginalHtml = this.dom.editorArea.innerHTML;
            this.rawViewOriginalMarkdown = md;
            this.dom.markdownRawEditor.value = md;
            this.dom.editorArea.classList.add('hidden');
            this.dom.markdownRawEditor.classList.remove('hidden');
            this.dom.markdownViewToggleBtn.innerHTML = '<i class="fa-solid fa-eye mr-1 text-blue-600 dark:text-cyan-400"></i> WYSIWYG';
            this.toast.show('Raw Markdown View activated', 'info');
        } else {
            const html = this.dom.markdownRawEditor.value === this.rawViewOriginalMarkdown
                ? this.rawViewOriginalHtml : MarkdownEngine.parse(this.dom.markdownRawEditor.value);
            this.dom.editorArea.innerHTML = html;
            this.dom.markdownRawEditor.classList.add('hidden');
            this.dom.editorArea.classList.remove('hidden');
            this.dom.markdownViewToggleBtn.innerHTML = '<i class="fa-brands fa-markdown mr-1 text-blue-600 dark:text-cyan-400"></i> RAW MD';
            this.toast.show('WYSIWYG Rich Editor restored', 'info');
            this.handleEditorInput();
        }
    }

    togglePlainTextMode(forceState = null) {
        if (!this.activeNote && forceState !== false) {
            this.toast.show('Pilih atau buat gulungan catatan terlebih dahulu.', 'warning');
            return;
        }
        this.toggleFindBar(false);
        this.audio?.playBlip?.();

        const targetState = forceState !== null ? forceState : !this.isPlainTextMode;
        if (targetState === this.isPlainTextMode) return;

        if (targetState && this.isRawMarkdownMode) {
            this.toggleMarkdownRawView();
        }

        this.isPlainTextMode = targetState;

        if (this.isPlainTextMode) {
            const html = this.dom.editorArea ? this.dom.editorArea.innerHTML : '';
            const plain = this.htmlToPlainText(html);
            this.plainTextOriginalHtml = html;
            this.plainTextOriginalText = plain;

            if (this.dom.plainTextEditor) {
                this.dom.plainTextEditor.value = plain;
            }
            this.updatePlainTextStats();

            this.dom.editorArea?.classList.add('hidden');
            this.dom.markdownRawEditor?.classList.add('hidden');
            this.dom.plainTextContainer?.classList.remove('hidden');

            if (this.dom.plainTextToggleBtn) {
                this.dom.plainTextToggleBtn.classList.add('bg-emerald-600', 'text-white');
                this.dom.plainTextToggleBtn.classList.remove('bg-surface');
                this.dom.plainTextToggleBtn.innerHTML = '<i class="fa-solid fa-eye mr-1 text-white"></i> WYSIWYG';
                this.dom.plainTextToggleBtn.title = 'Kembali ke Mode Rich Editor (WYSIWYG)';
            }

            if (this.dom.editorToolbarDecks) {
                this.dom.editorToolbarDecks.classList.add('notepad-mode');
            }

            if (this.dom.notepadModeBanner) {
                this.dom.notepadModeBanner.classList.remove('hidden');
                this.dom.notepadModeBanner.classList.add('flex');
            }

            this.toast.show('Mode Teks Biasa (Notepad) aktif. Teks siap disalin keseluruhan.', 'info', 'Notepad Mode', 'fa-solid fa-file-lines text-emerald-400');
            this.dom.plainTextEditor?.focus();
        } else {
            const currentPlain = this.dom.plainTextEditor ? this.dom.plainTextEditor.value : '';
            const html = currentPlain === this.plainTextOriginalText
                ? this.plainTextOriginalHtml
                : this.plainTextToHtml(currentPlain);

            if (this.dom.editorArea) {
                this.dom.editorArea.innerHTML = html;
            }

            this.dom.plainTextContainer?.classList.add('hidden');
            this.dom.editorArea?.classList.remove('hidden');

            if (this.dom.plainTextToggleBtn) {
                this.dom.plainTextToggleBtn.classList.remove('bg-emerald-600', 'text-white');
                this.dom.plainTextToggleBtn.classList.add('bg-surface');
                this.dom.plainTextToggleBtn.innerHTML = '<i class="fa-solid fa-file-lines mr-1 text-emerald-600 dark:text-emerald-400"></i> TXT';
                this.dom.plainTextToggleBtn.title = 'Mode Teks Biasa / Notepad (Alt+N)';
            }

            if (this.dom.editorToolbarDecks) {
                this.dom.editorToolbarDecks.classList.remove('notepad-mode');
            }

            if (this.dom.notepadModeBanner) {
                this.dom.notepadModeBanner.classList.add('hidden');
                this.dom.notepadModeBanner.classList.remove('flex');
            }

            this.toast.show('Kembali ke Mode Rich Editor (WYSIWYG).', 'info', 'Rich Editor', 'fa-solid fa-pen-nib text-blue-400');
            this.handleEditorInput();
        }
    }

    async copyTextToClipboard(text, toastOptions = null) {
        let success = false;
        try {
            if (typeof navigator !== 'undefined' && navigator.clipboard && navigator.clipboard.writeText) {
                await navigator.clipboard.writeText(text);
                success = true;
            } else if (typeof document !== 'undefined') {
                const ta = document.createElement('textarea');
                ta.value = text;
                ta.style.position = 'fixed';
                ta.style.left = '-9999px';
                ta.style.top = '-9999px';
                ta.style.opacity = '0';
                document.body.appendChild(ta);
                ta.focus();
                ta.select();
                success = document.execCommand('copy');
                document.body.removeChild(ta);
            }
        } catch {
            success = false;
        }

        if (success && toastOptions) {
            this.audio?.playCoin?.();
            this.toast?.show?.(toastOptions.message, toastOptions.type || 'success', toastOptions.title || 'Disalin', toastOptions.icon || 'fa-solid fa-copy text-emerald-400');
        } else if (!success && toastOptions) {
            this.toast?.show?.('Gagal menyalin teks ke clipboard.', 'danger', 'Gagal Salin');
        }

        return success;
    }

    async copyEntirePlainText() {
        let text = '';
        if (this.isPlainTextMode && this.dom.plainTextEditor) {
            text = this.dom.plainTextEditor.value;
        } else if (this.dom.editorArea) {
            text = this.htmlToPlainText(this.dom.editorArea.innerHTML);
        } else if (this.activeNote) {
            text = this.htmlToPlainText(this.activeNote.content || '');
        }

        const success = await this.copyTextToClipboard(text, {
            message: 'Seluruh teks berhasil disalin ke clipboard!',
            type: 'success',
            title: 'Teks Disalin',
            icon: 'fa-solid fa-copy text-emerald-400',
        });

        if (success) {
            if (this.dom.plainTextCopyBtn) {
                const orig = this.dom.plainTextCopyBtn.innerHTML;
                this.dom.plainTextCopyBtn.innerHTML = '<i class="fa-solid fa-check mr-1 text-white"></i><span>Tersalin!</span>';
                this.dom.plainTextCopyBtn.classList.add('bg-emerald-700');
                setTimeout(() => {
                    if (this.dom.plainTextCopyBtn) {
                        this.dom.plainTextCopyBtn.innerHTML = orig;
                        this.dom.plainTextCopyBtn.classList.remove('bg-emerald-700');
                    }
                }, 1500);
            }
        }

        return success;
    }

    updatePlainTextStats() {
        if (!this.dom.plainTextEditor) return;
        const text = this.dom.plainTextEditor.value || '';
        const charCount = text.length;
        const words = text.trim() ? text.trim().split(/\s+/).filter(Boolean) : [];
        const wordCount = words.length;
        const lines = text ? text.split(/\r?\n/).length : 0;

        if (this.dom.plainTextCharCount) {
            this.dom.plainTextCharCount.textContent = `${charCount} karakter`;
        }
        if (this.dom.plainTextWordCount) {
            this.dom.plainTextWordCount.textContent = `${wordCount} kata`;
        }
        if (this.dom.plainTextLineCount) {
            this.dom.plainTextLineCount.textContent = `${lines} baris`;
        }
    }

    htmlToPlainText(html) {
        return PlainTextEngine.toPlainText(html);
    }

    plainTextToHtml(text) {
        return PlainTextEngine.toHtml(text);
    }

    promptMarkdownInput() {
        this.openMarkdownModal();
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
        if (this.dom.plainTextEditor) {
            this.dom.plainTextEditor.style.fontSize = size;
        }
        if (this.dom.markdownRawEditor) {
            this.dom.markdownRawEditor.style.fontSize = size;
        }
        if (this.activeNote) {
            this.activeNote.font_size = size;
        }
    }

    /* ---------------- EDITOR INPUT & METRICS ---------------- */
    handleEditorInput() {
        if (!this.activeNote) return;

        const content = this.dom.editorArea?.cloneNode ? this.dom.editorArea.cloneNode(true) : { querySelectorAll: () => [], innerHTML: this.dom.editorArea?.innerHTML || '' };
        content.querySelectorAll?.('mark.search-match')?.forEach(mark => mark.replaceWith(...mark.childNodes));
        this.activeNote.content = content.innerHTML;
        this.updateWordAndCharMetrics();
        this.triggerXpBurst();
        this.saveDraftLocal();
        this.scheduleAutoSave();

        if (!this.isPerformingHistory) {
            clearTimeout(this.historyDebounce);
            this.historyDebounce = setTimeout(() => {
                this.pushUndoSnapshot();
            }, 300);
        }
    }

    /* ---------------- UNDO / REDO HISTORY ENGINE ---------------- */
    initHistory(initialContent = '') {
        this.undoStack = [initialContent];
        this.redoStack = [];
        this.isPerformingHistory = false;
    }

    pushUndoSnapshot() {
        if (this.isPerformingHistory || !this.dom.editorArea) return;
        const current = this.dom.editorArea.innerHTML;
        if (!this.undoStack || this.undoStack.length === 0) {
            this.undoStack = [current];
            this.redoStack = [];
            return;
        }
        if (this.undoStack[this.undoStack.length - 1] !== current) {
            this.undoStack.push(current);
            if (this.undoStack.length > (this.maxHistory || 80)) {
                this.undoStack.shift();
            }
            this.redoStack = [];
        }
    }

    undo() {
        if (this.isPerformingHistory) return;
        if (this.isPlainTextMode && this.dom.plainTextEditor) {
            this.dom.plainTextEditor.focus?.();
            try {
                document.execCommand('undo', false, null);
            } catch (_) {}
            this.updatePlainTextStats?.();
            return;
        }
        if (this.isRawMarkdownMode && this.dom.markdownRawEditor) {
            this.dom.markdownRawEditor.focus?.();
            try {
                document.execCommand('undo', false, null);
            } catch (_) {}
            return;
        }
        if (!this.dom.editorArea) return;
        if (!this.undoStack) this.undoStack = [];
        if (!this.redoStack) this.redoStack = [];

        const current = this.dom.editorArea.innerHTML;
        if (this.undoStack.length > 0 && this.undoStack[this.undoStack.length - 1] !== current) {
            this.undoStack.push(current);
        }

        if (this.undoStack.length > 1) {
            this.isPerformingHistory = true;
            const currentState = this.undoStack.pop();
            this.redoStack.push(currentState);
            const targetState = this.undoStack[this.undoStack.length - 1];
            this.dom.editorArea.innerHTML = targetState;
            this.handleEditorInput();
            this.audio?.playClick?.();
            this.toast?.show?.('Perubahan dibatalkan (Undo)', 'info', 'Undo', 'fa-solid fa-rotate-left text-cyan-400');
            this.isPerformingHistory = false;
        } else {
            try {
                document.execCommand('undo', false, null);
            } catch (_) {}
        }
        this.dom.editorArea.focus?.();
    }

    redo() {
        if (this.isPerformingHistory) return;
        if (this.isPlainTextMode && this.dom.plainTextEditor) {
            this.dom.plainTextEditor.focus?.();
            try {
                document.execCommand('redo', false, null);
            } catch (_) {}
            this.updatePlainTextStats?.();
            return;
        }
        if (this.isRawMarkdownMode && this.dom.markdownRawEditor) {
            this.dom.markdownRawEditor.focus?.();
            try {
                document.execCommand('redo', false, null);
            } catch (_) {}
            return;
        }
        if (!this.dom.editorArea) return;
        if (!this.undoStack) this.undoStack = [];
        if (!this.redoStack) this.redoStack = [];

        if (this.redoStack.length > 0) {
            this.isPerformingHistory = true;
            const nextState = this.redoStack.pop();
            this.undoStack.push(nextState);
            this.dom.editorArea.innerHTML = nextState;
            this.handleEditorInput();
            this.audio?.playClick?.();
            this.toast?.show?.('Perubahan diulang (Redo)', 'info', 'Redo', 'fa-solid fa-rotate-right text-cyan-400');
            this.isPerformingHistory = false;
        } else {
            try {
                document.execCommand('redo', false, null);
            } catch (_) {}
        }
        this.dom.editorArea.focus?.();
    }

    updateWordAndCharMetrics() {
        if (!this.activeNote) {
            if (this.dom.wordCountBadge) this.dom.wordCountBadge.innerHTML = '<i class="fa-solid fa-pen-nib mr-1 text-blue-500"></i> 0 Words';
            if (this.dom.charCountBadge) this.dom.charCountBadge.innerHTML = '<i class="fa-solid fa-font mr-1 text-blue-500"></i> 0 Chars';
            if (this.dom.readingTimeBadge) this.dom.readingTimeBadge.innerHTML = '<i class="fa-solid fa-clock mr-1 text-cyan-500"></i> 0m Read';
            if (this.dom.questGoalText) this.dom.questGoalText.textContent = `Goal: 0/${this.wordGoal}w (0%)`;
            return;
        }

        const text = this.dom.editorArea?.innerText || '';
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
            this.dom.saveStatusText.innerHTML = '<span class="text-amber-600 dark:text-amber-400 animate-pulse font-bold"><i class="fa-solid fa-spinner fa-spin mr-1"></i> Inscribing...</span>';
        }

        clearTimeout(this.saveTimeout);
        this.saveTimeout = setTimeout(() => {
            this.saveActiveNote(false);
        }, 900);
    }

    /* ---------------- CRUD & API SYNC ---------------- */
    saveActiveNote(manual = false) {
        if (!this.activeNote) {
            if (manual) {
                this.toast.show('Pilih atau buat gulungan catatan terlebih dahulu.', 'warning');
            }
            return Promise.resolve(false);
        }
        if (this.activeNote && this.dom.editorArea && !this.isRawMarkdownMode && !this.isPlainTextMode) {
            const content = this.dom.editorArea.cloneNode(true);
            content.querySelectorAll('mark.search-match').forEach(mark => mark.replaceWith(...mark.childNodes));
            this.activeNote.content = content.innerHTML;
        } else if (this.activeNote && this.dom.markdownRawEditor && this.isRawMarkdownMode) {
            this.activeNote.content = MarkdownEngine.parse(this.dom.markdownRawEditor.value);
        } else if (this.activeNote && this.dom.plainTextEditor && this.isPlainTextMode) {
            this.activeNote.content = this.dom.plainTextEditor.value === this.plainTextOriginalText
                ? this.plainTextOriginalHtml
                : this.plainTextToHtml(this.dom.plainTextEditor.value);
        }
        return this.saveNote(this.activeNote, manual);
    }

    saveNote(note, manual = false) {
        if (!note || this.categoryMutationPending || this.deletingNoteIds?.has(note.id)) return Promise.resolve(false);
        if (this.activeNote?.id === note.id) clearTimeout(this.saveTimeout);
        const snapshot = { ...note };
        const task = this.saveQueue.then(() => this.persistNote(note, snapshot, manual));
        this.saveQueue = task.catch(() => false);
        return task;
    }

    async flushNote(note = this.activeNote) {
        while (note && this.dirtyNotes.has(note.id)) {
            if (!await this.saveNote(note)) return false;
        }
        return true;
    }

    async persistNote(note, snapshot, manual) {

        try {
            const payload = {
                title: snapshot.title || 'Catatan Baru',
                category: snapshot.category || 'catatan',
                content: snapshot.content || '',
                font_family: snapshot.font_family || 'sans',
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

                if (unchanged && this.isPlainTextMode && this.dom.plainTextEditor) {
                    this.plainTextOriginalText = this.dom.plainTextEditor.value;
                    this.plainTextOriginalHtml = note.content;
                }
                if (unchanged && this.isRawMarkdownMode && this.dom.markdownRawEditor) {
                    this.rawViewOriginalMarkdown = this.dom.markdownRawEditor.value;
                    this.rawViewOriginalHtml = note.content;
                }

                // Clear draft backup since persisted
                if (unchanged) localStorage.removeItem(`notenux_draft_${note.id}`);

                if (this.dom.saveStatusText && this.activeNote?.id === note.id && unchanged) {
                    this.dom.saveStatusText.innerHTML = '<span class="text-emerald-700 dark:text-emerald-400 font-bold"><i class="fa-solid fa-check mr-1"></i> Synchronized</span>';
                }

                if (this.dom.scrollUpdatedText && this.activeNote?.id === note.id && unchanged) {
                    this.dom.scrollUpdatedText.textContent = `Diperbarui: ${this.formatDateTime(note.updated_at)}`;
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
            if (this.dom.saveStatusText && this.activeNote?.id === note.id) {
                this.dom.saveStatusText.innerHTML = '<span class="text-rose-600 dark:text-rose-400 font-bold"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Storage error</span>';
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
            const defaultCategory = (this.activeCategory && this.activeCategory !== 'all' && this.activeCategory !== 'pinned' && this.activeCategory !== 'favorites')
                ? this.activeCategory
                : (this.categories?.find(category => category.slug === 'catatan')?.slug || this.categories?.[0]?.slug || 'catatan');

            const payload = {
                title: count === 1 ? 'Catatan Baru' : `Catatan Baru #${count}`,
                category: defaultCategory,
                is_pinned: this.activeCategory === 'pinned',
                is_favorite: this.activeCategory === 'favorites',
                content: '<p>Mulai tulis catatan Anda di sini...</p>',
                font_family: 'sans',
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
                    await this.selectNote(newNote);
                }
                this.refreshPlayerStats();

                this.toast.show(
                    `Catatan baru "${newNote.title}" berhasil dibuat (+25 XP)`,
                    'xp',
                    'Catatan Baru',
                    'fa-solid fa-note-sticky text-yellow-400'
                );
            } else {
                throw new Error(data.message || 'Could not create scroll');
            }
        } catch (e) {
            this.toast.show('Gagal membuat catatan baru.', 'danger');
        }
    }

    async togglePinActiveNote() {
        if (!this.activeNote) {
            this.toast.show('Pilih atau buat gulungan catatan terlebih dahulu.', 'warning');
            return;
        }
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
            } else {
                throw new Error(data.message || 'Could not update pin');
            }
        } catch (e) {
            this.toast.show('Error updating pin status', 'danger');
        }
    }

    async toggleFavoriteActiveNote() {
        if (!this.activeNote) {
            this.toast.show('Pilih atau buat gulungan catatan terlebih dahulu.', 'warning');
            return;
        }
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
            } else {
                throw new Error(data.message || 'Could not update favorite');
            }
        } catch (e) {
            this.toast.show('Error updating favorite status', 'danger');
        }
    }

    async duplicateActiveNote() {
        if (!this.activeNote) {
            this.toast.show('Pilih atau buat gulungan catatan terlebih dahulu.', 'warning');
            return;
        }
        const note = this.activeNote;
        if (!await this.flushNote(note)) return;
        this.audio.playCoin();

        try {
            const response = await fetch(`/api/notes/${note.id}/duplicate`, {
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
                if (this.activeNote?.id === note.id) await this.selectNote(clone);
                this.refreshPlayerStats();

                this.toast.show(
                    `Scroll duplicated as "${clone.title}"`,
                    'success',
                    'Scroll Cloned',
                    'fa-solid fa-copy text-cyan-400'
                );
            } else {
                throw new Error(data.message || 'Could not duplicate scroll');
            }
        } catch (e) {
            this.toast.show('Failed to duplicate scroll', 'danger');
        }
    }

    async exportActiveNote(format) {
        if (!this.activeNote) {
            this.toast.show('Pilih atau buat gulungan catatan terlebih dahulu.', 'warning');
            return;
        }
        const note = this.activeNote;
        if (!await this.flushNote(note)) return;
        this.audio.playCoin();
        window.location.assign(`/api/notes/${note.id}/export/${format}`);
        this.toast.show(`Exporting scroll as .${format.toUpperCase()}`, 'info', 'Export Started', 'fa-solid fa-download text-blue-400');
    }

    async archiveActiveNote() {
        if (!this.activeNote) {
            this.toast.show('Pilih atau buat gulungan catatan terlebih dahulu.', 'warning');
            return;
        }
        if (this.categories && !this.categories.some(category => category.slug === 'archive')) {
            const archiveCat = { id: 'archive', name: 'Library Vault', slug: 'archive', icon: 'fa-solid fa-box-archive', color: 'amber', is_system: true };
            this.categories.push(archiveCat);
            if (typeof this.syncCategoriesDOM === 'function') {
                this.syncCategoriesDOM();
            }
        }
        const note = this.activeNote;
        this.activeNote.category = 'archive';
        if (this.dom.noteCategorySelect) {
            this.dom.noteCategorySelect.value = 'archive';
        }
        this.updateActiveNoteInList();
        this.dirtyNotes.add(note.id);
        this.saveDraftLocal();
        if (!await this.flushNote(note)) return;
        this.toast.show(
            `Scroll "${note.title}" moved to Library Vault!`,
            'info',
            'Scroll Archived',
            'fa-solid fa-box-archive text-amber-500'
        );
    }

    openDeleteConfirmModal() {
        if (!this.activeNote) {
            this.toast.show('Pilih atau buat gulungan catatan terlebih dahulu.', 'warning');
            return;
        }

        if (this.dom.deleteModalScrollTitle) {
            this.dom.deleteModalScrollTitle.textContent = this.activeNote.title || 'Untitled Scroll';
        }

        this.pendingDeleteNoteId = this.activeNote.id;

        if (this.dom.deleteConfirmModal) {
            this.dom.deleteConfirmModal.classList.remove('hidden');
            this.audio.playBlip();
            this.dom.cancelDeleteModalBtn?.focus();
        }
    }

    closeDeleteConfirmModal() {
        this.pendingDeleteNoteId = null;
        if (this.dom.deleteConfirmModal) {
            this.dom.deleteConfirmModal.classList.add('hidden');
        }
    }

    openProfileModal() {
        if (!this.dom.profileSettingsModal) return;
        this.dom.profileSettingsModal.classList.remove('hidden');
        if (this.dom.profileFormError) {
            this.dom.profileFormError.classList.add('hidden');
            this.dom.profileFormError.textContent = '';
        }
        this.audio?.playBlip?.();
    }

    closeProfileModal() {
        if (!this.dom.profileSettingsModal) return;
        this.dom.profileSettingsModal.classList.add('hidden');
        if (this.dom.profileFormError) {
            this.dom.profileFormError.classList.add('hidden');
            this.dom.profileFormError.textContent = '';
        }
    }

    async saveProfile() {
        const nameInput = document.getElementById('profile-name-input');
        const emailInput = document.getElementById('profile-email-input');
        const curPassInput = document.getElementById('profile-current-password-input');
        const newPassInput = document.getElementById('profile-new-password-input');
        const confPassInput = document.getElementById('profile-confirm-password-input');

        if (!nameInput || !emailInput) return;

        const name = nameInput.value.trim();
        const email = emailInput.value.trim();
        const current_password = curPassInput?.value || '';
        const password = newPassInput?.value || '';
        const password_confirmation = confPassInput?.value || '';

        if (!name) {
            this.showProfileError('Nama Adventurer tidak boleh kosong.');
            return;
        }

        if (!email) {
            this.showProfileError('Email Arcane tidak boleh kosong.');
            return;
        }

        if (password && !current_password) {
            this.showProfileError('Password saat ini wajib diisi jika ingin mengubah password.');
            return;
        }

        if (password && password !== password_confirmation) {
            this.showProfileError('Konfirmasi password baru tidak cocok.');
            return;
        }

        const payload = { name, email };
        if (password) {
            payload.current_password = current_password;
            payload.password = password;
            payload.password_confirmation = password_confirmation;
        }

        const btn = this.dom.saveProfileBtn;
        const originalHtml = btn ? btn.innerHTML : '';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> MENYIMPAN...';
        }

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const res = await fetch('/profile', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            const data = await res.json();
            if (res.ok && data.status === 'success') {
                this.toast.show(data.message || 'Profil berhasil diperbarui!', 'success', 'Hero Identity');
                this.audio?.playLevelUp?.();
                if (this.dom.topNavUsername && data.data?.name) {
                    this.dom.topNavUsername.textContent = data.data.name.length > 10 ? data.data.name.substring(0, 10) + '...' : data.data.name;
                }
                if (curPassInput) curPassInput.value = '';
                if (newPassInput) newPassInput.value = '';
                if (confPassInput) confPassInput.value = '';
                this.closeProfileModal();
            } else {
                const errorMsg = data.errors ? Object.values(data.errors).flat().join(' ') : (data.message || 'Gagal memperbarui profil.');
                this.showProfileError(errorMsg);
                this.toast.show(errorMsg, 'danger', 'Gagal');
            }
        } catch (err) {
            this.showProfileError('Terjadi kesalahan koneksi saat memperbarui profil.');
            this.toast.show('Gagal menghubungi server.', 'danger', 'Error');
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        }
    }

    showProfileError(msg) {
        if (this.dom.profileFormError) {
            this.dom.profileFormError.textContent = msg;
            this.dom.profileFormError.classList.remove('hidden');
        }
    }

    async executeDeleteActiveNote() {
        const noteId = this.pendingDeleteNoteId ?? this.activeNote?.id;
        const note = this.notes.find(item => item.id === noteId);
        if (!note) return;
        this.deletingNoteIds ??= new Set();
        if (this.deletingNoteIds.has(noteId)) return;
        this.deletingNoteIds.add(noteId);
        if (this.activeNote?.id === noteId) clearTimeout(this.saveTimeout);
        await this.saveQueue;
        const title = note.title || 'Untitled Scroll';
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
                this.dirtyNotes.delete(noteId);
                localStorage.removeItem(`notenux_draft_${noteId}`);

                this.renderNotesList();
                if (this.activeNote?.id === noteId) {
                    this.activeNote = null;
                    if (this.notes.length > 0) {
                        await this.selectNote(this.notes[0]);
                    } else {
                        this.clearEditorToEmptyState();
                    }
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
        } finally {
            this.deletingNoteIds.delete(noteId);
        }
    }

    deleteActiveNote() {
        this.openDeleteConfirmModal();
    }

    async selectNote(note) {
        const version = ++this.selectionVersion;
        if (!await this.flushNote()) return;
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

    clearEditorToEmptyState() {
        this.activeNote = null;
        this.clearFindHighlights?.();
        if (this.dom.noteTitleInput) {
            this.dom.noteTitleInput.value = '';
            this.dom.noteTitleInput.placeholder = 'Belum ada catatan dipilih...';
            this.dom.noteTitleInput.readOnly = true;
        }
        if (this.dom.editorArea) {
            this.dom.editorArea.setAttribute?.('contenteditable', 'false');
            this.dom.editorArea.innerHTML = `
                <div class="flex flex-col items-center justify-center min-h-[300px] text-center text-slate-400 p-8 space-y-3 font-mono select-none">
                    <i class="fa-solid fa-scroll text-4xl text-blue-500/40"></i>
                    <p class="font-bold text-sm text-slate-600 dark:text-slate-300">Belum ada catatan.</p>
                    <p class="text-xs">Klik tombol di bawah atau tekan <kbd class="px-1.5 py-0.5 rounded bg-slate-200 dark:bg-slate-800 border text-[10px]">Ctrl+N</kbd> untuk mulai membuat catatan baru.</p>
                    <button type="button" id="empty-state-new-btn" class="arcade-btn bg-blue-600 hover:bg-blue-500 text-white font-mono text-xs px-4 py-2 rounded shadow-[2px_2px_0px_#000] cursor-pointer mt-2 inline-flex items-center gap-2">
                        <i class="fa-solid fa-plus"></i> Buat Catatan Baru
                    </button>
                </div>
            `;
            if (this.dom.editorArea.style) {
                this.dom.editorArea.style.fontFamily = "'Modern Sans', sans-serif";
                this.dom.editorArea.style.fontSize = '16px';
            }
        }
        if (this.dom.markdownRawEditor) {
            this.dom.markdownRawEditor.value = '';
        }
        if (this.dom.plainTextEditor) {
            this.dom.plainTextEditor.value = '';
        }
        if (this.dom.scrollCreatedText) {
            this.dom.scrollCreatedText.textContent = 'Dibuat: -';
        }
        if (this.dom.scrollUpdatedText) {
            this.dom.scrollUpdatedText.textContent = 'Diperbarui: -';
        }
        if (this.dom.saveStatusText) {
            this.dom.saveStatusText.innerHTML = '<span class="text-slate-400 font-bold"><i class="fa-solid fa-circle-info mr-1"></i> Siap</span>';
        }
        if (this.dom.chunkCountBadge) {
            this.dom.chunkCountBadge.innerHTML = '<i class="fa-solid fa-layer-group mr-1 text-purple-500"></i> 0 Chunks';
        }
        this.updateWordAndCharMetrics();
        this.renderNotesList();
        this.refreshPlayerStats();
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
            this.dom.noteTitleInput.placeholder = 'Judul catatan...';
            this.dom.noteTitleInput.readOnly = false;
        }

        if (this.dom.noteCategorySelect) {
            this.dom.noteCategorySelect.value = note.category || 'catatan';
        }

        if (this.dom.editorArea) {
            this.dom.editorArea.setAttribute?.('contenteditable', 'true');
            this.dom.editorArea.innerHTML = sanitizeHtml(note.content || '');
            this.initHistory(this.dom.editorArea.innerHTML);
        }

        if (this.dom.markdownRawEditor) {
            this.dom.markdownRawEditor.value = MarkdownEngine.toMarkdown(note.content || '');
            this.rawViewOriginalMarkdown = this.dom.markdownRawEditor.value;
            this.rawViewOriginalHtml = this.dom.editorArea.innerHTML;
        }

        if (this.dom.plainTextEditor) {
            const plain = this.htmlToPlainText(note.content || '');
            this.dom.plainTextEditor.value = plain;
            this.plainTextOriginalText = plain;
            this.plainTextOriginalHtml = this.dom.editorArea ? this.dom.editorArea.innerHTML : (note.content || '');
            this.updatePlainTextStats();
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
            this.dom.chunkCountBadge.innerHTML = `<i class="fa-solid fa-cubes mr-1 text-cyan-700 dark:text-cyan-400"></i> ${count} Chunks`;
        }

        if (this.dom.saveStatusText) {
            this.dom.saveStatusText.innerHTML = '<span class="text-emerald-700 dark:text-emerald-400 font-bold"><i class="fa-solid fa-circle-check mr-1"></i> Synchronized</span>';
        }

        if (this.dom.scrollCreatedText) {
            this.dom.scrollCreatedText.textContent = `Dibuat: ${this.formatDateTime(note.created_at)}`;
        }
        if (this.dom.scrollUpdatedText) {
            this.dom.scrollUpdatedText.textContent = `Diperbarui: ${this.formatDateTime(note.updated_at)}`;
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

    getFilteredNotes() {
        let filtered = [...this.notes];

        if (this.activeCategory === 'pinned') {
            filtered = filtered.filter(n => Boolean(n.is_pinned));
        } else if (this.activeCategory === 'favorites') {
            filtered = filtered.filter(n => Boolean(n.is_favorite));
        } else if (this.activeCategory !== 'all') {
            filtered = filtered.filter(n => n.category === this.activeCategory);
        }

        if (this.searchQuery) {
            filtered = filtered.filter(n =>
                this.searchResultIds?.has(n.id) ||
                ((n.title || '').toLowerCase().includes(this.searchQuery)) ||
                (n.content && n.content.toLowerCase().includes(this.searchQuery))
            );
        }

        return filtered;
    }

    /* ---------------- RENDER NOTES LIST ---------------- */
    renderNotesList() {
        if (!this.dom.notesList) return;

        let filtered = this.getFilteredNotes();

        filtered.sort((a, b) => {
            if (a.is_pinned && !b.is_pinned) return -1;
            if (!a.is_pinned && b.is_pinned) return 1;

            switch (this.currentSort) {
                case 'title_asc':
                    return (a.title || '').localeCompare(b.title || '');
                case 'title_desc':
                    return (b.title || '').localeCompare(a.title || '');
                case 'word_count_desc':
                    return (b.word_count || 0) - (a.word_count || 0);
                case 'level_desc':
                    return (b.level || 1) - (a.level || 1);
                case 'created_desc':
                    return new Date(b.created_at || 0) - new Date(a.created_at || 0);
                case 'created_asc':
                    return new Date(a.created_at || 0) - new Date(b.created_at || 0);
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
                    [ TIDAK ADA CATATAN ]<br>
                    <span class="text-[10px] opacity-75">Klik + Scroll untuk membuat catatan baru</span>
                </div>
            `;
            return;
        }

        this.dom.notesList.innerHTML = filtered.map(note => {
            const isActive = this.activeNote && this.activeNote.id === note.id;
            const isSelected = this.selectedNoteIds?.has(note.id) ?? false;
            const categoryBadge = this.getCategoryBadge(note.category);
            const chunkCount = note.chunks ? note.chunks.length : (note.chunks_count || 1);
            const badgeClasses = isActive
                ? 'bg-black/30 text-white border-white/50'
                : categoryBadge.classes;

            const cardClasses = (this.isBulkSelectNotes && isSelected)
                ? 'bg-rose-50 dark:bg-rose-950/40 border-rose-500 ring-2 ring-rose-500 shadow-[3px_3px_0px_#000] text-main'
                : (isActive
                    ? 'bg-blue-600 text-white border-black dark:border-cyan-400 shadow-[3px_3px_0px_#000]'
                    : 'bg-surface hover:bg-slate-100 dark:hover:bg-blue-900/40 border-black/20 dark:border-blue-900/60 shadow-[2px_2px_0px_rgba(0,0,0,0.15)] text-main');

            const checkboxHtml = this.isBulkSelectNotes ? `
                <div class="note-checkbox-wrapper mr-2 shrink-0 flex items-center">
                    <input type="checkbox"
                           class="note-select-checkbox cursor-pointer accent-rose-600 w-4 h-4 rounded"
                           data-note-id="${note.id}"
                           ${isSelected ? 'checked' : ''}
                           title="Pilih gulungan ${this.escapeHtml(note.title)}">
                </div>
            ` : '';

            return `
                <div data-note-id="${note.id}"
                     class="note-list-card cursor-pointer p-3 rounded-lg border-2 transition-all mb-2 select-none ${cardClasses}">
                    <div class="flex items-center justify-between gap-1 mb-1">
                        <div class="flex items-center gap-1.5 overflow-hidden min-w-0">
                            ${checkboxHtml}
                            ${note.is_pinned ? '<i title="Pinned Scroll" class="fa-solid fa-thumbtack text-amber-500 dark:text-amber-400 text-xs shrink-0"></i>' : ''}
                            ${note.is_favorite ? '<i title="Favorite Scroll" class="fa-solid fa-star text-amber-500 dark:text-yellow-400 text-xs shrink-0"></i>' : ''}
                            <h4 class="font-bold text-sm truncate ${isActive && !this.isBulkSelectNotes ? 'text-white' : 'text-main'}">
                                ${this.escapeHtml(note.title)}
                            </h4>
                        </div>
                        <span class="text-[10px] font-mono shrink-0 px-1.5 py-0.5 rounded border border-black/30 flex items-center gap-1 ${badgeClasses}">
                            <i class="${this.escapeHtml(categoryBadge.icon)}"></i>
                            ${this.escapeHtml(categoryBadge.label)}
                        </span>
                    </div>

                    <div class="mt-2 pt-1 border-t ${isActive && !this.isBulkSelectNotes ? 'border-white/20 text-blue-100' : 'border-black/10 dark:border-white/10 text-slate-500 dark:text-slate-400'} text-[10px] font-mono flex flex-col gap-1">
                        <div class="flex items-center justify-between text-[11px]">
                            <span><i class="fa-solid fa-cubes text-[10px] mr-1"></i>${chunkCount} chunks</span>
                            <span>Lv.${note.level || 1} • ${note.word_count || 0}w</span>
                        </div>
                        <div class="flex items-center justify-between text-[9px] opacity-80 pt-0.5 border-t border-dashed ${isActive && !this.isBulkSelectNotes ? 'border-white/20 text-white/90' : 'border-black/10 dark:border-white/10 text-slate-600 dark:text-slate-400'}">
                            <span class="truncate scroll-created-meta" title="Waktu Pembuatan: ${this.formatDateTime(note.created_at)}">
                                <i class="fa-regular fa-calendar-plus mr-0.5 text-blue-500 dark:text-cyan-400"></i>Dibuat: ${this.formatDateCompact(note.created_at)}
                            </span>
                            <span class="truncate ml-1 text-right scroll-updated-meta" title="Terakhir Diperbarui: ${this.formatDateTime(note.updated_at)}">
                                <i class="fa-regular fa-clock mr-0.5 text-emerald-600 dark:text-emerald-400"></i>Update: ${this.formatDateCompact(note.updated_at)}
                            </span>
                        </div>
                    </div>
                </div>
            `;
        }).join('');

        this.dom.notesList.querySelectorAll('.note-list-card').forEach(card => {
            card.addEventListener('click', () => {
                const id = parseInt(card.dataset.noteId, 10);
                if (this.isBulkSelectNotes) {
                    this.toggleSelectNote(id);
                    return;
                }
                const note = this.notes.find(n => n.id === id);
                if (note) {
                    this.selectNote(note);
                }
            });

            card.querySelector('.note-select-checkbox')?.addEventListener('click', (e) => {
                e.stopPropagation();
                const id = parseInt(card.dataset.noteId, 10);
                this.toggleSelectNote(id);
            });
        });
    }

    /* ---------------- BULK SELECT & DELETE SCROLLS ---------------- */
    toggleBulkSelectNotesMode(forceState) {
        this.isBulkSelectNotes = typeof forceState === 'boolean' ? forceState : !this.isBulkSelectNotes;
        this.audio?.playClick?.();

        if (this.isBulkSelectNotes) {
            this.dom.notesBulkBar?.classList.remove('hidden');
            if (this.dom.bulkToggleLabel) this.dom.bulkToggleLabel.textContent = 'BATAL';
            this.dom.toggleBulkSelectNotesBtn?.classList.add('bg-blue-600', 'text-white', 'border-blue-700');
        } else {
            this.dom.notesBulkBar?.classList.add('hidden');
            if (this.dom.bulkToggleLabel) this.dom.bulkToggleLabel.textContent = 'PILIH';
            this.dom.toggleBulkSelectNotesBtn?.classList.remove('bg-blue-600', 'text-white', 'border-blue-700');
            if (this.selectedNoteIds) {
                this.selectedNoteIds.clear();
            } else {
                this.selectedNoteIds = new Set();
            }
            if (this.dom.bulkSelectAllNotesCb) this.dom.bulkSelectAllNotesCb.checked = false;
        }
        this.updateBulkNotesUI();
        this.renderNotesList();
    }

    toggleSelectNote(noteId) {
        this.selectedNoteIds ??= new Set();
        const id = Number(noteId);
        if (this.selectedNoteIds.has(id)) {
            this.selectedNoteIds.delete(id);
        } else {
            this.selectedNoteIds.add(id);
        }
        this.audio?.playClick?.();
        this.updateBulkNotesUI();
        this.renderNotesList();
    }

    selectAllFilteredNotes(checked = true) {
        this.selectedNoteIds ??= new Set();
        const filtered = this.getFilteredNotes();
        if (checked) {
            filtered.forEach(n => this.selectedNoteIds.add(n.id));
        } else {
            filtered.forEach(n => this.selectedNoteIds.delete(n.id));
        }
        this.audio?.playBlip?.();
        this.updateBulkNotesUI();
        this.renderNotesList();
    }

    updateBulkNotesUI() {
        const count = this.selectedNoteIds?.size || 0;
        if (this.dom.bulkSelectedNotesCount) {
            this.dom.bulkSelectedNotesCount.textContent = `${count} dipilih`;
        }
        if (this.dom.bulkDeleteNotesBtn) {
            this.dom.bulkDeleteNotesBtn.disabled = count === 0;
        }
        if (this.dom.bulkPinNotesBtn) {
            this.dom.bulkPinNotesBtn.disabled = count === 0;
        }
        if (this.dom.bulkFavNotesBtn) {
            this.dom.bulkFavNotesBtn.disabled = count === 0;
        }
        if (this.dom.bulkMoveCategorySelect) {
            this.dom.bulkMoveCategorySelect.disabled = count === 0;
            if (count === 0) {
                this.dom.bulkMoveCategorySelect.value = '';
            }
        }
        if (this.dom.bulkSelectAllNotesCb) {
            const filtered = this.getFilteredNotes();
            this.dom.bulkSelectAllNotesCb.checked = filtered.length > 0 && filtered.every(n => this.selectedNoteIds?.has(n.id));
        }
    }

    openBulkDeleteNotesModal() {
        if (!this.selectedNoteIds || this.selectedNoteIds.size === 0) return;
        if (this.dom.bulkDeleteNotesCountBadge) {
            this.dom.bulkDeleteNotesCountBadge.textContent = `${this.selectedNoteIds.size} Gulungan Terpilih`;
        }
        this.audio?.playBlip?.();
        this.dom.bulkDeleteNotesModal?.classList.remove('hidden');
    }

    closeBulkDeleteNotesModal() {
        this.dom.bulkDeleteNotesModal?.classList.add('hidden');
    }

    async executeBulkDeleteNotes() {
        const idsToDelete = Array.from(this.selectedNoteIds || []);
        if (idsToDelete.length === 0) return;

        this.closeBulkDeleteNotesModal();
        this.audio?.playDelete?.();

        clearTimeout(this.saveTimeout);
        await this.saveQueue;

        try {
            const response = await fetch('/api/notes/bulk-delete', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ ids: idsToDelete }),
            });

            const data = await response.json();

            if (response.ok && data.status === 'success') {
                const deletedSet = new Set(data.data?.deleted_ids || idsToDelete);

                this.notes = this.notes.filter(n => !deletedSet.has(n.id));

                deletedSet.forEach(id => {
                    this.dirtyNotes.delete(id);
                    if (typeof localStorage !== 'undefined') {
                        localStorage.removeItem(`notenux_draft_${id}`);
                    }
                });

                if (this.activeNote && deletedSet.has(this.activeNote.id)) {
                    this.activeNote = null;
                    if (this.notes.length > 0) {
                        await this.selectNote(this.notes[0]);
                    } else {
                        this.clearEditorToEmptyState();
                    }
                }

                this.selectedNoteIds.clear();
                this.toggleBulkSelectNotesMode(false);
                this.refreshPlayerStats();
                this.toast.show(
                    `Berhasil memusnahkan ${deletedSet.size} gulungan catatan ke digital void!`,
                    'danger',
                    'Scrolls Banished',
                    'fa-solid fa-skull-crossbones text-rose-500'
                );
            } else {
                throw new Error(data.message || 'Gagal menghapus beberapa catatan.');
            }
        } catch (e) {
            this.toast.show(e.message || 'Network error during bulk delete.', 'danger');
        }
    }

    async executeBulkUpdateNotes(updates, successMsg = 'Scrolls updated') {
        const ids = Array.from(this.selectedNoteIds || []);
        if (ids.length === 0) return;

        this.audio?.playCoin?.();

        try {
            const response = await fetch('/api/notes/bulk-update', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ ids, ...updates }),
            });

            const data = await response.json();

            if (response.ok && data.status === 'success') {
                const idSet = new Set(ids);
                this.notes.forEach(note => {
                    if (idSet.has(note.id)) {
                        Object.assign(note, updates);
                    }
                });

                if (this.activeNote && idSet.has(this.activeNote.id)) {
                    Object.assign(this.activeNote, updates);
                    if (this.dom.pinBtn && 'is_pinned' in updates) {
                        this.dom.pinBtn.classList.toggle('text-amber-500', Boolean(this.activeNote.is_pinned));
                    }
                    if (this.dom.favoriteBtn && 'is_favorite' in updates) {
                        this.dom.favoriteBtn.classList.toggle('text-yellow-400', Boolean(this.activeNote.is_favorite));
                    }
                    if (this.dom.noteCategorySelect && 'category' in updates) {
                        this.dom.noteCategorySelect.value = this.activeNote.category;
                    }
                    if (this.dom.fontFamilySelect && 'font_family' in updates) {
                        this.dom.fontFamilySelect.value = updates.font_family;
                        this.applyFontFamily(updates.font_family);
                    }
                    if (this.dom.fontSizeSelect && 'font_size' in updates) {
                        this.dom.fontSizeSelect.value = updates.font_size;
                        this.applyFontSize(updates.font_size);
                    }
                }

                this.renderNotesList();
                this.syncCategoriesDOM();
                this.toast?.show?.(data.message || successMsg, 'success', 'Bulk Action', 'fa-solid fa-wand-magic-sparkles text-cyan-400');
            } else {
                this.toast?.show?.(data.message || 'Gagal memperbarui gulungan.', 'danger', 'Gagal');
            }
        } catch {
            this.toast?.show?.('Kesalahan jaringan saat memperbarui gulungan.', 'danger', 'Koneksi Gagal');
        }
    }

    async executeBulkMoveCategory(targetCategory) {
        const ids = Array.from(this.selectedNoteIds || []);
        if (ids.length === 0 || !targetCategory) return;

        const catObj = this.categories?.find(c => c.slug === targetCategory);
        const catName = catObj ? catObj.name : targetCategory;

        await this.executeBulkUpdateNotes(
            { category: targetCategory },
            `${ids.length} gulungan dipindahkan ke kategori "${catName}"!`
        );
    }

    async executeBulkPinNotes() {
        const ids = Array.from(this.selectedNoteIds || []);
        if (ids.length === 0) return;

        const selectedNotes = this.notes.filter(n => this.selectedNoteIds.has(n.id));
        const anyUnpinned = selectedNotes.some(n => !n.is_pinned);
        const targetPinned = anyUnpinned;

        await this.executeBulkUpdateNotes(
            { is_pinned: targetPinned },
            targetPinned ? `Menyematkan ${ids.length} gulungan!` : `Melepaskan sematan ${ids.length} gulungan.`
        );
    }

    async executeBulkFavoriteNotes() {
        const ids = Array.from(this.selectedNoteIds || []);
        if (ids.length === 0) return;

        const selectedNotes = this.notes.filter(n => this.selectedNoteIds.has(n.id));
        const anyUnfav = selectedNotes.some(n => !n.is_favorite);
        const targetFav = anyUnfav;

        await this.executeBulkUpdateNotes(
            { is_favorite: targetFav },
            targetFav ? `Menandai ${ids.length} gulungan sebagai favorit!` : `Menghapus ${ids.length} gulungan dari favorit.`
        );
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
            case 'catatan':
                return { label: 'CATATAN', icon: 'fa-solid fa-note-sticky', classes: colorMap.blue };
            case 'tugas':
                return { label: 'TUGAS', icon: 'fa-solid fa-list-check', classes: colorMap.emerald };
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
    async runCategoryMutation(callback) {
        if (this.categoryRequestPending) return;
        this.categoryRequestPending = true;
        try {
            for (const note of this.notes) {
                if (!await this.flushNote(note)) return;
            }
            await this.saveQueue;
            this.categoryMutationPending = true;
            return await callback();
        } finally {
            this.categoryMutationPending = false;
            this.categoryRequestPending = false;
            for (const note of this.notes) {
                if (this.dirtyNotes.has(note.id)) this.saveNote(note);
            }
        }
    }

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
        const targetNote = this.activeNote;
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
            await this.runCategoryMutation(async () => {
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
                if (targetNote && this.notes.some(note => note.id === targetNote.id)) {
                    targetNote.category = newCat.slug;
                    this.dirtyNotes.add(targetNote.id);
                    if (this.activeNote?.id === targetNote.id) this.saveDraftLocal();
                }
                this.syncCategoriesDOM();
                this.renderNotesList();
                this.closeQuickCategoryModals();
                this.audio.playLevelUp();
                this.toast.show(`Category "${newCat.name}" forged successfully!`, 'success', 'Category Created');
            } else {
                const errorMsg = data.message || 'Gagal membuat kategori.';
                this.toast.show(errorMsg, 'danger', 'Forge Failed');
            }
            });
        } catch (e) {
            this.toast.show('Network error during category creation.', 'danger');
        }
    }

    openQuickEditCategoryModal(slug = null) {
        this.closeQuickCategoryModals();
        const targetSlug = slug || this.dom.noteCategorySelect?.value || this.activeNote?.category || 'catatan';
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
            await this.runCategoryMutation(async () => {
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
            });
        } catch (e) {
            this.toast.show('Network error during category update.', 'danger');
        }
    }

    openQuickDeleteCategoryModal(slug = null) {
        this.closeQuickCategoryModals();
        const targetSlug = slug || this.dom.noteCategorySelect?.value || this.activeNote?.category || 'catatan';
        const category = this.categories.find(c => c.slug === targetSlug);

        if (!category) {
            this.toast.show('Kategori tidak ditemukan.', 'warning');
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
            await this.runCategoryMutation(async () => {
            const response = await fetch(`/api/categories/${id}`, {
                method: 'DELETE',
            });

            const data = await response.json();
            if (response.ok) {
                const deletedCount = data.data?.deleted_notes_count ?? 0;

                // Remove from in-memory categories
                this.categories = this.categories.filter(c => String(c.id) !== String(id));

                // Remove all notes in this category from in-memory notes & clean up drafts
                if (deletedSlug) {
                    const deletedNotes = this.notes.filter(n => n.category === deletedSlug);
                    deletedNotes.forEach(n => {
                        this.dirtyNotes.delete(n.id);
                        localStorage.removeItem(`notenux_draft_${n.id}`);
                    });
                    this.notes = this.notes.filter(note => note.category !== deletedSlug);

                    if (this.activeCategory === deletedSlug) {
                        this.activeCategory = 'all';
                    }

                    if (this.activeNote && this.activeNote.category === deletedSlug) {
                        this.activeNote = null;
                        if (this.notes.length > 0) {
                            await this.selectNote(this.notes[0]);
                        } else {
                            this.clearEditorToEmptyState();
                        }
                    }
                }

                this.syncCategoriesDOM();
                this.renderNotesList();
                this.refreshPlayerStats();
                const notesMsg = deletedCount > 0 ? ` and ${deletedCount} associated scroll${deletedCount === 1 ? '' : 's'} were banished.` : ' banished.';
                this.toast.show(`Category "${deletedName}"${notesMsg}`, 'success', 'Category Banished');
            } else {
                const errorMsg = data.message || 'Gagal menghapus kategori.';
                this.toast.show(errorMsg, 'danger', 'Delete Failed');
            }
            });
        } catch (e) {
            this.toast.show('Network error during category deletion.', 'danger');
        }
    }

    syncCategoriesDOM() {
        // Sync note category dropdown options
        if (this.dom.noteCategorySelect) {
            const currentVal = this.activeNote?.category || this.dom.noteCategorySelect.value;
            this.dom.noteCategorySelect.innerHTML = this.categories.map(cat =>
                `<option value="${this.escapeHtml(cat.slug)}" data-id="${cat.id}">[${this.escapeHtml((cat.slug || '').toUpperCase())}] ${this.escapeHtml(cat.name)}</option>`
            ).join('');
            if (currentVal && this.categories.some(c => c.slug === currentVal)) {
                this.dom.noteCategorySelect.value = currentVal;
            } else if (currentVal) {
                const option = document.createElement('option');
                option.value = currentVal;
                option.textContent = currentVal;
                this.dom.noteCategorySelect.appendChild(option);
                this.dom.noteCategorySelect.value = currentVal;
            }
        }

        // Sync import category dropdown options
        if (this.dom.importCategorySelect) {
            const currentValue = this.dom.importCategorySelect.value;
            this.dom.importCategorySelect.innerHTML = this.categories.map(cat =>
                `<option value="${this.escapeHtml(cat.slug)}">${this.escapeHtml(cat.name)}</option>`
            ).join('');
            if (this.categories.some(category => category.slug === currentValue)) this.dom.importCategorySelect.value = currentValue;
        }

        // Sync bulk move category dropdown options
        if (this.dom.bulkMoveCategorySelect) {
            const options = (this.categories || []).map(cat =>
                `<option value="${this.escapeHtml(cat.slug)}">${this.escapeHtml(cat.name)}</option>`
            ).join('');
            this.dom.bulkMoveCategorySelect.innerHTML = `<option value="">PINDAH KATEGORI...</option>${options}`;
        }

        // Sync filter pills row
        if (this.dom.categoryFilterPillsRow) {
            let pillsHtml = `
                <button class="category-filter-btn px-2 py-0.5 rounded border border-black/40 ${this.activeCategory === 'all' ? 'bg-blue-600 text-white active-category' : 'bg-surface'} whitespace-nowrap shadow-[1px_1px_0px_#000]"
                        data-category="all">
                    ALL
                </button>
                <button class="category-filter-btn px-2 py-0.5 rounded border border-black/40 ${this.activeCategory === 'pinned' ? 'bg-blue-600 text-white active-category' : 'bg-surface hover:bg-amber-100 dark:hover:bg-amber-950/60'} text-amber-700 dark:text-amber-400 whitespace-nowrap shadow-[1px_1px_0px_#000]"
                        data-category="pinned"
                        title="Filter hanya catatan yang disematkan (Pinned)">
                    <i class="fa-solid fa-thumbtack mr-1"></i>PINNED
                </button>
                <button class="category-filter-btn px-2 py-0.5 rounded border border-black/40 ${this.activeCategory === 'favorites' ? 'bg-blue-600 text-white active-category' : 'bg-surface hover:bg-yellow-100 dark:hover:bg-yellow-950/60'} text-yellow-700 dark:text-yellow-400 whitespace-nowrap shadow-[1px_1px_0px_#000]"
                        data-category="favorites"
                        title="Filter hanya catatan favorit (Favorites)">
                    <i class="fa-solid fa-star mr-1"></i>FAV
                </button>
            `;

            this.categories.forEach(cat => {
                const isActive = this.activeCategory === cat.slug;
                pillsHtml += `
                    <button class="category-filter-btn px-2 py-0.5 rounded border border-black/40 ${isActive ? 'bg-blue-600 text-white active-category' : 'bg-surface hover:bg-blue-200 dark:hover:bg-blue-900'} whitespace-nowrap shadow-[1px_1px_0px_#000]"
                            data-category="${this.escapeHtml(cat.slug)}">
                        <i class="${this.escapeHtml(cat.icon || 'fa-solid fa-folder')} mr-1"></i>${this.escapeHtml((cat.name || cat.slug).slice(0, 10).toUpperCase())}
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
                     data-category="${this.escapeHtml(cat.slug)}">
                    <a href="#" class="nav-category-link flex-1 flex items-center gap-2.5 px-3 py-2 rounded-lg truncate ${this.activeCategory === cat.slug ? 'bg-blue-600 text-white font-bold' : ''}"
                       data-category="${this.escapeHtml(cat.slug)}"
                       title="${this.escapeHtml(cat.name)}">
                        <i class="${this.escapeHtml(cat.icon || 'fa-solid fa-folder')} text-sm w-4 text-center shrink-0"></i>
                        <span class="nav-text-label font-bold truncate">${this.escapeHtml(cat.name)}</span>
                    </a>
                    <div class="hidden group-hover:flex items-center gap-1 shrink-0">
                        <button type="button"
                                class="sidebar-edit-category-btn text-[10px] text-cyan-600 dark:text-cyan-400 hover:scale-110 transition-transform p-1"
                                data-slug="${this.escapeHtml(cat.slug)}"
                                title="Edit Category">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                        <button type="button"
                                class="sidebar-delete-category-btn text-[10px] text-rose-500 hover:scale-110 transition-transform p-1"
                                data-slug="${this.escapeHtml(cat.slug)}"
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
        if (!this.activeNote) {
            this.toast.show('Pilih atau buat gulungan catatan terlebih dahulu.', 'warning');
            return;
        }
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
                    this.dom.chunkModalCount.innerHTML = `<i class="fa-solid fa-cubes mr-1 text-blue-600 dark:text-cyan-400"></i> ${data.total_chunks} Total Chunks`;
                }
                if (this.dom.chunkModalBytes) {
                    this.dom.chunkModalBytes.innerHTML = `<i class="fa-solid fa-database mr-1 text-blue-600 dark:text-blue-400"></i> ${data.total_bytes} Bytes Stored`;
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
                        <button onclick="window.notenux.confirmDeleteChunk(${chunk.id}, this)" class="arcade-btn bg-red-600 text-white px-2 py-0.5 text-[10px] rounded hover:bg-red-500" title="Delete Shard">
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
        if (!this.activeNote || this.chunkMutationPending || !this.cachedChunks || this.cachedChunks.length <= 1) return;
        const note = this.activeNote;
        const newIndex = index + direction;
        if (newIndex < 0 || newIndex >= this.cachedChunks.length) return;

        this.audio.playClick();
        const reordered = [...this.cachedChunks];
        const item = reordered.splice(index, 1)[0];
        reordered.splice(newIndex, 0, item);

        const orderedIds = reordered.map(c => c.id);
        this.chunkMutationPending = true;

        try {
            if (!await this.flushNote(note)) return;
            const response = await fetch(`/api/notes/${note.id}/chunks/reorder`, {
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
                if (this.activeNote?.id === note.id) {
                    this.cachedChunks = data.data.chunks;
                    this.renderChunkCards(this.cachedChunks);
                }
                if (data.data.note) {
                    this.acceptChunkNote(note, data.data.note);
                }
            } else {
                throw new Error(data.message || 'Error reordering chunks');
            }
        } catch (e) {
            this.toast.show('Failed to reorder shards', 'danger');
        } finally {
            this.chunkMutationPending = false;
        }
    }

    confirmDeleteChunk(chunkId, button) {
        if (button.dataset.confirmed === 'true') return this.deleteChunk(chunkId);
        button.dataset.confirmed = 'true';
        button.textContent = 'Confirm delete shard';
    }

    async deleteChunk(chunkId) {
        if (!this.activeNote || this.chunkMutationPending) return;
        const note = this.activeNote;
        this.chunkMutationPending = true;

        this.audio.playDelete();
        try {
            if (!await this.flushNote(note)) return;
            const response = await fetch(`/api/notes/${note.id}/chunks/${chunkId}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                },
            });

            const data = await response.json();
            if (response.ok && data.status === 'success') {
                this.toast.show('Shard deleted and parent scroll reassembled!', 'success');
                if (data.data && data.data.note) {
                    this.acceptChunkNote(note, data.data.note);
                }
                if (this.activeNote?.id === note.id) await this.openChunkInspector();
            } else {
                throw new Error(data.message || 'Error deleting chunk');
            }
        } catch (e) {
            this.toast.show('Failed to delete shard', 'danger');
        } finally {
            this.chunkMutationPending = false;
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
        if (!this.activeNote || this.chunkMutationPending) return;
        const note = this.activeNote;
        const editEl = document.getElementById(`chunk-edit-${chunkId}`);
        const textarea = editEl?.querySelector('textarea');
        if (!textarea) return;

        const newContent = textarea.value;
        this.audio.playCoin();
        this.chunkMutationPending = true;

        try {
            if (!await this.flushNote(note)) return;
            const response = await fetch(`/api/notes/${note.id}/chunks/${chunkId}`, {
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
                if (data.data && data.data.note) {
                    this.acceptChunkNote(note, data.data.note);
                }
                if (this.activeNote?.id === note.id) await this.openChunkInspector();
            } else {
                throw new Error(data.message || 'Error updating chunk');
            }
        } catch (e) {
            this.toast.show('Failed to save individual chunk', 'danger');
        } finally {
            this.chunkMutationPending = false;
        }
    }

    acceptChunkNote(note, saved) {
        if (this.dirtyNotes.has(note.id)) {
            this.toast.show('Shard tersimpan. Draft baru Anda tetap dipertahankan.', 'warning');
            return;
        }
        Object.assign(note, saved);
        if (!saved.chunks) delete note.chunks;
        if (this.activeNote?.id === note.id) this.loadNoteIntoEditor(note);
        this.renderNotesList();
        this.refreshPlayerStats();
    }

    async verifyChunksIntegrity() {
        if (!this.activeNote) {
            this.toast.show('Pilih atau buat gulungan catatan terlebih dahulu.', 'warning');
            return;
        }
        const noteId = this.activeNote.id;
        this.audio.playClick();

        if (this.dom.chunkVerifyStatus) {
            this.dom.chunkVerifyStatus.className = 'px-4 py-2 text-xs font-mono bg-blue-100 dark:bg-blue-900/50 text-blue-900 dark:text-cyan-300 border-b border-blue-400';
            this.dom.chunkVerifyStatus.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> Running cryptographic SHA-256 integrity verification...';
        }

        try {
            const response = await fetch(`/api/notes/${noteId}/chunks/verify`);
            const data = await response.json();
            if (this.activeNote?.id !== noteId) return;

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
            } else {
                throw new Error(data.message || 'Integrity verification failed');
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
                const collapsedBadge = document.querySelector('.collapsed-level-badge');
                if (collapsedBadge) collapsedBadge.textContent = `LV.${s.level}`;
                if (this.dom.playerRankTitle) {
                    this.dom.playerRankTitle.innerHTML = `<i class="fa-solid ${s.rank_badge} text-amber-600 dark:text-yellow-400 mr-1.5"></i> ${s.rank_title}`;
                }
                if (this.dom.playerXpText) this.dom.playerXpText.textContent = `${s.total_xp} XP`;
                if (this.dom.playerXpBar) this.dom.playerXpBar.style.width = `${s.progress_percent}%`;
                if (this.dom.playerManaText) this.dom.playerManaText.textContent = s.mana;
            }
        } catch (e) {
            // Background telemetry
        }
    }

    formatDateTime(dateStr) {
        if (!dateStr) return '-';
        try {
            const date = new Date(dateStr);
            if (isNaN(date.getTime())) return '-';
            const day = String(date.getDate()).padStart(2, '0');
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
            const month = months[date.getMonth()];
            const year = date.getFullYear();
            const hours = String(date.getHours()).padStart(2, '0');
            const mins = String(date.getMinutes()).padStart(2, '0');
            return `${day} ${month} ${year}, ${hours}:${mins}`;
        } catch (_) {
            return '-';
        }
    }

    formatDateCompact(dateStr) {
        if (!dateStr) return '-';
        try {
            const date = new Date(dateStr);
            if (isNaN(date.getTime())) return '-';
            const day = String(date.getDate()).padStart(2, '0');
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
            const month = months[date.getMonth()];
            const now = new Date();
            const hours = String(date.getHours()).padStart(2, '0');
            const mins = String(date.getMinutes()).padStart(2, '0');

            if (date.getFullYear() === now.getFullYear()) {
                return `${day} ${month}, ${hours}:${mins}`;
            }
            return `${day} ${month} '${String(date.getFullYear()).slice(-2)}`;
        } catch (_) {
            return '-';
        }
    }

    escapeHtml(str) {
        return String(str ?? '').replace(/[&<>"']/g, character => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
        })[character]);
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.notenux = new NotenuxPanel();
    RetroSearchableSelect.initAll();
    RetroPasswordToggle.initAll();
});

window.RetroSearchableSelect = RetroSearchableSelect;
window.RetroPasswordToggle = RetroPasswordToggle;
window.PlainTextEngine = PlainTextEngine;
if (typeof globalThis !== 'undefined') {
    globalThis.PlainTextEngine = PlainTextEngine;
    globalThis.RetroPasswordToggle = RetroPasswordToggle;
}
