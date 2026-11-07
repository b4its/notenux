import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import test from 'node:test';
import assert from 'node:assert/strict';

const source = readFileSync(new URL('../resources/js/app.js', import.meta.url), 'utf8');

function harness(fetchHandler) {
    const storage = new Map();
    const messages = [];
    const context = vm.createContext({
        window: { fetch: fetchHandler, innerWidth: 1440 },
        document: { addEventListener() {}, querySelector: () => ({ content: 'csrf-test' }) },
        localStorage: { removeItem: key => storage.delete(key), setItem: (key, value) => storage.set(key, value) },
        clearTimeout,
        setTimeout,
    });
    vm.runInContext(source + '\nglobalThis.Panel = NotenuxPanel;', context);
    const panel = Object.create(context.Panel.prototype);
    Object.assign(panel, {
        activeNote: { id: 1, title: 'Alpha', content: 'First draft' },
        notes: [], saveQueue: Promise.resolve(), dirtyNotes: new Set([1]), selectionVersion: 0,
        dom: { saveStatusText: { innerHTML: '' } },
        toast: { show(...args) { messages.push(args); } },
        audio: { playBlip() {}, playCoin() {}, playDelete() {}, playLevelUp() {} },
        renderNotesList() {}, refreshPlayerStats() {}, loadNoteIntoEditor(note) { this.loaded = note; },
    });
    panel.notes.push(panel.activeNote);
    return { panel, storage, messages, context };
}

const response = data => ({ ok: true, json: async () => ({ status: 'success', data }) });
const deferred = () => {
    let resolve;
    const promise = new Promise(done => { resolve = done; });
    return { promise, resolve };
};

test('white-box: late save response never changes another active manuscript', async () => {
    const pending = deferred();
    const { panel } = harness(() => pending.promise);
    const first = panel.activeNote;
    const saving = panel.saveActiveNote();
    panel.activeNote = { id: 2, title: 'Beta', content: 'Second manuscript' };
    pending.resolve(response({ ...first, xp: 30 }));
    assert.equal(await saving, true);
    assert.equal(panel.activeNote.id, 2);
    assert.equal(panel.activeNote.content, 'Second manuscript');
    assert.equal(first.xp, 30);
});

test('white-box: typing during a save retains newer content and local recovery draft', async () => {
    const pending = deferred();
    const { panel, storage } = harness(() => pending.promise);
    const snapshot = { ...panel.activeNote };
    const saving = panel.saveActiveNote();
    panel.activeNote.content = 'Newer unsaved typing';
    storage.set('notenux_draft_1', 'Recovery');
    pending.resolve(response(snapshot));
    await saving;
    assert.equal(panel.activeNote.content, 'Newer unsaved typing');
    assert.equal(panel.dirtyNotes.has(1), true);
    assert.equal(storage.get('notenux_draft_1'), 'Recovery');
});

test('white-box: save requests are serialized and each carries its own snapshot and CSRF token', async () => {
    const pending = deferred();
    const calls = [];
    const { panel } = harness((url, options) => {
        calls.push({ url, options });
        return calls.length === 1 ? pending.promise : Promise.resolve(response(JSON.parse(options.body)));
    });
    const first = panel.saveActiveNote();
    panel.activeNote.content = 'Second draft';
    const second = panel.saveActiveNote();
    await Promise.resolve();
    assert.equal(calls.length, 1);
    pending.resolve(response({ id: 1, title: 'Alpha', content: 'First draft' }));
    await Promise.all([first, second]);
    assert.equal(calls.length, 2);
    assert.equal(JSON.parse(calls[0].options.body).content, 'First draft');
    assert.equal(JSON.parse(calls[1].options.body).content, 'Second draft');
    assert.equal(calls[0].options.headers['X-CSRF-TOKEN'], 'csrf-test');
    assert.equal(panel.activeNote.content, 'Second draft');
});

test('white-box: failed save blocks navigation and preserves dirty state', async () => {
    const { panel } = harness(async () => { throw new Error('Offline'); });
    await panel.selectNote({ id: 2, content: 'Beta' });
    assert.equal(panel.activeNote.id, 1);
    assert.equal(panel.dirtyNotes.has(1), true);
});

