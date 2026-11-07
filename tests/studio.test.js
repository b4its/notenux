import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import test from 'node:test';
import assert from 'node:assert/strict';

const source = readFileSync(new URL('../resources/js/app.js', import.meta.url), 'utf8');

function harness(fetchHandler) {
    const storage = new Map();
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
        toast: { show() {} }, audio: { playBlip() {} },
        renderNotesList() {}, refreshPlayerStats() {}, loadNoteIntoEditor(note) { this.loaded = note; },
    });
    panel.notes.push(panel.activeNote);
    return { panel, storage };
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
});
