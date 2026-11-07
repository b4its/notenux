async (page) => {
    await page.unrouteAll({ behavior: 'wait' });
    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.addInitScript(() => {
        localStorage.setItem('notenux_sound', 'muted');
        localStorage.setItem('notenux_primary_collapsed', 'false');
        localStorage.setItem('notenux_explorer_collapsed', 'false');
        for (const key of Object.keys(localStorage)) {
            if (/^notenux_draft_(1|99)$/.test(key)) localStorage.removeItem(key);
        }
    });
    const errors = [];
    const onError = error => errors.push(error.message);
    page.on('pageerror', onError);
    const assert = (value, message) => { if (!value) throw new Error(message); };
    const categories = [
        { id: 1, name: 'Quests', slug: 'quest', icon: 'fa-solid fa-khanda', color: 'amber' },
        { id: 2, name: 'Lore', slug: 'lore', icon: 'fa-solid fa-scroll', color: 'cyan' },
    ];
    let chunks = [
        { id: 11, chunk_uid: 'chk_browser_first', chunk_index: 0, content: '<p>First block</p>', chunk_type: 'paragraph', checksum: 'first', byte_size: 18 },
        { id: 12, chunk_uid: 'chk_browser_second', chunk_index: 1, content: '<p>Second block</p>', chunk_type: 'paragraph', checksum: 'second', byte_size: 19 },
    ];
    const notes = new Map([[1, { id: 1, title: 'Integrity manuscript', category: 'quest', content: chunks.map(chunk => chunk.content).join(''), font_family: 'mono', font_size: '16px', updated_at: '2026-01-01T00:00:00Z', chunks }]]);
    let rejectReorder = true;
    let cancelAi;
    let aiStarted;
    let aiMode = 'error';
    const syncChunks = () => {
        chunks.forEach((chunk, index) => { chunk.chunk_index = index; });
        Object.assign(notes.get(1), { content: chunks.map(chunk => chunk.content).join(''), chunks });
    };
    const choose = async (id, value) => {
        const container = page.locator(`#${id}`).locator('..');
        await container.locator('.retro-select-trigger').click();
        await container.locator(`.retro-select-option-item[data-value="${value}"]`).click();
    };
    await page.route('**/api/**', async route => {
        const request = route.request();
        const path = new URL(request.url()).pathname;
        const method = request.method();
        const id = Number(path.split('/')[3]);
        const payload = ['POST', 'PUT'].includes(method) ? request.postDataJSON() : null;
        let data;
        if (path.startsWith('/api/categories')) {
            if (method === 'POST') {
                data = { ...payload, id: 10, slug: payload.slug || 'research' };
                categories.push(data);
            } else if (method === 'PUT') {
                const category = categories.find(item => item.id === id);
                const oldSlug = category.slug;
                Object.assign(category, payload);
                for (const note of notes.values()) if (note.category === oldSlug) note.category = category.slug;
                data = category;
            } else if (method === 'DELETE') {
                const category = categories.find(item => item.id === id);
                categories.splice(categories.indexOf(category), 1);
                for (const note of notes.values()) if (note.category === category.slug) note.category = 'quest';
                data = { fallback_slug: 'quest', fallback_name: 'Quests', fallback_category: categories[0] };
            } else data = categories;
        } else if (path.endsWith('/chunks/reorder')) {
            if (rejectReorder) {
                rejectReorder = false;
                await route.fulfill({ status: 422, json: { status: 'error', message: 'Simulated invalid order' } });
                return;
            }
            chunks = payload.order.map(chunkId => chunks.find(chunk => chunk.id === chunkId));
            syncChunks();
            data = { chunks, note: notes.get(1) };
        } else if (path.endsWith('/chunks/verify')) {
            data = { verified: true, total_chunks: chunks.length, corrupted_chunks: 0 };
        } else if (/\/chunks\/\d+$/.test(path)) {
            const chunkId = Number(path.split('/').at(-1));
            if (method === 'PUT') Object.assign(chunks.find(chunk => chunk.id === chunkId), { content: payload.content });
            else chunks = chunks.filter(chunk => chunk.id !== chunkId);
            syncChunks();
            data = { note: notes.get(1), remaining_chunks: chunks.length };
        } else if (path.endsWith('/chunks')) {
            await route.fulfill({ json: { status: 'success', chunks, total_chunks: chunks.length, total_bytes: 37 } });
            return;
        } else if (path === '/api/notes/import') {
            data = { id: 99, title: payload.title || 'Imported', content: '<h1>Imported</h1><p>Imported body</p>', category: payload.category || 'quest' };
            notes.set(99, data);
        } else if (path.includes('/export/')) {
            await route.fulfill({ contentType: 'text/markdown', headers: { 'Content-Disposition': 'attachment; filename="studio-test.md"' }, body: notes.get(id).content });
            return;
        } else if (path.endsWith('/assistant')) {
            if (aiMode === 'error') {
                await route.fulfill({ status: 503, json: { message: 'AI unavailable for test' } });
                return;
            }
            aiStarted?.();
            await new Promise(resolve => { cancelAi = resolve; });
            try { await route.fulfill({ json: { status: 'success', data: { content: 'Cancelled output', note_id: id } } }); } catch (_) {}
            return;
        } else if (path === '/api/player-stats') {
            data = { total_xp: 10, level: 1, rank_badge: 'fa-award', rank_title: 'Scribe', progress_percent: 10, mana: '30/100 MP' };
        } else if (method === 'PUT' && notes.has(id)) {
            Object.assign(notes.get(id), payload, { updated_at: new Date().toISOString() });
            data = notes.get(id);
        } else {
            await route.fulfill({ status: 500, json: { message: `Unexpected test request: ${method} ${path}` } });
            return;
        }
        await route.fulfill({ json: { status: 'success', data } });
    });
    await page.route('http://127.0.0.1:18588/', async route => {
        const response = await route.fetch();
        let html = await response.text();
        const json = value => JSON.stringify(value).replace(/</g, '\\u003c');
        html = html.replace(/(<script id="initial-notes-data"[^>]*>)[\s\S]*?(<\/script>)/, (_, start, end) => start + json([...notes.values()]) + end);
        html = html.replace(/(<script id="initial-categories-data"[^>]*>)[\s\S]*?(<\/script>)/, (_, start, end) => start + json(categories) + end);
        html = html.replace(/(id="initial-active-note-id" value=")[^"]*/, (_, start) => start + '1');
        await route.fulfill({ response, body: html });
    });
    try {
        await page.goto('http://127.0.0.1:18588/');
        await page.waitForFunction(() => window.notenux?.activeNote?.id === 1);

        await page.locator('#font-family-select').locator('..').locator('.retro-select-trigger').click();
        await page.locator('#font-size-select').locator('..').locator('.retro-select-trigger').click();
        await page.locator('#font-family-select').locator('..').locator('.retro-select-trigger').click();
        assert(await page.locator('#font-family-select').locator('..').locator('.retro-select-dropdown').isVisible(), 'Previously closed dropdown reopens with one click');
        await page.keyboard.press('Escape');

        await page.locator('#quick-forge-category-btn').click();
        const unsafeName = 'Research "<img src=x onerror=window.categoryXss=true>"';
        await page.locator('#quick-forge-cat-name').fill(unsafeName);
        await page.locator('#quick-forge-cat-slug').fill('research');
        await page.locator('#quick-forge-category-form button[type="submit"]').click();
        await page.waitForFunction(() => window.notenux.activeNote.category === 'research' && !window.notenux.dirtyNotes.has(1));
        assert(await page.locator('#notes-list [onerror], #sidebar-categories-list [onerror]').count() === 0, 'Category HTML is escaped everywhere');
        assert(!await page.evaluate(() => window.categoryXss), 'Category name cannot execute HTML');
        assert(await page.locator('#quick-forge-category-modal').isHidden(), 'Successful forge closes modal without audio errors');

        await page.locator('#quick-edit-category-btn').click();
        await page.locator('#quick-edit-cat-name').fill('Renamed Research');
        await page.locator('#quick-edit-cat-slug').fill('renamed');
        await page.locator('#quick-edit-category-form button[type="submit"]').click();
        await page.waitForFunction(() => window.notenux.activeNote.category === 'renamed');
        assert(notes.get(1).category === 'renamed', 'Category rename updates persisted and in-memory notes');
        await page.locator('#quick-delete-category-btn').click();
        await page.locator('#confirm-quick-delete-cat-btn').click();
        await page.waitForFunction(() => window.notenux.activeNote.category === 'quest');
        assert(notes.has(1) && notes.get(1).category === 'quest', 'Category delete preserves manuscript');

        await page.locator('#chunk-count-badge').click();
        await page.locator('.chunk-card').first().waitFor();
        await page.locator('.chunk-card').first().getByTitle('Move Shard Down').click();
        await page.getByText('Failed to reorder shards', { exact: true }).waitFor();
        assert(await page.locator('.chunk-card code').first().innerText() === 'chk_browser_first', 'Failed reorder leaves displayed order unchanged');
        await page.locator('.chunk-card').first().getByTitle('Move Shard Down').click();
        await page.waitForFunction(() => window.notenux.cachedChunks[0].id === 12);
        assert(notes.get(1).content.startsWith('<p>Second'), 'Successful reorder updates manuscript');
        await page.locator('.chunk-card').first().getByRole('button', { name: 'Edit', exact: true }).click();
        await page.locator('#chunk-edit-12 textarea').fill('<p>Edited block</p>');
        await page.locator('#chunk-edit-12').getByRole('button', { name: 'Save Shard' }).click();
        await page.waitForFunction(() => window.notenux.activeNote.content.startsWith('<p>Edited'));
        await page.locator('#verify-chunks-btn').click();
        await page.locator('#chunk-verify-status').getByText(/All 2 shards verified/).waitFor();
        await page.locator('.chunk-card').first().getByTitle('Delete Shard').click();
        assert(chunks.length === 2, 'First delete click only asks for confirmation');
        await page.getByRole('button', { name: 'Confirm delete shard', exact: true }).click();
        await page.waitForFunction(() => window.notenux.activeNote.chunks?.length === 1);
        assert(notes.get(1).content === '<p>First block</p>', 'Deleting one shard preserves the remaining text');
        await page.keyboard.press('Escape');

        await page.locator('#import-note-btn').click();
        await page.locator('#import-title-input').fill('Imported manuscript');
        await page.locator('#import-raw-content').fill('# Imported\n\nImported body');
        await page.locator('#confirm-import-btn').click();
        await page.waitForFunction(() => window.notenux.activeNote.id === 99);
        const downloadPromise = page.waitForEvent('download');
        await page.locator('#export-note-btn').click();
        await page.locator('[data-export-format="md"]').click();
        assert((await downloadPromise).suggestedFilename() === 'studio-test.md', 'Export downloads a named file');

        await page.locator('summary').filter({ hasText: 'AI ASSISTANT' }).click();
        await page.locator('#ai-generate-btn').click();
        await page.locator('#ai-status').getByText('AI unavailable for test', { exact: true }).waitFor();
        assert(await page.locator('#ai-append-btn').isDisabled(), 'AI failure never enables apply');
        aiMode = 'hold';
        const started = new Promise(resolve => { aiStarted = resolve; });
        await page.locator('#ai-generate-btn').click();
        await started;
        await page.locator('#ai-cancel-btn').click();
        await page.locator('#ai-status').getByText(/dibatalkan/).waitFor();
        cancelAi();
        assert(notes.get(99).content.includes('Imported body'), 'Cancelling AI preserves original content');
        await choose('font-size-select', '20px');
        await page.locator('#save-now-btn').click();
        await page.waitForFunction(() => !window.notenux.dirtyNotes.has(99));
        assert(errors.length === 0, errors.join('\n'));
        return { passed: true, checks: ['dropdown state', 'category forge', 'category escaping', 'category rename', 'category delete preservation', 'chunk badge', 'failed reorder', 'successful reorder', 'chunk edit', 'integrity verification', 'two-step shard delete', 'import', 'export download', 'AI failure', 'AI cancellation'], errors };
    } finally {
        cancelAi?.();
        page.off('pageerror', onError);
        await page.unrouteAll({ behavior: 'wait' });
    }
}