test('white-box: failed lazy load does not blank the current editor', async () => {
    const { panel } = harness(async () => ({ ok: false, json: async () => ({ message: 'Not found' }) }));
    panel.dirtyNotes.clear();
    await panel.selectNote({ id: 2, title: 'Missing' });
    assert.equal(panel.activeNote.id, 1);
    assert.equal(panel.loaded, undefined);
});

test('white-box: last selection wins when lazy responses arrive out of order', async () => {
    const pending = deferred();
    const { panel } = harness(url => url.endsWith('/2') ? pending.promise : Promise.resolve(response({ id: 3, content: 'Gamma' })));
    panel.dirtyNotes.clear();
    const older = panel.selectNote({ id: 2 });
    await panel.selectNote({ id: 3 });
    pending.resolve(response({ id: 2, content: 'Beta' }));
    await older;
    assert.equal(panel.activeNote.id, 3);
    assert.equal(panel.loaded.content, 'Gamma');
});

test('white-box: stale save metadata does not undo pin or favorite toggles', async () => {
    const pending = deferred();
    const { panel } = harness(() => pending.promise);
    const saving = panel.saveActiveNote();
    panel.activeNote.is_pinned = true;
    panel.activeNote.is_favorite = true;
    pending.resolve(response({ id: 1, title: 'Alpha', content: 'First draft', is_pinned: false, is_favorite: false }));
    await saving;
    assert.equal(panel.activeNote.is_pinned, true);
    assert.equal(panel.activeNote.is_favorite, true);
});

test('white-box: navigation flushes typing that arrives while the first save is in flight', async () => {
    const pending = deferred();
    const calls = [];
    const { panel } = harness((url, options) => {
        const payload = JSON.parse(options.body);
        calls.push(payload);
        return calls.length === 1 ? pending.promise : Promise.resolve(response({ id: 1, ...payload }));
    });
    const switching = panel.selectNote({ id: 2, content: 'Beta' });
    await Promise.resolve();
    panel.activeNote.content = 'Final typing before switch';
    pending.resolve(response({ id: 1, title: 'Alpha', content: 'First draft' }));
    await switching;
    assert.equal(calls.at(-1).content, 'Final typing before switch');
    assert.equal(panel.dirtyNotes.has(1), false);
    assert.equal(panel.activeNote.id, 2);
});

test('white-box: duplicate remains bound to its originating note while a save is pending', async () => {
    const pending = deferred();
    const calls = [];
    const { panel } = harness((url, options) => {
        calls.push(url);
        if (options.method === 'PUT') return pending.promise;
        return Promise.resolve(response({ id: 3, title: 'Alpha Copy', content: 'Alpha copy' }));
    });
    const duplicating = panel.duplicateActiveNote();
    await Promise.resolve();
    panel.activeNote = { id: 2, title: 'Beta', content: 'Beta' };
    pending.resolve(response({ id: 1, title: 'Alpha', content: 'First draft' }));
    await duplicating;
    assert.ok(calls.includes('/api/notes/1/duplicate'));
    assert.ok(!calls.includes('/api/notes/2/duplicate'));
});

test('white-box: late delete response preserves the newer selected manuscript and its dirty text', async () => {
    const pending = deferred();
    const { panel } = harness(() => pending.promise);
    const deleting = panel.executeDeleteActiveNote();
    await Promise.resolve();
    panel.activeNote = { id: 2, title: 'Beta', content: 'Unsaved Beta' };
    panel.notes.push(panel.activeNote);
    panel.dirtyNotes.add(2);
    pending.resolve({ ok: true });
    await deleting;
    assert.equal(panel.activeNote.id, 2);
    assert.equal(panel.activeNote.content, 'Unsaved Beta');
    assert.equal(panel.dirtyNotes.has(2), true);
    assert.ok(!panel.notes.some(note => note.id === 1));
});

test('white-box: delete confirmation stays bound to the manuscript named in the dialog', async () => {
    const calls = [];
    const { panel } = harness(async url => { calls.push(url); return { ok: true }; });
    panel.dom.deleteConfirmModal = { classList: { remove() {}, add() {} } };
    panel.openDeleteConfirmModal();
    panel.activeNote = { id: 2, title: 'Beta', content: 'Keep Beta' };
    panel.notes.push(panel.activeNote);
    await panel.executeDeleteActiveNote();
    assert.deepEqual(calls, ['/api/notes/1']);
    assert.equal(panel.activeNote.id, 2);
});

