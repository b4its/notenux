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
