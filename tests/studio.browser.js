async (page) => {
    await page.unrouteAll({ behavior: 'wait' });
    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.addInitScript(() => {
        localStorage.setItem('notenux_primary_collapsed', 'false');
        localStorage.setItem('notenux_explorer_collapsed', 'false');
        localStorage.setItem('notenux_sound', 'muted');
        localStorage.removeItem('notenux_draft_1');
        localStorage.removeItem('notenux_draft_2');
    });
    const assert = (condition, message) => { if (!condition) throw new Error(message); };
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    let sequence = 100;
    const notes = new Map([
        [1, { id: 1, title: 'Studio Alpha', content: '<p>Alpha manuscript</p>', category: 'quest', font_family: 'mono', font_size: '16px', updated_at: '2026-01-01T00:00:00Z' }],
        [2, { id: 2, title: 'Studio Beta', content: '<p>Beta manuscript</p>', category: 'lore', font_family: 'mono', font_size: '16px', updated_at: '2026-01-01T00:00:00Z' }],
    ]);
    await page.route('**/api/**', async route => {
        const request = route.request();
        const path = new URL(request.url()).pathname;
        const id = Number(path.split('/')[3]);
        const note = notes.get(id);
        let data;
        if (path.endsWith('/assistant')) {
            data = { content: '**AI suggestion**<img src=x onerror="window.studioXss=true">', note_id: id };
        } else if (path === '/api/player-stats') {
            data = { total_xp: 10, level: 1, rank_badge: 'fa-award', rank_title: 'Scribe', progress_percent: 10, mana: '30/100 MP' };
        } else if (request.method() === 'DELETE') {
            notes.delete(id);
        } else if (path.endsWith('/duplicate')) {
            data = { ...note, id: ++sequence, title: note.title + ' (Copy)' };
            notes.set(data.id, data);
        } else if (request.method() === 'PUT') {
            data = { ...note, ...request.postDataJSON(), updated_at: new Date().toISOString() };
            notes.set(id, data);
        } else if (path.endsWith('/pin') || path.endsWith('/favorite')) {
            const key = path.endsWith('/pin') ? 'is_pinned' : 'is_favorite';
            note[key] = !note[key];
            await route.fulfill({ json: { status: 'success', [key]: note[key] } });
            return;
        } else if (path === '/api/notes' && request.method() === 'POST') {
            data = { ...request.postDataJSON(), id: ++sequence };
            notes.set(data.id, data);
        } else {
            const query = new URL(request.url()).searchParams.get('q')?.toLowerCase();
            data = note || [...notes.values()].filter(item => !query || (item.title + item.content).toLowerCase().includes(query));
        }
        await route.fulfill({ json: { status: 'success', data } });
    });
    await page.route('http://127.0.0.1:18588/', async route => {
        const response = await route.fetch();
        let html = await response.text();
        html = html.replace(/(<script id="initial-notes-data"[^>]*>)[\s\S]*?(<\/script>)/, (_, start, end) => start + JSON.stringify([...notes.values()]) + end);
        html = html.replace(/(id="initial-active-note-id" value=")[^"]*/, '$11');
        await route.fulfill({ response, body: html });
    });
    await page.goto('http://127.0.0.1:18588/');
    await page.waitForFunction(() => window.notenux?.activeNote?.id === 1);
    await page.locator('#editor-content').fill('Updated Alpha manuscript');
    await page.locator('[data-note-id="2"]').click();
    await page.waitForFunction(() => window.notenux.activeNote.id === 2);
    assert(notes.get(1).content.includes('Updated Alpha'), 'Switching must save Alpha before loading Beta');
    assert(await page.locator('#editor-content').innerText() === 'Beta manuscript', 'Beta content must not be overwritten');

    await page.locator('#search-notes-input').fill('Updated Alpha');
    await page.waitForFunction(() => window.notenux.searchResultIds !== null);
    assert(await page.locator('.note-list-card').count() === 1, 'Search includes manuscript content');
    await page.locator('#search-notes-input').fill('');
    await page.locator('#sort-notes-select').selectOption('title_asc');
    assert(await page.locator('.note-list-card').first().getAttribute('data-note-id') === '1', 'Title sorting');

    const themeBefore = await page.locator('html').getAttribute('data-theme');
    await page.locator('#theme-toggle-btn').click();
    assert(await page.locator('html').getAttribute('data-theme') !== themeBefore, 'Theme toggles');
    await page.locator('#crt-toggle-btn').click();
    assert(await page.locator('body').evaluate(element => element.classList.contains('crt-mode')), 'CRT enabled');
    await page.locator('#crt-toggle-btn').click();
    await page.locator('#zen-mode-btn').click();
    assert(await page.locator('body').evaluate(element => element.classList.contains('zen-mode')), 'Zen enabled');
    await page.keyboard.press('Escape');
    assert(!await page.locator('body').evaluate(element => element.classList.contains('zen-mode')), 'Escape exits Zen');

    await page.locator('#font-family-select').selectOption('serif');
    await page.locator('#font-size-select').selectOption('24px');
    await page.locator('#save-now-btn').click();
    await page.waitForFunction(() => !window.notenux.dirtyNotes.has(2));
    assert(notes.get(2).font_family === 'serif' && notes.get(2).font_size === '24px', 'Typography persists');

    await page.locator('#delete-note-btn').click();
    await page.locator('#cancel-delete-modal-btn').click();
    assert(notes.has(2), 'Cancel deletion preserves note');
    await page.locator('#editor-content').fill('Beta Beta');
    await page.locator('#find-replace-toggle-btn').click();
    await page.locator('#find-input').fill('Beta');
    await page.locator('#replace-input').fill('Chapter');
    await page.locator('#replace-all-btn').click();
    await page.locator('#save-now-btn').click();
    await page.waitForFunction(() => !window.notenux.dirtyNotes.has(2));
    assert(notes.get(2).content.includes('Chapter Chapter'), 'Replace all persists');
    assert(!notes.get(2).content.includes('search-match'), 'Search highlights must never persist');
    await page.keyboard.press('Escape');

    await page.locator('#markdown-view-toggle-btn').click();
    await page.locator('#markdown-raw-editor').fill('# Heading\n\n**Strong text**');
    await page.locator('#markdown-view-toggle-btn').click();
    assert(await page.locator('#editor-content strong').innerText() === 'Strong text', 'Raw Markdown round trip');

    await page.locator('#editor-content').click();
    await page.keyboard.press('Control+End');
    await page.locator('#insert-table-btn').click();
    assert(await page.locator('#editor-content table').count() === 1, 'Table insertion');
    await page.locator('#editor-content').click();
    await page.keyboard.press('Control+End');
    await page.locator('#insert-task-btn').click();
    const checkbox = page.locator('#editor-content input[type="checkbox"]').first();
    await checkbox.check();
    assert(await checkbox.getAttribute('checked') !== null, 'Task checked state persists as HTML');
    const richHtml = await page.locator('#editor-content').innerHTML();
    await page.locator('#markdown-view-toggle-btn').click();
    await page.locator('#markdown-view-toggle-btn').click();
    assert(await page.locator('#editor-content').innerHTML() === richHtml, 'Opening Markdown without edits is lossless');

    await page.locator('summary').filter({ hasText: 'AI ASSISTANT' }).click();
    await page.locator('#ai-generate-btn').click();
    await page.locator('#ai-append-btn').waitFor({ state: 'visible' });
    await page.waitForFunction(() => !document.getElementById('ai-append-btn').disabled);
    assert(!(await page.locator('#editor-content').innerText()).includes('AI suggestion'), 'AI preview must not change manuscript');
    await page.locator('#ai-append-btn').click();
    assert((await page.locator('#editor-content').innerText()).includes('AI suggestion'), 'Explicit AI apply works');
    assert(!await page.evaluate(() => window.studioXss), 'AI HTML cannot execute scripts');
    assert(await page.locator('#editor-content [onerror]').count() === 0, 'AI handlers stripped');

    await page.locator('#ai-generate-btn').click();
    await page.waitForFunction(() => !document.getElementById('ai-replace-btn').disabled);
    await page.locator('#editor-content').fill('Newer manual text');
    await page.locator('#ai-replace-btn').click();
    assert(await page.locator('#editor-content').innerText() === 'Newer manual text', 'Stale AI results cannot overwrite new writing');

    await page.locator('#pin-note-btn').click();
    await page.waitForFunction(() => window.notenux.activeNote.is_pinned);
    await page.locator('#favorite-note-btn').click();
    await page.waitForFunction(() => window.notenux.activeNote.is_favorite);
    await page.locator('#save-now-btn').click();
    await page.waitForFunction(() => !window.notenux.dirtyNotes.has(2));
    assert(notes.get(2).is_pinned && notes.get(2).is_favorite, 'Autosave preserves pin and favorite');

    await page.locator('#zoom-in-btn').click({ clickCount: 12 });
    assert(await page.evaluate(() => window.notenux.currentZoom) <= 200, 'Zoom upper bound');
    await page.locator('#zoom-out-btn').click({ clickCount: 20 });
    assert(await page.evaluate(() => window.notenux.currentZoom) >= 60, 'Zoom lower bound');
    await page.setViewportSize({ width: 390, height: 844 });
    await page.locator('#mobile-explorer-toggle-btn').click();
    await page.locator('#sidebar-overlay').click({ position: { x: 380, y: 700 } });
    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.locator('#duplicate-note-btn').click();
    await page.waitForFunction(() => window.notenux.activeNote.title.endsWith('(Copy)'));
    assert(notes.get(101).content === notes.get(2).content && notes.get(101).content.includes('Newer manual text'), 'Duplicate includes latest unsaved text');
    await page.locator('#archive-note-btn').click();
    await page.waitForFunction(() => !window.notenux.dirtyNotes.has(101));
    assert(notes.get(101).category === 'archive', 'Archive persists');
    await page.locator('#delete-note-btn').click();
    await page.locator('#confirm-delete-modal-btn').click();
    await page.waitForFunction(() => !window.notenux.notes.some(note => note.id === 101));
    assert(!notes.has(101), 'Confirmed deletion removes only target');
    assert(errors.length === 0, errors.join('\n'));
    await page.unrouteAll({ behavior: 'wait' });
    return { passed: true, checks: ['autosave on switch', 'search', 'sort', 'theme', 'CRT', 'Zen', 'typography', 'delete cancel and confirm', 'duplicate', 'archive', 'find replace', 'markdown', 'tables', 'checklist', 'lossless raw toggle', 'AI preview and apply', 'stale AI guard', 'XSS safety', 'pin/favorite', 'zoom bounds', 'mobile drawers'], errors };
}