test('white-box: failed chunk reorder preserves the cached order and reports the error', async () => {
    const { panel, messages } = harness(async () => ({ ok: false, json: async () => ({ message: 'Invalid order' }) }));
    panel.cachedChunks = [{ id: 11 }, { id: 12 }];
    panel.dirtyNotes.clear();
    panel.audio.playClick = () => {};
    await panel.moveChunk(0, 1);
    assert.deepEqual(panel.cachedChunks.map(chunk => chunk.id), [11, 12]);
    assert.ok(messages.some(message => message[1] === 'danger'));
});

test('white-box: late chunk response updates the original cache without loading it into another editor', async () => {
    const pending = deferred();
    const { panel } = harness(() => pending.promise);
    const alpha = panel.activeNote;
    panel.cachedChunks = [{ id: 11 }, { id: 12 }];
    panel.dirtyNotes.clear();
    panel.audio.playClick = () => {};
    panel.renderChunkCards = () => {};
    const moving = panel.moveChunk(0, 1);
    panel.activeNote = { id: 2, title: 'Beta', content: 'Unsaved Beta' };
    pending.resolve(response({ chunks: [{ id: 12 }, { id: 11 }], note: { id: 1, content: 'Reordered Alpha' } }));
    await moving;
    assert.equal(panel.activeNote.id, 2);
    assert.equal(panel.loaded, undefined);
    assert.equal(alpha.content, 'Reordered Alpha');
});

test('white-box: category text escapes both HTML and attribute delimiters', () => {
    const { panel } = harness(() => {});
    assert.equal(panel.escapeHtml('<img title="x" onerror=\'y\'>&'), '&lt;img title=&quot;x&quot; onerror=&#39;y&#39;&gt;&amp;');
});

test('white-box: rejected note creation reports failure instead of silently doing nothing', async () => {
    const { panel, messages } = harness(async () => ({ ok: false, json: async () => ({ message: 'Storage unavailable' }) }));
    await panel.createNewNote();
    assert.equal(panel.notes.length, 1);
    assert.ok(messages.some(message => message[1] === 'danger'));
});

test('white-box: category mutations drain older saves and resume new drafts using the renamed slug', async () => {
    const entered = deferred();
    const release = deferred();
    const payloads = [];
    const { panel } = harness(async (url, options) => {
        const payload = JSON.parse(options.body);
        payloads.push(payload);
        return response({ id: 1, ...payload });
    });
    panel.activeNote.category = 'original';
    const mutation = panel.runCategoryMutation(async () => {
        entered.resolve();
        await release.promise;
        panel.activeNote.category = 'renamed';
    });
    await entered.promise;
    panel.activeNote.content = 'New writing during category change';
    panel.dirtyNotes.add(1);
    assert.equal(await panel.saveActiveNote(), false);
    assert.equal(payloads.length, 1);
    release.resolve();
    await mutation;
    await panel.saveQueue;
    assert.deepEqual(payloads.map(payload => payload.category), ['original', 'renamed']);
    assert.equal(payloads.at(-1).content, 'New writing during category change');
});

test('white-box: double category submission performs only one mutation', async () => {
    const entered = deferred();
    const release = deferred();
    const { panel } = harness(() => {});
    panel.dirtyNotes.clear();
    let calls = 0;
    const first = panel.runCategoryMutation(async () => { calls++; entered.resolve(); await release.promise; });
    await entered.promise;
    await panel.runCategoryMutation(async () => { calls++; });
    release.resolve();
    await first;
    assert.equal(calls, 1);
    assert.equal(panel.categoryMutationPending, false);
});

