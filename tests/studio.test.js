import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import test from 'node:test';
import assert from 'node:assert/strict';

const source = readFileSync(new URL('../resources/js/app.js', import.meta.url), 'utf8');

function harness(fetchHandler) {
    const storage = new Map();
    const messages = [];
    const context = vm.createContext({
        fetch: fetchHandler,
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
        selectedNoteIds: new Set(),
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

test('white-box: quick delete category automatically banishes category and its associated notes', async () => {
    const { panel, context, messages } = harness(async (url, options) => {
        if (url === '/api/categories/5' && options?.method === 'DELETE') {
            return response({
                deleted_id: 5,
                deleted_slug: 'tactics',
                deleted_notes_count: 2
            });
        }
        return response({});
    });
    panel.categories = [
        { id: 5, name: 'Tactics', slug: 'tactics' },
        { id: 6, name: 'Lore', slug: 'lore' }
    ];
    panel.notes = [
        { id: 101, title: 'Ambush Plan', category: 'tactics', content: 'Alpha' },
        { id: 102, title: 'Siege Engine', category: 'tactics', content: 'Beta' },
        { id: 103, title: 'Ancient Kings', category: 'lore', content: 'Gamma' }
    ];
    panel.activeNote = panel.notes[0];
    panel.activeCategory = 'tactics';
    context.document.getElementById = id => ({ value: id === 'quick-delete-cat-id' ? '5' : '' });
    panel.syncCategoriesDOM = () => {};
    panel.renderNotesList = () => {};
    panel.refreshPlayerStats = () => {};

    await panel.confirmQuickDeleteCategory();

    assert.equal(panel.categories.some(c => c.id === 5), false);
    assert.equal(panel.notes.length, 1);
    assert.equal(panel.notes[0].id, 103);
    assert.equal(panel.activeCategory, 'all');
    assert.equal(panel.activeNote.id, 103);
    assert.ok(messages.some(message => message[0].includes('banished')));
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
                get value() { return this.innerHTML; },
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

test('white-box: table customization operations add/delete rows and columns and style cells', () => {
    const { panel, context } = harness();
    let handledInput = false;
    panel.handleEditorInput = () => { handledInput = true; };

    // Create a mock table structure
    const createCell = (tag = 'td') => {
        const cell = {
            tagName: tag.toUpperCase(),
            innerHTML: '',
            style: {
                textAlign: '',
                backgroundColor: '',
                color: '',
                removeProperty(prop) {
                    if (prop === 'background-color') delete this.backgroundColor;
                    if (prop === 'color') delete this.color;
                    delete this[prop];
                }
            },
            attributes: [],
            setAttribute(k, v) { this[k] = v; },
            classList: { add: () => {}, remove: () => {} },
            remove() {
                if (cell.parentTr) {
                    const idx = cell.parentTr.cells.indexOf(cell);
                    if (idx !== -1) cell.parentTr.cells.splice(idx, 1);
                }
            },
            closest(selector) {
                if (selector.includes('tr')) return cell.parentTr;
                if (selector.includes('table')) return cell.parentTr?.parentTable;
                return null;
            }
        };
        return cell;
    };

    const createRow = (cellTags = ['td', 'td']) => {
        const tr = {
            tagName: 'TR',
            cells: [],
            closest(selector) {
                if (selector.includes('table')) return tr.parentTable;
                if (selector.includes('thead')) return tr.parentThead;
                return null;
            },
            parentNode: null,
            remove() {
                const idx = tr.parentTable.rows.indexOf(tr);
                if (idx !== -1) tr.parentTable.rows.splice(idx, 1);
            },
            insertBefore(newNode, refNode) {
                const refIdx = tr.cells.indexOf(refNode);
                newNode.parentTr = tr;
                if (refIdx === -1) tr.cells.push(newNode);
                else tr.cells.splice(refIdx, 0, newNode);
                return newNode;
            },
            appendChild(newNode) {
                newNode.parentTr = tr;
                tr.cells.push(newNode);
                return newNode;
            },
            replaceChild(newChild, oldChild) {
                const idx = tr.cells.indexOf(oldChild);
                if (idx !== -1) {
                    newChild.parentTr = tr;
                    tr.cells[idx] = newChild;
                }
            }
        };
        tr.cells = cellTags.map(tag => {
            const c = createCell(tag);
            c.parentTr = tr;
            return c;
        });
        return tr;
    };

    const mockTable = {
        tagName: 'TABLE',
        rows: [],
        querySelectorAll(selector) {
            if (selector.includes('tr')) return mockTable.rows;
            return [];
        },
        insertBefore(newNode, refNode) {
            const refIdx = mockTable.rows.indexOf(refNode);
            newNode.parentTable = mockTable;
            newNode.parentNode = mockTable;
            if (refIdx === -1) mockTable.rows.push(newNode);
            else mockTable.rows.splice(refIdx, 0, newNode);
            return newNode;
        },
        remove() { mockTable.removed = true; }
    };

    const row1 = createRow(['th', 'th']);
    row1.parentTable = mockTable;
    row1.parentNode = mockTable;
    const row2 = createRow(['td', 'td']);
    row2.parentTable = mockTable;
    row2.parentNode = mockTable;
    mockTable.rows = [row1, row2];

    context.document.createElement = (tag) => {
        if (tag.toLowerCase() === 'tr') {
            const tr = createRow([]);
            return tr;
        }
        return createCell(tag);
    };

    // Test cell selection & coordinates
    panel.activeTableCell = row2.cells[0];
    row2.cells[0].cellIndex = 0;
    row2.rowIndex = 1;
    panel.dom.tableCellCoord = { textContent: '' };
    panel.dom.tableToolkitBar = { classList: { remove: () => {}, add: () => {} } };

    panel.setActiveTableCell(row2.cells[0]);
    assert.ok(panel.dom.tableCellCoord.textContent.includes('R2'));

    // Test add row below
    panel.tableAddRow('below');
    assert.equal(mockTable.rows.length, 3);
    assert.equal(handledInput, true);

    // Test styling active cell
    panel.tableSetAlign('center');
    assert.equal(panel.activeTableCell.style.textAlign, 'center');

    panel.tableSetCellBg('#10b981');
    assert.equal(panel.activeTableCell.style.backgroundColor, '#10b981');

    panel.tableSetCellColor('#ffffff');
    assert.equal(panel.activeTableCell.style.color, '#ffffff');

    panel.tableResetColors();
    assert.equal(panel.activeTableCell.style.backgroundColor, undefined);

    // Test add column right
    panel.tableAddColumn('right');
    assert.equal(mockTable.rows[0].cells.length, 3);
    assert.equal(mockTable.rows[1].cells.length, 3);

    // Test delete column
    panel.tableDeleteColumn();
    assert.equal(mockTable.rows[0].cells.length, 2);

    // Test delete row
    panel.tableDeleteRow();
    assert.equal(mockTable.rows.length, 2);
});

test('white-box: table image paste directly inserts image into active table cell', () => {
    const listeners = new Map();
    const cellAppended = [];
    const mockCell = {
        tagName: 'TD',
        appendChild(child) { cellAppended.push(child); }
    };
    const mockEditorArea = {
        addEventListener(event, fn) { listeners.set(event, fn); },
        cloneNode: () => ({ innerHTML: '', querySelectorAll: () => [] }),
        contains: (node) => node === mockCell
    };
    const { panel, context } = harness(() => Promise.resolve({ ok: true }));
    panel.dom.editorArea = mockEditorArea;
    panel.activeTableCell = mockCell;

    context.FileReader = class {
        readAsDataURL() {
            this.onload({ target: { result: 'data:image/png;base64,TABLEIMGDATA' } });
        }
    };
    context.document.createElement = (tag) => ({
        tagName: tag.toUpperCase(),
        src: '',
        alt: '',
        className: ''
    });

    panel.initPasteInterceptor();
    const pasteHandler = listeners.get('paste');

    const fakePasteEvent = {
        preventDefault: () => {},
        clipboardData: {
            items: [{
                type: 'image/png',
                getAsFile: () => ({ name: 'screenshot.png', type: 'image/png' })
            }],
            files: [],
            getData: () => ''
        }
    };

    pasteHandler(fakePasteEvent);

    assert.equal(cellAppended.length, 1);
    assert.equal(cellAppended[0].tagName, 'IMG');
    assert.equal(cellAppended[0].src, 'data:image/png;base64,TABLEIMGDATA');
    assert.ok(cellAppended[0].className.includes('max-h-48'));
});

test('white-box: MarkdownEngine preserves images inside table cells during conversion', () => {
    const context = vm.createContext({
        window: {},
        document: {
            createElement: () => ({
                querySelectorAll: () => [],
                attributes: [],
                innerHTML: '',
                get value() { return this.innerHTML; },
                content: {
                    querySelectorAll: () => []
                }
            }),
            addEventListener() {},
            querySelector() { return null; },
        }
    });
    vm.runInContext(source + '\nglobalThis.MarkdownEngine = MarkdownEngine;', context);
    const MarkdownEngine = context.MarkdownEngine;

    // Test toMarkdown with table containing img
    const htmlTable = `<table>
        <tr><th>Header A</th><th>Header B</th></tr>
        <tr><td>Item 1</td><td><img src="https://example.com/item.png" alt="Item Image"></td></tr>
    </table>`;

    const md = MarkdownEngine.toMarkdown(htmlTable);
    assert.ok(md.includes('| Header A | Header B |'));
    assert.ok(md.includes('![Item Image](https://example.com/item.png)'));

    // Test parse markdown table with image back to HTML
    const parsedHtml = MarkdownEngine.parse(md);
    assert.ok(parsedHtml.includes('<table class="retro-table">'));
    assert.ok(parsedHtml.includes('<img'));
    assert.ok(parsedHtml.includes('https://example.com/item.png'));
});

test('white-box: MarkdownEngine converts markdown tables with escaped pipes and inline images to html', () => {
    const context = vm.createContext({
        window: {},
        document: {
            createElement: () => ({
                querySelectorAll: () => [],
                attributes: [],
                innerHTML: '',
                get value() { return this.innerHTML; },
                content: { querySelectorAll: () => [] }
            }),
            addEventListener() {},
            querySelector() { return null; },
        }
    });
    vm.runInContext(source + '\nglobalThis.MarkdownEngine = MarkdownEngine;', context);
    const MarkdownEngine = context.MarkdownEngine;

    const md = `| Command | Output |
| --- | --- |
| cat &#124; grep | ![Terminal](https://notenux.dev/term.png) |`;

    const html = MarkdownEngine.parse(md);
    assert.ok(html.includes('<table class="retro-table">'));
    assert.ok(html.includes('cat &#124; grep'));
    assert.ok(html.includes('https://notenux.dev/term.png'));
});

test('white-box: sanitizeHtml in app.js strips svg base64 data URIs while allowing safe raster formats', () => {
    const context = vm.createContext({
        window: {},
        document: {
            createElement: (tag) => {
                let attrs = [];
                let children = [];
                let inner = '';
                return {
                    tagName: tag.toUpperCase(),
                    localName: tag.toLowerCase(),
                    attributes: attrs,
                    content: {
                        querySelectorAll: () => children
                    },
                    removeAttribute: (name) => {
                        attrs = attrs.filter(a => a.name !== name);
                    },
                    remove: () => {},
                    get innerHTML() { return inner; },
                    set innerHTML(val) { inner = val; }
                };
            },
            addEventListener() {},
            querySelector() { return null; },
        }
    });
    vm.runInContext(source + '\nglobalThis.sanitizeHtml = sanitizeHtml;', context);
    const sanitizeHtml = context.sanitizeHtml;
    assert.equal(typeof sanitizeHtml, 'function');
});

test('white-box: PlainTextEngine converts rich html to clean unformatted plain text and vice versa', () => {
    const context = vm.createContext({
        window: {},
        document: {
            createElement: () => ({ querySelectorAll: () => [], attributes: [], content: { querySelectorAll: () => [] } }),
            addEventListener() {},
        }
    });
    vm.runInContext(source + '\nglobalThis.PlainTextEngine = PlainTextEngine;', context);
    const PlainTextEngine = context.PlainTextEngine;

    // Test rich HTML with headings, paragraphs, bold, lists, and linebreaks
    const richHtml = '<h1>Mission Briefing</h1><p>Welcome to <strong>Notenux</strong>.<br>Prepare your gear.</p><ul><li>Sword</li><li>Shield</li></ul>';
    const plain = PlainTextEngine.toPlainText(richHtml);

    assert.ok(plain.includes('Mission Briefing'));
    assert.ok(plain.includes('Welcome to Notenux.'));
    assert.ok(plain.includes('Prepare your gear.'));
    assert.ok(plain.includes('• Sword'));
    assert.ok(plain.includes('• Shield'));
    assert.equal(plain.includes('<h1>'), false);
    assert.equal(plain.includes('<strong>'), false);
    assert.equal(plain.includes('<p>'), false);

    // Test scripts and styles are stripped
    const unsafeHtml = '<script>alert("hack")</script><style>body{color:red}</style><p>Clean content</p>';
    const safePlain = PlainTextEngine.toPlainText(unsafeHtml);
    assert.equal(safePlain, 'Clean content');

    // Test plain text converted back to clean unformatted paragraphs
    const plainInput = "First paragraph\nLine two\n\nSecond paragraph";
    const htmlOutput = PlainTextEngine.toHtml(plainInput);
    assert.equal(htmlOutput, '<p>First paragraph<br>Line two</p><p>Second paragraph</p>');
    assert.equal(htmlOutput.includes('<strong>'), false);
    assert.equal(htmlOutput.includes('<h1>'), false);

    // Test XSS escaping during plain text to html conversion
    const xssInput = '<img src=x onerror=alert(1)>';
    const escapedHtml = PlainTextEngine.toHtml(xssInput);
    assert.ok(escapedHtml.includes('&lt;img src=x onerror=alert(1)&gt;'));
});

test('white-box: togglePlainTextMode toggles editor view, syncs plain text, and toggles notepad toolbar classes', () => {
    const { panel } = harness();
    let handledInput = false;
    panel.handleEditorInput = () => { handledInput = true; };

    const classSet = (initial = []) => {
        const set = new Set(initial);
        return {
            classes: set,
            add(c) { set.add(c); },
            remove(c) { set.delete(c); },
            contains(c) { return set.has(c); },
            toggle(c) { set.has(c) ? set.delete(c) : set.add(c); }
        };
    };

    const editorArea = {
        innerHTML: '<h2>Quest Title</h2><p>Begin adventure.</p>',
        classList: classSet(),
        focus() {}
    };
    const plainTextEditor = {
        value: '',
        classList: classSet(['hidden']),
        focus() {}
    };
    const plainTextContainer = {
        classList: classSet(['hidden'])
    };
    const plainTextToggleBtn = {
        classList: classSet(['bg-surface']),
        innerHTML: 'TXT',
        title: ''
    };
    const editorToolbarDecks = {
        classList: classSet()
    };
    const markdownRawEditor = {
        classList: classSet(['hidden']),
        value: ''
    };

    panel.dom = {
        ...panel.dom,
        editorArea,
        plainTextEditor,
        plainTextContainer,
        plainTextToggleBtn,
        editorToolbarDecks,
        markdownRawEditor,
        plainTextCharCount: { textContent: '' },
        plainTextWordCount: { textContent: '' },
        plainTextLineCount: { textContent: '' },
    };

    // Toggle on (enter plain text mode)
    panel.togglePlainTextMode();
    assert.equal(panel.isPlainTextMode, true);
    assert.equal(editorArea.classList.contains('hidden'), true);
    assert.equal(plainTextContainer.classList.contains('hidden'), false);
    assert.equal(editorToolbarDecks.classList.contains('notepad-mode'), true);
    assert.ok(plainTextEditor.value.includes('Quest Title'));
    assert.ok(plainTextEditor.value.includes('Begin adventure.'));
    assert.equal(plainTextEditor.value.includes('<h2>'), false);
    assert.ok(plainTextToggleBtn.innerHTML.includes('WYSIWYG'));

    // Toggle off (return to rich editor)
    panel.togglePlainTextMode();
    assert.equal(panel.isPlainTextMode, false);
    assert.equal(editorArea.classList.contains('hidden'), false);
    assert.equal(plainTextContainer.classList.contains('hidden'), true);
    assert.equal(editorToolbarDecks.classList.contains('notepad-mode'), false);
    assert.ok(plainTextToggleBtn.innerHTML.includes('TXT'));
    assert.equal(handledInput, true);
});

test('white-box: copyEntirePlainText copies all plain text to clipboard and reports success', async () => {
    let copiedText = '';
    const { panel, messages, context } = harness();
    context.navigator = {
        clipboard: {
            writeText: async (text) => { copiedText = text; }
        }
    };

    panel.isPlainTextMode = true;
    panel.dom.plainTextEditor = { value: 'Line 1: Note content\nLine 2: Second line' };

    const success = await panel.copyEntirePlainText();
    assert.equal(success, true);
    assert.equal(copiedText, 'Line 1: Note content\nLine 2: Second line');
    assert.ok(messages.some(m => m[0].includes('berhasil disalin')));

    // Test copying when not in plain text mode (converts rich HTML to plain text)
    panel.isPlainTextMode = false;
    panel.dom.editorArea = { innerHTML: '<h1>Legendary Scroll</h1><p>Secret artifact details.</p>' };
    const successRich = await panel.copyEntirePlainText();
    assert.equal(successRich, true);
    assert.ok(copiedText.includes('Legendary Scroll'));
    assert.ok(copiedText.includes('Secret artifact details.'));
    assert.equal(copiedText.includes('<h1>'), false);
});

test('white-box: saveActiveNote in plain text mode persists plain text converted to clean unformatted HTML', async () => {
    let capturedBody = null;
    const { panel } = harness(async (url, options) => {
        capturedBody = JSON.parse(options.body);
        return { ok: true, json: async () => ({ status: 'success', data: { ...panel.activeNote, ...capturedBody } }) };
    });

    panel.isPlainTextMode = true;
    panel.plainTextOriginalText = 'Old unedited plain text';
    panel.plainTextOriginalHtml = '<p>Old content</p>';
    panel.dom.plainTextEditor = {
        value: 'New plain text line 1\n\nNew paragraph without format'
    };

    const saved = await panel.saveActiveNote(true);
    assert.equal(saved, true);
    assert.ok(capturedBody.content.includes('<p>New plain text line 1</p>'));
    assert.ok(capturedBody.content.includes('<p>New paragraph without format</p>'));
    assert.equal(capturedBody.content.includes('<h1>'), false);
    assert.equal(panel.activeNote.content, capturedBody.content);
});

test('white-box: updatePlainTextStats accurately computes char, word, and line metrics', () => {
    const { panel } = harness();
    panel.dom.plainTextEditor = {
        value: 'Baris satu kata satu dua\nBaris dua kata tiga empat\nBaris tiga'
    };
    panel.dom.plainTextCharCount = { textContent: '' };
    panel.dom.plainTextWordCount = { textContent: '' };
    panel.dom.plainTextLineCount = { textContent: '' };

    panel.updatePlainTextStats();

    assert.equal(panel.dom.plainTextCharCount.textContent, '61 karakter');
    assert.equal(panel.dom.plainTextWordCount.textContent, '12 kata');
    assert.equal(panel.dom.plainTextLineCount.textContent, '3 baris');
});

test('white-box: undo and redo history stack accurately reverts and reapplies editor content', () => {
    const { panel, messages } = harness();
    panel.dom.editorArea = {
        innerHTML: '<p>Initial state</p>',
        focus() {},
        cloneNode: () => ({ innerHTML: '<p>Initial state</p>', querySelectorAll: () => [] }),
    };

    panel.initHistory('<p>Initial state</p>');
    assert.equal(panel.undoStack.length, 1);
    assert.equal(panel.redoStack.length, 0);

    // Edit 1
    panel.dom.editorArea.innerHTML = '<p>Second state</p>';
    panel.pushUndoSnapshot();
    assert.equal(panel.undoStack.length, 2);
    assert.equal(panel.undoStack[1], '<p>Second state</p>');

    // Edit 2
    panel.dom.editorArea.innerHTML = '<p>Third state</p>';
    panel.pushUndoSnapshot();
    assert.equal(panel.undoStack.length, 3);

    // Undo 1: Reverts to Second state
    panel.undo();
    assert.equal(panel.dom.editorArea.innerHTML, '<p>Second state</p>');
    assert.equal(panel.redoStack.length, 1);
    assert.equal(panel.redoStack[0], '<p>Third state</p>');
    assert.ok(messages.some(m => m[0].includes('Undo') || m[0].includes('dibatalkan')));

    // Undo 2: Reverts to Initial state
    panel.undo();
    assert.equal(panel.dom.editorArea.innerHTML, '<p>Initial state</p>');
    assert.equal(panel.redoStack.length, 2);

    // Redo 1: Re-applies Second state
    panel.redo();
    assert.equal(panel.dom.editorArea.innerHTML, '<p>Second state</p>');
    assert.equal(panel.redoStack.length, 1);

    // Redo 2: Re-applies Third state
    panel.redo();
    assert.equal(panel.dom.editorArea.innerHTML, '<p>Third state</p>');
    assert.equal(panel.redoStack.length, 0);
});

test('white-box: new edit after undo invalidates redo stack', () => {
    const { panel } = harness();
    panel.dom.editorArea = {
        innerHTML: '<p>Base state</p>',
        focus() {},
        cloneNode: () => ({ innerHTML: '<p>Base state</p>', querySelectorAll: () => [] }),
    };

    panel.initHistory('<p>Base state</p>');
    panel.dom.editorArea.innerHTML = '<p>Branch A</p>';
    panel.pushUndoSnapshot();

    panel.undo();
    assert.equal(panel.dom.editorArea.innerHTML, '<p>Base state</p>');
    assert.equal(panel.redoStack.length, 1);

    // New edit on Base state branches off
    panel.dom.editorArea.innerHTML = '<p>Branch B</p>';
    panel.pushUndoSnapshot();

    // Redo stack must be cleared
    assert.equal(panel.redoStack.length, 0);
    assert.equal(panel.undoStack.length, 2);
    assert.equal(panel.undoStack[1], '<p>Branch B</p>');

    // Undo should go to Base state
    panel.undo();
    assert.equal(panel.dom.editorArea.innerHTML, '<p>Base state</p>');
});

test('white-box: keyboard shortcuts invoke undo on Ctrl+Z and redo on Ctrl+Y and Ctrl+Shift+Z', () => {
    let keyHandler = null;
    const context = vm.createContext({
        window: { fetch: () => {}, innerWidth: 1440 },
        document: {
            addEventListener: (event, handler) => {
                if (event === 'keydown') keyHandler = handler;
            },
            querySelector: () => ({ content: 'csrf' }),
            querySelectorAll: () => []
        },
        localStorage: { removeItem() {}, setItem() {}, getItem() {} },
        clearTimeout,
        setTimeout,
    });
    vm.runInContext(source + '\nglobalThis.Panel = NotenuxPanel;', context);
    const panel = Object.create(context.Panel.prototype);

    let undoCalled = false;
    let redoCalled = false;
    panel.undo = () => { undoCalled = true; };
    panel.redo = () => { redoCalled = true; };
    panel.dom = { editorArea: { tagName: 'DIV' } };

    panel.bindKeyboardShortcuts();
    assert.equal(typeof keyHandler, 'function');

    // Ctrl+Z (Undo)
    let prevented = false;
    keyHandler({ ctrlKey: true, key: 'z', shiftKey: false, preventDefault() { prevented = true; }, target: panel.dom.editorArea });
    assert.equal(undoCalled, true);
    assert.equal(prevented, true);

    // Ctrl+Y (Redo)
    prevented = false;
    keyHandler({ ctrlKey: true, key: 'y', shiftKey: false, preventDefault() { prevented = true; }, target: panel.dom.editorArea });
    assert.equal(redoCalled, true);
    assert.equal(prevented, true);

    // Ctrl+Shift+Z (Redo alternative)
    redoCalled = false;
    prevented = false;
    keyHandler({ ctrlKey: true, key: 'z', shiftKey: true, preventDefault() { prevented = true; }, target: panel.dom.editorArea });
    assert.equal(redoCalled, true);
    assert.equal(prevented, true);

    // Target inside foreign input should NOT preventDefault or trigger panel undo
    undoCalled = false;
    prevented = false;
    keyHandler({ ctrlKey: true, key: 'z', shiftKey: false, preventDefault() { prevented = true; }, target: { tagName: 'INPUT' } });
    assert.equal(undoCalled, false);
    assert.equal(prevented, false);
});

test('white-box: undo and redo delegate to textarea when in plain text mode', () => {
    const { panel } = harness();
    let textareaFocused = false;
    let statsUpdated = false;

    panel.isPlainTextMode = true;
    panel.dom.plainTextEditor = {
        focus() { textareaFocused = true; }
    };
    panel.updatePlainTextStats = () => { statsUpdated = true; };

    panel.undo();
    assert.equal(textareaFocused, true);
    assert.equal(statsUpdated, true);

    textareaFocused = false;
    statsUpdated = false;

    panel.redo();
    assert.equal(textareaFocused, true);
    assert.equal(statsUpdated, true);
});

test('white-box: updateFavicon dynamically adapts link elements to active dark and light theme', () => {
    const { panel, context } = harness();
    const faviconElement = { href: '', setAttribute(name, val) { if (name === 'href') this.href = val; } };
    const appleIconElement = { href: '', setAttribute(name, val) { if (name === 'href') this.href = val; } };

    context.document.getElementById = (id) => {
        if (id === 'app-favicon') return faviconElement;
        if (id === 'app-apple-touch-icon') return appleIconElement;
        return null;
    };
    context.window.notenuxFaviconLight = '/assets/logo/light/icon_light.png';
    context.window.notenuxFaviconDark = '/assets/logo/dark/icon_dark.png';

    panel.updateFavicon('dark');
    assert.equal(faviconElement.href, '/assets/logo/dark/icon_dark.png');
    assert.equal(appleIconElement.href, '/assets/logo/dark/icon_dark.png');

    panel.updateFavicon('light');
    assert.equal(faviconElement.href, '/assets/logo/light/icon_light.png');
    assert.equal(appleIconElement.href, '/assets/logo/light/icon_light.png');
});

test('white-box: setTheme invokes updateFavicon and sets theme classes and attributes', () => {
    const { panel, context } = harness();
    let updatedFaviconTheme = null;
    panel.updateFavicon = (t) => { updatedFaviconTheme = t; };

    const classList = new Set();
    const attributes = {};
    context.document.documentElement = {
        classList: {
            add(c) { classList.add(c); },
            remove(c) { classList.delete(c); },
            contains(c) { return classList.has(c); },
        },
        setAttribute(k, v) { attributes[k] = v; },
    };

    panel.setTheme('dark', false);
    assert.equal(updatedFaviconTheme, 'dark');
    assert.equal(classList.has('dark'), true);
    assert.equal(attributes['data-theme'], 'dark');

    panel.setTheme('light', false);
    assert.equal(updatedFaviconTheme, 'light');
    assert.equal(classList.has('dark'), false);
    assert.equal(attributes['data-theme'], 'light');
});

test('white-box: RetroPasswordToggle switches password input visibility, icons, and titles', () => {
    const { context } = harness();
    const RetroPasswordToggle = context.window.RetroPasswordToggle || context.RetroPasswordToggle;

    const listeners = new Map();
    const iconClassList = new Set(['fa-solid', 'fa-eye', 'toggle-password-icon']);
    const buttonAttributes = {
        'data-target': 'test-pass',
        'data-state': 'closed',
    };

    const iconElement = {
        classList: {
            add(c) { iconClassList.add(c); },
            remove(c) { iconClassList.delete(c); },
            contains(c) { return iconClassList.has(c); },
        },
    };

    const inputElement = {
        id: 'test-pass',
        type: 'password',
    };

    const buttonElement = {
        dataset: {},
        getAttribute(attr) { return buttonAttributes[attr] ?? null; },
        setAttribute(attr, val) { buttonAttributes[attr] = val; },
        querySelector(selector) {
            if (selector === '.toggle-password-icon' || selector === 'i') return iconElement;
            return null;
        },
        addEventListener(event, fn) { listeners.set(event, fn); },
    };

    context.document.getElementById = (id) => {
        if (id === 'test-pass') return inputElement;
        return null;
    };

    const toggleInstance = new RetroPasswordToggle(buttonElement);
    assert.equal(buttonElement.dataset.boundPasswordToggle, 'true');

    // First toggle: reveal password
    toggleInstance.toggle();
    assert.equal(inputElement.type, 'text');
    assert.equal(buttonAttributes['data-state'], 'open');
    assert.equal(buttonAttributes['title'], 'Sembunyikan kata sandi (Close password)');
    assert.equal(iconClassList.has('fa-eye-slash'), true);
    assert.equal(iconClassList.has('fa-eye'), false);

    // Second toggle: close/hide password
    toggleInstance.toggle();
    assert.equal(inputElement.type, 'password');
    assert.equal(buttonAttributes['data-state'], 'closed');
    assert.equal(buttonAttributes['title'], 'Tampilkan kata sandi (Show password)');
    assert.equal(iconClassList.has('fa-eye'), true);
    assert.equal(iconClassList.has('fa-eye-slash'), false);
});

test('white-box: PlainTextEngine converts task checkboxes, ordered lists, and tables without formatting artifacts', () => {
    const context = vm.createContext({
        window: {},
        document: {
            createElement: () => ({ querySelectorAll: () => [], attributes: [], content: { querySelectorAll: () => [] } }),
            addEventListener() {},
        }
    });
    vm.runInContext(source + '\nglobalThis.PlainTextEngine = PlainTextEngine;', context);
    const PlainTextEngine = context.PlainTextEngine;

    const html = `
        <h2>Quests</h2>
        <ul>
            <li><input type="checkbox"> Slay Dragon</li>
            <li><input type="checkbox" checked> Save Village</li>
        </ul>
        <ol>
            <li>First Step</li>
            <li>Second Step</li>
        </ol>
        <table>
            <tr><th>Weapon</th><th>Power</th></tr>
            <tr><td>Excalibur</td><td>100</td></tr>
        </table>
    `;

    const plain = PlainTextEngine.toPlainText(html);

    assert.ok(plain.includes('[ ] Slay Dragon'));
    assert.ok(plain.includes('[x] Save Village'));
    assert.ok(plain.includes('1. First Step'));
    assert.ok(plain.includes('2. Second Step'));
    assert.ok(plain.includes("Weapon\tPower"));
    assert.ok(plain.includes("Excalibur\t100"));
    assert.equal(plain.includes('<li'), false);
    assert.equal(plain.includes('<table'), false);
});

test('white-box: plainTextClearBtn clears plainTextEditor, resets editorArea, and updates stats', () => {
    const { panel, messages } = harness();
    let handledInput = false;
    let playedDelete = false;
    panel.handleEditorInput = () => { handledInput = true; };
    panel.audio = { playDelete: () => { playedDelete = true; } };

    panel.dom.plainTextEditor = { value: 'Some note text inside notepad' };
    panel.dom.editorArea = { innerHTML: '<p>Some note text inside notepad</p>' };
    panel.dom.plainTextCharCount = { textContent: '' };
    panel.dom.plainTextWordCount = { textContent: '' };
    panel.dom.plainTextLineCount = { textContent: '' };

    const clearHandler = () => {
        if (!panel.dom.plainTextEditor || !panel.dom.plainTextEditor.value.trim()) return;
        panel.audio?.playDelete?.();
        panel.dom.plainTextEditor.value = '';
        if (panel.dom.editorArea) panel.dom.editorArea.innerHTML = '<p><br></p>';
        panel.updatePlainTextStats();
        panel.handleEditorInput();
        panel.toast.show('Teks Notepad berhasil dikosongkan.', 'info', 'Notepad Dikosongkan');
    };

    clearHandler();

    assert.equal(panel.dom.plainTextEditor.value, '');
    assert.equal(panel.dom.editorArea.innerHTML, '<p><br></p>');
    assert.equal(playedDelete, true);
    assert.equal(handledInput, true);
    assert.equal(panel.dom.plainTextCharCount.textContent, '0 karakter');
    assert.equal(panel.dom.plainTextWordCount.textContent, '0 kata');
    assert.equal(panel.dom.plainTextLineCount.textContent, '0 baris');
    assert.ok(messages.some(m => m[0].includes('berhasil dikosongkan')));
});

test('white-box: applyFontSize adjusts plainTextEditor, markdownRawEditor, and editorArea style', () => {
    const { panel } = harness();
    panel.dom.editorArea = { style: { fontSize: '14px' } };
    panel.dom.plainTextEditor = { style: { fontSize: '14px' } };
    panel.dom.markdownRawEditor = { style: { fontSize: '14px' } };
    panel.activeNote = { font_size: '14px' };

    panel.applyFontSize('18px');

    assert.equal(panel.dom.editorArea.style.fontSize, '18px');
    assert.equal(panel.dom.plainTextEditor.style.fontSize, '18px');
    assert.equal(panel.dom.markdownRawEditor.style.fontSize, '18px');
    assert.equal(panel.activeNote.font_size, '18px');
});

test('white-box: togglePlainTextMode manages notepadModeBanner visibility along with editor panels', () => {
    const { panel } = harness();
    panel.handleEditorInput = () => {};

    const bannerClasses = new Set(['hidden']);
    panel.dom.notepadModeBanner = {
        classList: {
            add(c) { bannerClasses.add(c); },
            remove(c) { bannerClasses.delete(c); },
            contains(c) { return bannerClasses.has(c); },
        }
    };
    panel.dom.editorArea = {
        innerHTML: '<p>Notepad test</p>',
        classList: { add() {}, remove() {}, contains: () => false },
    };
    panel.dom.plainTextContainer = {
        classList: { add() {}, remove() {}, contains: () => false },
    };
    panel.dom.plainTextEditor = { value: '', focus() {} };
    panel.dom.plainTextToggleBtn = { classList: { add() {}, remove() {} }, innerHTML: '', title: '' };
    panel.dom.editorToolbarDecks = { classList: { add() {}, remove() {} } };
    panel.dom.plainTextCharCount = { textContent: '' };
    panel.dom.plainTextWordCount = { textContent: '' };
    panel.dom.plainTextLineCount = { textContent: '' };

    // Toggle to Plain Text mode
    panel.togglePlainTextMode(true);
    assert.equal(panel.isPlainTextMode, true);
    assert.equal(bannerClasses.has('hidden'), false);
    assert.equal(bannerClasses.has('flex'), true);

    // Toggle back to Rich Editor mode
    panel.togglePlainTextMode(false);
    assert.equal(panel.isPlainTextMode, false);
    assert.equal(bannerClasses.has('hidden'), true);
    assert.equal(bannerClasses.has('flex'), false);
});

test('white-box: copyEntirePlainText provides feedback and restores copy button', async () => {
    const { panel, messages, context } = harness();
    let copiedText = '';
    context.navigator = {
        clipboard: {
            writeText: async (t) => { copiedText = t; }
        }
    };

    let btnHtml = '<span>Copy Semua Teks</span>';
    const btnClasses = new Set();
    panel.dom.plainTextCopyBtn = {
        get innerHTML() { return btnHtml; },
        set innerHTML(val) { btnHtml = val; },
        classList: {
            add(c) { btnClasses.add(c); },
            remove(c) { btnClasses.delete(c); },
            contains(c) { return btnClasses.has(c); },
        }
    };

    panel.isPlainTextMode = true;
    panel.dom.plainTextEditor = { value: 'All plain text copied cleanly.' };

    const success = await panel.copyEntirePlainText();
    assert.equal(success, true);
    assert.equal(copiedText, 'All plain text copied cleanly.');
    assert.ok(btnHtml.includes('Tersalin!'));
    assert.equal(btnClasses.has('bg-emerald-700'), true);
});

test('white-box: toggleBulkSelectNotesMode toggles bulk mode and manages bulk bar UI', () => {
    const { panel } = harness();
    const barClasses = new Set(['hidden']);
    const btnClasses = new Set();
    panel.dom.notesBulkBar = {
        classList: {
            add(c) { barClasses.add(c); },
            remove(c) { barClasses.delete(c); },
            contains(c) { return barClasses.has(c); },
        }
    };
    panel.dom.toggleBulkSelectNotesBtn = {
        classList: {
            add(c) { btnClasses.add(c); },
            remove(c) { btnClasses.delete(c); },
            contains(c) { return btnClasses.has(c); },
        }
    };
    panel.dom.bulkToggleLabel = { textContent: 'PILIH' };
    panel.dom.bulkSelectedNotesCount = { textContent: '' };
    panel.dom.bulkDeleteNotesBtn = { disabled: true };
    panel.dom.bulkSelectAllNotesCb = { checked: false };

    // 1. Enable bulk selection mode
    panel.toggleBulkSelectNotesMode(true);
    assert.equal(panel.isBulkSelectNotes, true);
    assert.equal(barClasses.has('hidden'), false);
    assert.equal(panel.dom.bulkToggleLabel.textContent, 'BATAL');
    assert.equal(btnClasses.has('bg-blue-600'), true);

    // 2. Disable bulk selection mode
    panel.toggleBulkSelectNotesMode(false);
    assert.equal(panel.isBulkSelectNotes, false);
    assert.equal(barClasses.has('hidden'), true);
    assert.equal(panel.dom.bulkToggleLabel.textContent, 'PILIH');
    assert.equal(btnClasses.has('bg-blue-600'), false);
    assert.equal(panel.selectedNoteIds.size, 0);
});

test('white-box: toggleSelectNote and selectAllFilteredNotes accurately track selected note ids', () => {
    const { panel } = harness();
    panel.notes = [
        { id: 10, title: 'Scroll 1', category: 'quest' },
        { id: 20, title: 'Scroll 2', category: 'quest' },
        { id: 30, title: 'Scroll 3', category: 'spellbook' },
    ];
    panel.activeCategory = 'all';
    panel.searchQuery = '';
    panel.selectedNoteIds = new Set();
    panel.dom.bulkSelectedNotesCount = { textContent: '' };
    panel.dom.bulkDeleteNotesBtn = { disabled: true };
    panel.dom.bulkSelectAllNotesCb = { checked: false };

    // Toggle note 10 on
    panel.toggleSelectNote(10);
    assert.equal(panel.selectedNoteIds.has(10), true);
    assert.equal(panel.selectedNoteIds.size, 1);
    assert.equal(panel.dom.bulkDeleteNotesBtn.disabled, false);

    // Toggle note 10 off
    panel.toggleSelectNote(10);
    assert.equal(panel.selectedNoteIds.has(10), false);
    assert.equal(panel.selectedNoteIds.size, 0);
    assert.equal(panel.dom.bulkDeleteNotesBtn.disabled, true);

    // Select all filtered notes
    panel.selectAllFilteredNotes(true);
    assert.equal(panel.selectedNoteIds.size, 3);
    assert.equal(panel.selectedNoteIds.has(10), true);
    assert.equal(panel.selectedNoteIds.has(20), true);
    assert.equal(panel.selectedNoteIds.has(30), true);
    assert.equal(panel.dom.bulkSelectAllNotesCb.checked, true);

    // Deselect all
    panel.selectAllFilteredNotes(false);
    assert.equal(panel.selectedNoteIds.size, 0);
    assert.equal(panel.dom.bulkSelectAllNotesCb.checked, false);
});

test('white-box: executeBulkDeleteNotes sends bulk delete payload and switches active note if deleted', async () => {
    const requestedUrls = [];
    let requestedPayload = null;

    const { panel, storage } = harness((url, options = {}) => {
        requestedUrls.push(url);
        if (options.body) {
            requestedPayload = JSON.parse(options.body);
        }
        return Promise.resolve(response({
            deleted_count: 2,
            deleted_ids: [1, 2],
        }));
    });

    const note1 = { id: 1, title: 'Scroll 1', category: 'quest', content: 'c1' };
    const note2 = { id: 2, title: 'Scroll 2', category: 'quest', content: 'c2' };
    const note3 = { id: 3, title: 'Scroll 3', category: 'spellbook', content: 'c3' };

    panel.notes = [note1, note2, note3];
    panel.activeNote = note1;
    panel.selectNote = async (note) => { panel.activeNote = note; };
    panel.dirtyNotes = new Set([1, 2, 3]);
    storage.set('notenux_draft_1', 'draft 1');
    storage.set('notenux_draft_2', 'draft 2');
    panel.selectedNoteIds = new Set([1, 2]);

    panel.dom.notesBulkBar = { classList: { add() {}, remove() {} } };
    panel.dom.toggleBulkSelectNotesBtn = { classList: { add() {}, remove() {} } };
    panel.dom.bulkDeleteNotesModal = { classList: { add() {}, remove() {} } };

    await panel.executeBulkDeleteNotes();

    assert.ok(requestedUrls.includes('/api/notes/bulk-delete'));
    assert.deepEqual(requestedPayload, { ids: [1, 2] });
    assert.equal(panel.notes.length, 1);
    assert.equal(panel.notes[0].id, 3);
    assert.equal(panel.dirtyNotes.has(1), false);
    assert.equal(panel.dirtyNotes.has(2), false);
    assert.equal(storage.has('notenux_draft_1'), false);
    assert.equal(storage.has('notenux_draft_2'), false);
    // Active note switched to remaining note
    assert.equal(panel.activeNote.id, 3);
});

test('white-box: formatDateTime and formatDateCompact correctly format ISO timestamps and handle edge cases', () => {
    const { panel } = harness();

    // Valid date
    const iso = '2026-11-08T01:45:00.000Z';
    const formatted = panel.formatDateTime(iso);
    assert.ok(formatted.includes('2026'));
    assert.ok(formatted.includes('Nov'));

    const compact = panel.formatDateCompact(iso);
    assert.ok(compact.includes('Nov'));

    // Null or undefined or invalid
    assert.equal(panel.formatDateTime(null), '-');
    assert.equal(panel.formatDateTime(undefined), '-');
    assert.equal(panel.formatDateTime(''), '-');
    assert.equal(panel.formatDateCompact(null), '-');
    assert.equal(panel.formatDateCompact('invalid date string'), '-');
});

test('white-box: renderNotesList renders created_at and updated_at metadata timestamps on note cards', () => {
    const { panel } = harness();
    delete panel.renderNotesList;
    panel.categories = [];
    panel.activeCategory = 'all';
    panel.searchQuery = '';
    let renderedHtml = '';
    panel.dom.notesList = {
        get innerHTML() { return renderedHtml; },
        set innerHTML(val) { renderedHtml = val; },
        querySelectorAll: () => [],
    };

    panel.notes = [
        {
            id: 42,
            title: 'Legendary Chronicle',
            category: 'lore',
            created_at: '2026-10-15T10:00:00.000Z',
            updated_at: '2026-11-08T01:30:00.000Z',
            chunks_count: 2,
            word_count: 120,
            level: 3,
        }
    ];

    panel.renderNotesList();

    assert.ok(renderedHtml.includes('scroll-created-meta'));
    assert.ok(renderedHtml.includes('scroll-updated-meta'));
    assert.ok(renderedHtml.includes('Dibuat:'));
    assert.ok(renderedHtml.includes('Update:'));
    assert.ok(renderedHtml.includes('15 Okt'));
    assert.ok(renderedHtml.includes('08 Nov'));
});

test('white-box: loadNoteIntoEditor populates scrollCreatedText and scrollUpdatedText HUD badges', () => {
    const { panel } = harness();
    delete panel.loadNoteIntoEditor;
    panel.clearFindHighlights = () => {};
    panel.initHistory = () => {};
    panel.applyFontFamily = () => {};
    panel.applyFontSize = () => {};
    panel.updateWordAndCharMetrics = () => {};
    panel.updatePinButtonUI = () => {};
    panel.updateFavoriteButtonUI = () => {};
    panel.updatePlainTextStats = () => {};

    let createdText = '';
    let updatedText = '';

    panel.dom.scrollCreatedText = {
        get textContent() { return createdText; },
        set textContent(val) { createdText = val; },
    };
    panel.dom.scrollUpdatedText = {
        get textContent() { return updatedText; },
        set textContent(val) { updatedText = val; },
    };

    const note = {
        id: 99,
        title: 'Ancient Scroll of Wisdom',
        content: '<p>Wisdom words</p>',
        category: 'spellbook',
        created_at: '2026-05-20T12:00:00.000Z',
        updated_at: '2026-11-08T01:15:00.000Z',
    };

    panel.loadNoteIntoEditor(note);

    assert.ok(createdText.includes('Dibuat:'));
    assert.ok(createdText.includes('20 Mei 2026'));
    assert.ok(updatedText.includes('Diperbarui:'));
    assert.ok(updatedText.includes('08 Nov 2026'));
});

test('white-box: RetroSearchableSelect ignores dropdown internal clicks and prevents premature closing', () => {
    const docListeners = new Map();
    const mockSelect = {
        className: 'w-full',
        dataset: {},
        value: 'opt1',
        options: [
            { value: 'opt1', textContent: 'Quest Log' },
        ],
        querySelector() {
            return this.options.find(o => o.value === 'opt1');
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
            body: {
                appendChild(child) { return child; }
            },
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
                    contains(target) {
                        return this === target || this.children.some(c => c === target || (c.contains && c.contains(target)));
                    },
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
            addEventListener: (event, fn) => { docListeners.set(event, fn); }
        },
        MutationObserver: class { observe() {} disconnect() {} },
        HTMLSelectElement: class {},
        Event: class { constructor(type) { this.type = type; } },
        setTimeout: (fn) => fn(),
        clearTimeout: () => {}
    });

    vm.runInContext(source + '\nglobalThis.RetroSearchableSelect = RetroSearchableSelect;', context);
    const instance = new context.RetroSearchableSelect(mockSelect);
    assert.equal(mockSelect.dataset.retroSelectInit, 'true');

    instance.open();
    assert.equal(instance.isOpen, true);

    const docClickHandler = docListeners.get('click');
    assert.equal(typeof docClickHandler, 'function');

    // Click inside dropdown does not close
    const dropdownChild = { contains: () => false };
    instance.dropdown.children.push(dropdownChild);
    docClickHandler({ target: dropdownChild });
    assert.equal(instance.isOpen, true);

    // Click outside closes
    const outsideTarget = { notContained: true };
    docClickHandler({ target: outsideTarget });
    assert.equal(instance.isOpen, false);
});

test('white-box: getFilteredNotes filters by pinned and favorites flags', () => {
    const { panel } = harness(() => {});
    panel.notes = [
        { id: 1, title: 'Note 1', category: 'quest', is_pinned: true, is_favorite: false },
        { id: 2, title: 'Note 2', category: 'quest', is_pinned: false, is_favorite: true },
        { id: 3, title: 'Note 3', category: 'lore', is_pinned: true, is_favorite: true },
        { id: 4, title: 'Note 4', category: 'lore', is_pinned: false, is_favorite: false },
    ];
    panel.searchQuery = '';

    // 'all' category
    panel.activeCategory = 'all';
    assert.equal(panel.getFilteredNotes().length, 4);

    // 'pinned' category filter
    panel.activeCategory = 'pinned';
    const pinned = panel.getFilteredNotes();
    assert.equal(pinned.length, 2);
    assert.equal(pinned[0].id, 1);
    assert.equal(pinned[1].id, 3);

    // 'favorites' category filter
    panel.activeCategory = 'favorites';
    const favorites = panel.getFilteredNotes();
    assert.equal(favorites.length, 2);
    assert.equal(favorites[0].id, 2);
    assert.equal(favorites[1].id, 3);

    // specific category filter
    panel.activeCategory = 'lore';
    const lore = panel.getFilteredNotes();
    assert.equal(lore.length, 2);
    assert.equal(lore[0].id, 3);
    assert.equal(lore[1].id, 4);
});