test('white-box: quick forge reports success and saves the original selected note', async () => {
    const { panel, context, messages } = harness(async (url, options) => {
        if (url === '/api/categories') return response({ id: 9, name: 'Research', slug: 'research' });
        return response({ id: 1, ...JSON.parse(options.body) });
    });
    panel.categories = [];
    panel.dirtyNotes.clear();
    context.document.getElementById = id => ({ value: id === 'quick-forge-cat-name' ? 'Research' : '' });
    panel.syncCategoriesDOM = () => {};
    await panel.submitQuickForgeCategory();
    await panel.saveQueue;
    assert.equal(panel.activeNote.category, 'research');
    assert.ok(messages.some(message => message[2] === 'Category Created'));
    assert.ok(!messages.some(message => message[1] === 'danger'));
});

test('white-box: RetroSearchableSelect wraps native select, provides search filtering and dispatches change', () => {
    let changeFired = false;
    const listeners = new Map();
    const mockSelect = {
        className: 'w-full',
        dataset: {},
        value: 'opt1',
        options: [
            { value: 'opt1', textContent: 'Quest Log' },
            { value: 'opt2', textContent: 'Ancient Lore' },
            { value: 'opt3', textContent: 'Boss Tactics' },
        ],
        querySelector(selector) {
            const val = selector.match(/value="([^"]+)"/)?.[1];
            return this.options.find(o => o.value === val);
        },
        classList: { add() {}, remove() {}, contains: () => false },
        parentNode: {
            insertBefore(newNode) { this.inserted = newNode; }
        },
        addEventListener(event, fn) {
            listeners.set(event, fn);
        },
        dispatchEvent(event) {
            if (event.type === 'change') changeFired = true;
            listeners.get(event.type)?.(event);
        }
    };

    const context = vm.createContext({
        window: {},
        document: {
            createElement: (tag) => {
                const el = {
                    tagName: tag,
                    className: '',
                    innerHTML: '',
                    children: [],
                    dataset: {},
                    classList: {
                        add(c) { this.classes = this.classes || new Set(); this.classes.add(c); },
                        remove(c) { this.classes = this.classes || new Set(); this.classes.delete(c); },
                        contains(c) { return Boolean(this.classes?.has(c)); },
                        toggle(c, state) { if (state) this.add(c); else this.remove(c); }
                    },
                    appendChild(child) { this.children.push(child); return child; },
                    querySelector(sel) {
                        if (sel === '.retro-select-label') return { textContent: '' };
                        if (sel === '.fa-chevron-down') return { classList: this.classList };
                        if (sel === '.retro-select-search-input') return { value: '', focus() {}, addEventListener() {} };
                        if (sel === '.retro-select-options-list') return { innerHTML: '', appendChild() {} };
                        if (sel === '.retro-select-no-results') return { classList: this.classList };
                        return null;
                    },
                    addEventListener() {},
                    focus() {}
                };
                return el;
            },
            querySelectorAll: () => [],
            addEventListener: () => {}
        },
        MutationObserver: class { observe() {} disconnect() {} },
        HTMLSelectElement: class {},
        Event: class { constructor(type) { this.type = type; } },
        setTimeout: (fn) => fn(),
        clearTimeout: () => {}
    });

    vm.runInContext(source + '\nglobalThis.RetroSearchableSelect = RetroSearchableSelect;', context);
    const SelectClass = context.RetroSearchableSelect;
    assert.equal(typeof SelectClass, 'function');

    const instance = new SelectClass(mockSelect);
    assert.equal(mockSelect.dataset.retroSelectInit, 'true');
    instance.selectValue('opt2');
    assert.equal(mockSelect.value, 'opt2');
    assert.equal(changeFired, true);
    mockSelect.disabled = true;
    instance.open();
    instance.selectValue('opt3');
    assert.equal(instance.isOpen, false);
    assert.equal(mockSelect.value, 'opt2');
});

test('white-box: RetroSearchableSelect filters options and toggles no-results element', () => {
    const listeners = new Map();
    const mockSelect = {
        className: 'w-full',
        dataset: {},
        value: 'opt1',
        options: [
            { value: 'opt1', textContent: 'Quest Log' },
            { value: 'opt2', textContent: 'Ancient Lore' },
            { value: 'opt3', textContent: 'Boss Tactics' },
        ],
        querySelector(selector) {
            const val = selector.match(/value="([^"]+)"/)?.[1];
            return this.options.find(o => o.value === val);
        },
        classList: { add() {}, remove() {}, contains: () => false },
        parentNode: { insertBefore() {} },
        addEventListener(event, fn) { listeners.set(event, fn); },
        dispatchEvent() {}
    };

    let noResultsHidden = true;
    const noResultsEl = {
        classList: {
            add(c) { if (c === 'hidden') noResultsHidden = true; },
            remove(c) { if (c === 'hidden') noResultsHidden = false; },
            contains(c) { return c === 'hidden' ? noResultsHidden : false; }
        }
    };
    const optionsListEl = {
        children: [],
        innerHTML: '',
        appendChild(child) { this.children.push(child); return child; }
    };

    const context = vm.createContext({
        window: { innerWidth: 1024 },
        document: {
            createElement: (tag) => {
                const el = {
                    tagName: tag,
                    className: '',
                    innerHTML: '',
                    children: [],
                    dataset: {},
                    classList: {
                        add() {}, remove() {}, contains: () => false, toggle() {}
                    },
                    appendChild(child) { this.children.push(child); return child; },
                    querySelector(sel) {
                        if (sel === '.retro-select-label') return { textContent: '' };
                        if (sel === '.fa-chevron-down') return { classList: { add() {}, remove() {} } };
                        if (sel === '.retro-select-search-input') return { value: '', focus() {}, addEventListener() {} };
                        if (sel === '.retro-select-options-list') return optionsListEl;
                        if (sel === '.retro-select-no-results') return noResultsEl;
                        return null;
                    },
                    addEventListener() {},
                    focus() {},
                    scrollIntoView() {},
                    getBoundingClientRect() { return { right: 500, width: 200 }; }
                };
                return el;
            },
            querySelectorAll: () => [],
            addEventListener: () => {}
        },
        MutationObserver: class { observe() {} disconnect() {} },
        HTMLSelectElement: class {},
        Event: class { constructor(type) { this.type = type; } },
        setTimeout: (fn) => fn(),
        clearTimeout: () => {}
    });

    vm.runInContext(source + '\nglobalThis.RetroSearchableSelect = RetroSearchableSelect;', context);
    const instance = new context.RetroSearchableSelect(mockSelect);

    // Filter with matching text
    instance.renderOptions('Ancient');
    assert.equal(instance.filteredOptions.length, 1);
    assert.equal(instance.filteredOptions[0].value, 'opt2');
    assert.equal(noResultsHidden, true);

    // Filter with non-matching text
    instance.renderOptions('Nonexistent Query');
    assert.equal(instance.filteredOptions.length, 0);
    assert.equal(noResultsHidden, false);

    // Keyboard navigation
    instance.renderOptions('');
    assert.equal(instance.filteredOptions.length, 3);
    instance.moveHighlight(1);
    assert.equal(instance.highlightIndex, 0);
    instance.moveHighlight(1);
    assert.equal(instance.highlightIndex, 1);
    instance.moveHighlight(-1);
    assert.equal(instance.highlightIndex, 0);
    mockSelect.options[1].disabled = true;
    instance.renderOptions('');
    assert.deepEqual(Array.from(instance.filteredOptions, option => option.value), ['opt1', 'opt3']);
});

test('white-box: RetroSearchableSelect positions dropdown with fixed coordinates and closes cleanly', () => {
    const mockSelect = {
        className: 'w-full',
        dataset: {},
        value: 'opt1',
        options: [
            { value: 'opt1', textContent: 'Quest Log' },
            { value: 'opt2', textContent: 'Ancient Lore' },
        ],
        querySelector(selector) {
            const val = selector.match(/value="([^"]+)"/)?.[1];
            return this.options.find(o => o.value === val);
        },
        classList: { add() {}, remove() {}, contains: () => false },
        parentNode: { insertBefore() {} },
        addEventListener() {},
        dispatchEvent() {}
    };

    const createdElements = [];
    const context = vm.createContext({
        window: {
            innerWidth: 1024,
            innerHeight: 768,
            addEventListener() {},
            removeEventListener() {}
        },
        document: {
            createElement: (tag) => {
                const el = {
                    tagName: tag,
                    className: '',
                    innerHTML: '',
                    children: [],
                    dataset: {},
                    style: {},
                    offsetHeight: 200,
                    offsetWidth: 220,
                    classList: {
                        classes: new Set(),
                        add(c) { this.classes.add(c); },
                        remove(c) { this.classes.delete(c); },
                        contains(c) { return this.classes.has(c); },
                        toggle(c, s) { if (s) this.classes.add(c); else this.classes.delete(c); }
                    },
                    appendChild(child) { this.children.push(child); return child; },
                    querySelector(sel) {
                        if (sel === '.retro-select-label') return { textContent: '' };
                        if (sel === '.fa-chevron-down') return { classList: this.classList };
                        if (sel === '.retro-select-search-input') return { value: '', focus() {}, addEventListener() {} };
                        if (sel === '.retro-select-options-list') return { innerHTML: '', appendChild() {} };
                        if (sel === '.retro-select-no-results') return { classList: this.classList };
                        return null;
                    },
                    addEventListener() {},
                    focus() {},
                    scrollIntoView() {},
                    getBoundingClientRect() {
                        return { top: 100, bottom: 130, left: 200, right: 350, width: 150, height: 30 };
                    }
                };
                createdElements.push(el);
                return el;
            },
            querySelectorAll: () => [],
            addEventListener: () => {}
        },
        MutationObserver: class { observe() {} disconnect() {} },
        HTMLSelectElement: class {},
        Event: class { constructor(type) { this.type = type; } },
        setTimeout: (fn) => fn(),
        clearTimeout: () => {}
    });

    vm.runInContext(source + '\nglobalThis.RetroSearchableSelect = RetroSearchableSelect;', context);
    const instance = new context.RetroSearchableSelect(mockSelect);

    // Open dropdown and verify fixed layout and z-index positioning
    instance.open();
    assert.equal(instance.isOpen, true);
    assert.equal(instance.dropdown.style.position, 'fixed');
    assert.equal(instance.dropdown.style.zIndex, '99999');
    assert.equal(instance.dropdown.style.top, '134px');
    assert.equal(instance.dropdown.style.left, '200px');

    // Close dropdown and verify state
    instance.close();
    assert.equal(instance.isOpen, false);
    assert.equal(instance.dropdown.classList.contains('hidden'), true);

    const secondSelect = { ...mockSelect, dataset: {} };
    const second = new context.RetroSearchableSelect(secondSelect);
    instance.open();
    second.open();
    assert.equal(instance.isOpen, false);
    assert.equal(second.isOpen, true);
    instance.toggle();
    assert.equal(instance.isOpen, true);
    assert.equal(second.isOpen, false);
});

test('white-box: MarkdownEngine parses h4, h5, and h6 markdown headings', () => {
    const context = vm.createContext({
        window: {},
        document: {
            createElement: () => ({
                querySelectorAll: () => [],
                attributes: [],
                innerHTML: '',
                content: {
                    querySelectorAll: () => []
                }
            }),
            addEventListener() {},
            querySelector() { return null; },
        }
    });
    vm.runInContext(source + '\nglobalThis.MarkdownEngine = MarkdownEngine;', context);
    const html = context.MarkdownEngine.parse('#### Fourth Heading\n\n##### Fifth Heading\n\n###### Sixth Heading');
    assert.ok(html.includes('<h4>Fourth Heading</h4>'));
    assert.ok(html.includes('<h5>Fifth Heading</h5>'));
    assert.ok(html.includes('<h6>Sixth Heading</h6>'));
});

test('white-box: renderNotesList sorts by title_desc, created_desc, and created_asc', () => {
    const { panel, context } = harness(() => Promise.resolve({ ok: true }));
    delete panel.renderNotesList;
    panel.categories = [];
    panel.notes = [
        { id: 1, title: 'B Scroll', category: 'quest', created_at: '2026-01-01T00:00:00Z', is_pinned: false },
        { id: 2, title: 'A Scroll', category: 'quest', created_at: '2026-01-03T00:00:00Z', is_pinned: false },
        { id: 3, title: 'C Scroll', category: 'quest', created_at: '2026-01-02T00:00:00Z', is_pinned: false },
    ];
    let renderedHtml = '';
    panel.dom.notesList = {
        set innerHTML(val) { renderedHtml = val; },
        get innerHTML() { return renderedHtml; },
        querySelectorAll: () => []
    };
    panel.activeCategory = 'all';
    panel.searchQuery = '';

    // Sort title_desc (C, B, A)
    panel.currentSort = 'title_desc';
    panel.renderNotesList();
    const cIndex = renderedHtml.indexOf('C Scroll');
    const bIndex = renderedHtml.indexOf('B Scroll');
    const aIndex = renderedHtml.indexOf('A Scroll');
    assert.ok(cIndex !== -1 && bIndex !== -1 && aIndex !== -1, 'All titles rendered');
    assert.ok(cIndex < bIndex && bIndex < aIndex, 'title_desc orders Z to A');

    // Sort created_desc (A [Jan 3], C [Jan 2], B [Jan 1])
    panel.currentSort = 'created_desc';
    panel.renderNotesList();
    const aCreated = renderedHtml.indexOf('A Scroll');
    const cCreated = renderedHtml.indexOf('C Scroll');
    const bCreated = renderedHtml.indexOf('B Scroll');
    assert.ok(aCreated < cCreated && cCreated < bCreated, 'created_desc orders newest first');

    // Sort created_asc (B [Jan 1], C [Jan 2], A [Jan 3])
    panel.currentSort = 'created_asc';
    panel.renderNotesList();
    const bAsc = renderedHtml.indexOf('B Scroll');
    const cAsc = renderedHtml.indexOf('C Scroll');
    const aAsc = renderedHtml.indexOf('A Scroll');
    assert.ok(bAsc < cAsc && cAsc < aAsc, 'created_asc orders oldest first');
});

test('white-box: MarkdownEngine parses markdown image tags with base64 raster data URIs', () => {
    const context = vm.createContext({
        window: {},
        document: {
            createElement: () => ({
                querySelectorAll: () => [],
                attributes: [],
                innerHTML: '',
                content: { querySelectorAll: () => [] }
            }),
            addEventListener() {},
            querySelector() { return null; },
        }
    });
    vm.runInContext(source + '\nglobalThis.MarkdownEngine = MarkdownEngine;', context);
    const base64Img = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
    const html = context.MarkdownEngine.parse(`![Pasted Image](${base64Img})`);
    assert.ok(html.includes('<img alt="Pasted Image" src="' + base64Img + '">'));
});

test('white-box: initPasteInterceptor intercepts image file paste and triggers reader', () => {
    const listeners = new Map();
    const mockEditorArea = {
        addEventListener(event, fn) { listeners.set(event, fn); },
        cloneNode: () => ({
            innerHTML: '<p><img src="data:image/png;base64,FAKEIMAGE"></p>',
            querySelectorAll: () => []
        }),
        innerText: 'Some text'
    };
    const { panel, messages, context } = harness(() => Promise.resolve({ ok: true }));
    panel.dom.editorArea = mockEditorArea;

    let preventDefaultCalled = false;
    let readAsDataURLCalled = false;

    // Mock FileReader on global context
    context.FileReader = class {
        readAsDataURL() {
            readAsDataURLCalled = true;
            this.onload({ target: { result: 'data:image/png;base64,FAKEIMAGE' } });
        }
    };

    panel.initPasteInterceptor();

    const pasteHandler = listeners.get('paste');
    assert.ok(pasteHandler, 'Paste listener registered');

    let execCommandCalled = false;
    let insertedHtml = '';
    context.document.execCommand = (cmd, showUi, val) => {
        execCommandCalled = true;
        insertedHtml = val;
    };
    context.document.createElement = (tag) => {
        const el = {
            tagName: tag.toUpperCase(),
            src: '',
            alt: '',
            className: '',
            innerHTML: '',
            children: [],
            appendChild(child) { this.children.push(child); },
            get outerHTML() {
                if (this.tagName === 'IMG') {
                    return `<img src="${this.src}" alt="${this.alt}" class="${this.className}">`;
                }
                return `<${this.tagName.toLowerCase()}>${this.innerHTML || this.children.map(c => c.outerHTML).join('')}</${this.tagName.toLowerCase()}>`;
            }
        };
        return el;
    };

    const fakeImageFile = { type: 'image/png', name: 'screenshot.png' };
    const fakeEvent = {
        clipboardData: {
            items: [{ type: 'image/png', getAsFile: () => fakeImageFile }],
            getData: () => '',
        },
        preventDefault() { preventDefaultCalled = true; }
    };

    pasteHandler(fakeEvent);

    assert.equal(preventDefaultCalled, true);
    assert.equal(readAsDataURLCalled, true);
    assert.equal(execCommandCalled, true);
    assert.ok(insertedHtml.includes('<img src="data:image/png;base64,FAKEIMAGE"'));
    assert.ok(messages.some(m => m[0] === 'Image pasted into scroll!'));
});

test('white-box: saveActiveNote flushes editorArea innerHTML to note content before persisting', async () => {
    let capturedBody = null;
    const { panel } = harness(async (url, options) => {
        capturedBody = JSON.parse(options.body);
        return { ok: true, json: async () => ({ status: 'success', data: { ...panel.activeNote, ...capturedBody } }) };
    });
    panel.dom.editorArea = {
        cloneNode: () => ({
            innerHTML: '<p>Pasted image in editor <img src="data:image/png;base64,XYZ"></p>',
            querySelectorAll: () => []
        })
    };
    panel.activeNote.content = '<p>Old content</p>';

    const saved = await panel.saveActiveNote(true);
    assert.equal(saved, true);
    assert.ok(capturedBody.content.includes('<img src="data:image/png;base64,XYZ">'));
    assert.equal(panel.activeNote.content, capturedBody.content);
});

test('white-box: renderMarkdownModalPreview parses markdown and updates modal stats', () => {
    const context = vm.createContext({
        window: {},
        document: {
            createElement: () => ({
                querySelectorAll: () => [],
                attributes: [],
                innerHTML: '',
                content: { querySelectorAll: () => [] }
            }),
            addEventListener() {},
            querySelector() { return null; },
        }
    });
    vm.runInContext(source + '\nglobalThis.Panel = NotenuxPanel;\nglobalThis.MarkdownEngine = MarkdownEngine;', context);
    const panel = Object.create(context.Panel.prototype);
    panel.dom = {
        markdownModalTextarea: { value: '# Hello World\n\nThis is a **markdown** preview.' },
        markdownModalPreview: { innerHTML: '' },
        mdModalWordCount: { textContent: '' },
        mdModalCharCount: { textContent: '' }
    };

    panel.renderMarkdownModalPreview();

    assert.ok(panel.dom.markdownModalPreview.innerHTML.includes('<h1>Hello World</h1>'));
    assert.ok(panel.dom.markdownModalPreview.innerHTML.includes('<strong>markdown</strong>'));
    assert.ok(panel.dom.mdModalWordCount.textContent.includes('8 kata'));
    assert.ok(panel.dom.mdModalCharCount.textContent.includes('46 karakter'));
});

test('white-box: clear controls normalize rich formatting and clear editor content', () => {
    const { panel } = harness();
    let handledInput = false;
    panel.handleEditorInput = () => { handledInput = true; };

    panel.dom.editorArea = {
        innerHTML: '<h1>Heading</h1><p style="color:red;">Rich content</p>',
        innerText: 'Heading\n\nRich content'
    };
    panel.dom.clearConfirmModal = { classList: { add: () => {}, remove: () => {} } };

    // Simulate clear all content
    panel.dom.editorArea.innerHTML = '<p><br></p>';
    panel.handleEditorInput();
    assert.equal(panel.dom.editorArea.innerHTML, '<p><br></p>');
    assert.equal(handledInput, true);

    // Simulate normalize format
    panel.dom.editorArea.innerText = 'Paragraph 1\n\nParagraph 2';
    const plainText = panel.dom.editorArea.innerText;
    const paragraphs = plainText.split(/\n\n+/).filter(Boolean);
    panel.dom.editorArea.innerHTML = paragraphs.map(p => `<p>${panel.escapeHtml(p.trim())}</p>`).join('');
    assert.equal(panel.dom.editorArea.innerHTML, '<p>Paragraph 1</p><p>Paragraph 2</p>');
});
