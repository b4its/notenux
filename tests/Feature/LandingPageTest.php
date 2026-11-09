<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('landing page renders successfully for guests with interactive sections', function () {
    $response = $this->get(route('landing'));

    $response->assertOk()
        ->assertViewIs('landing')
        ->assertSee('NOTENUX')
        ->assertSee('Tingkatkan Level Scribe')
        ->assertSee('BUKA STUDIO')
        ->assertSee('hero-studio-cta')
        ->assertSee('hero-register-cta')
        ->assertSee('hero-login-cta')
        ->assertSee('theme-toggle-btn')
        ->assertSee('demo-editor-input')
        ->assertSee('demo-xp-bar')
        ->assertSee('PERLINDUNGAN DATA OTOMATIS')
        ->assertSee('DISTRACTION-FREE ZEN MODE')
        ->assertDontSee('CHUNK MATRIX ENGINE')
        ->assertDontSee('Arsitektur Shard Database')
        ->assertSee('font-awesome', false);
});

test('landing page includes dark and light theme toggle support', function () {
    $response = $this->get(route('landing'));

    $response->assertOk()
        ->assertSee('data-theme="dark"', false)
        ->assertSee('theme-toggle-btn')
        ->assertSee('TEMA');
});

test('landing page renders authenticated user session controls', function () {
    $user = User::factory()->create([
        'name' => 'Commander Cyber',
    ]);

    $response = $this->actingAs($user)->get(route('landing'));

    $response->assertOk()
        ->assertSee('Commander Cyber')
        ->assertSee('nav-studio-btn')
        ->assertSee('nav-logout-btn')
        ->assertDontSee('hero-register-cta');
});

test('main cta links directly to studio route', function () {
    $response = $this->get(route('landing'));

    $response->assertOk()
        ->assertSee(route('studio'), false);
});

test('landing page and studio render horizontal, vertical, and icon logos with dark/light orientation', function () {
    $response = $this->get(route('landing'));

    $response->assertOk()
        ->assertSee('assets/logo/dark/icon_horizontal_dark.webp')
        ->assertSee('assets/logo/light/icon_horizontal_light.webp')
        ->assertSee('assets/logo/dark/icon_vertical_dark.webp')
        ->assertSee('assets/logo/light/icon_vertical_light.webp')
        ->assertSee('assets/logo/dark/icon_dark.webp')
        ->assertSee('decoding="async"', false)
        ->assertSee('loading="lazy"', false);
});

test('landing page uses light favicon by default and includes dynamic theme adaptation logic', function () {
    $response = $this->get(route('landing'));

    $lightFavicon = asset('assets/logo/light/icon_light.webp');
    $darkFavicon = asset('assets/logo/dark/icon_dark.webp');

    $response->assertOk()
        // Default HTML favicon must be light
        ->assertSee('<link rel="icon" id="app-favicon" type="image/webp" href="'.$lightFavicon.'">', false)
        ->assertSee('<link rel="apple-touch-icon" id="app-apple-touch-icon" href="'.$lightFavicon.'">', false)
        // Dynamic theme-switching script variables
        ->assertSee('const lightFavicon = "'.$lightFavicon.'";', false)
        ->assertSee('const darkFavicon = "'.$darkFavicon.'";', false)
        ->assertSee('window.notenuxFaviconLight = lightFavicon;', false)
        ->assertSee('window.notenuxFaviconDark = darkFavicon;', false)
        ->assertSee('applyFavicon', false);
});

test('all major views use light favicon by default and contain dynamic favicon theme synchronization', function () {
    $user = User::factory()->create();

    // Guest views
    foreach ([route('landing'), route('login'), route('register')] as $url) {
        $response = $this->get($url);
        $response->assertOk()
            ->assertSee('id="app-favicon"', false)
            ->assertSee('id="app-apple-touch-icon"', false)
            ->assertSee('assets/logo/light/icon_light.webp', false)
            ->assertSee('assets/logo/dark/icon_dark.webp', false)
            ->assertSee('applyFavicon', false);
    }

    // Authenticated views
    foreach ([route('studio'), route('categories.index')] as $url) {
        $response = $this->actingAs($user)->get($url);
        $response->assertOk()
            ->assertSee('id="app-favicon"', false)
            ->assertSee('id="app-apple-touch-icon"', false)
            ->assertSee('assets/logo/light/icon_light.webp', false)
            ->assertSee('assets/logo/dark/icon_dark.webp', false)
            ->assertSee('applyFavicon', false);
    }
});

test('landing page contains rich interactive hero station with quests, hasher, and font lab', function () {
    $response = $this->get(route('landing'));

    $response->assertOk()
        ->assertSee('station-panel-quests')
        ->assertSee('hero-quest-xp-bar')
        ->assertSee('hero-quest-rank')
        ->assertSee('hero-reset-quests-btn')
        ->assertSee('station-panel-hasher')
        ->assertSee('hasher-input')
        ->assertSee('hasher-hash-output')
        ->assertSee('copy-hash-btn')
        ->assertSee('station-panel-fonts')
        ->assertSee('hero-font-preview-box');
});

test('landing page contains enhanced interactive playground with spell bar, counters, and actions', function () {
    $response = $this->get(route('landing'));

    $response->assertOk()
        ->assertSee('spell-btn')
        ->assertSee('demo-copy-btn')
        ->assertSee('demo-download-btn')
        ->assertSee('template-table-btn')
        ->assertSee('demo-line-count')
        ->assertSee('demo-read-time')
        ->assertSee('toggle-preview-btn');
});

test('landing page contains interactive shard tamper and recovery simulator deck', function () {
    $response = $this->get(route('landing'));

    $response->assertOk()
        ->assertSee('sim-corrupt-btn')
        ->assertSee('sim-repair-btn')
        ->assertSee('sim-status-banner')
        ->assertSee('sim-shard-2-box')
        ->assertSee('sim-shard-2-badge')
        ->assertSee('sim-shard-2-hash');
});

test('landing page contains rpg scribe rank codex and floating sprite companion', function () {
    $response = $this->get(route('landing'));

    $response->assertOk()
        ->assertSee('rank-codex')
        ->assertSee('rank-card')
        ->assertSee('rank-detail-card')
        ->assertSee('rank-detail-name')
        ->assertSee('scribe-companion')
        ->assertSee('companion-avatar-btn')
        ->assertSee('companion-bubble')
        ->assertSee('faq-search-input');
});

test('all brand logo assets have transparent alpha channel and no solid background container', function () {
    $logoFiles = [
        public_path('assets/logo/dark/icon_dark.png'),
        public_path('assets/logo/dark/icon_horizontal_dark.png'),
        public_path('assets/logo/dark/icon_vertical_dark.png'),
        public_path('assets/logo/light/icon_horizontal_light.png'),
        public_path('assets/logo/light/icon_light.png'),
        public_path('assets/logo/light/icon_vertical_light.png'),
        public_path('assets/logo/icon_dark.png'),
        public_path('assets/logo/icon_light.png'),
    ];

    foreach ($logoFiles as $filePath) {
        expect(file_exists($filePath))->toBeTrue();
        $im = imagecreatefrompng($filePath);
        expect($im)->not->toBeFalse();
        $corner = imagecolorsforindex($im, imagecolorat($im, 0, 0));
        // GD alpha of 127 signifies 100% transparency
        expect($corner['alpha'])->toBeGreaterThan(100);
        imagedestroy($im);
    }
});

test('all webp logo assets are generated, valid RIFF/WEBP format, and optimized under 50KB', function () {
    $webpFiles = [
        public_path('assets/logo/dark/icon_dark.webp'),
        public_path('assets/logo/dark/icon_horizontal_dark.webp'),
        public_path('assets/logo/dark/icon_vertical_dark.webp'),
        public_path('assets/logo/light/icon_horizontal_light.webp'),
        public_path('assets/logo/light/icon_light.webp'),
        public_path('assets/logo/light/icon_vertical_light.webp'),
        public_path('assets/logo/icon_dark.webp'),
        public_path('assets/logo/icon_light.webp'),
    ];

    foreach ($webpFiles as $filePath) {
        expect(file_exists($filePath))->toBeTrue();
        expect(filesize($filePath))->toBeLessThan(50000); // Drastically smaller than 1MB PNGs

        $handle = fopen($filePath, 'rb');
        $header = fread($handle, 12);
        fclose($handle);

        expect(substr($header, 0, 4))->toBe('RIFF');
        expect(substr($header, 8, 4))->toBe('WEBP');
    }
});

test('telemetry strip omits db shards and xss sanitized while keeping manuscripts and rpg leveling', function () {
    $response = $this->get(route('landing'));

    $response->assertOk()
        ->assertSee('MANUSCRIPTS')
        ->assertSee('RPG LEVELING')
        ->assertDontSee('DB SHARDS')
        ->assertDontSee('XSS SANITIZED');
});

test('landing page focuses on user benefits and capabilities without revealing internal architecture', function () {
    $response = $this->get(route('landing'));

    $response->assertOk()
        ->assertSee('PERLINDUNGAN DATA OTOMATIS')
        ->assertSee('Bebas Risiko Kehilangan')
        ->assertSee('Respons Cepat Tanpa Lemot')
        ->assertSee('Pemulihan 1-Klik yang Tenang')
        ->assertSee('DISTRACTION-FREE ZEN MODE')
        ->assertSee('MULTI-FONT RETRO STUDIO')
        ->assertSee('DUAL THEME & CRT FX', false)
        ->assertSee('UNIVERSAL ZERO LOCK-IN')
        ->assertDontSee('CHUNK MATRIX ENGINE')
        ->assertDontSee('Arsitektur Shard Database')
        ->assertDontSee('Semantic Sharding')
        ->assertDontSee('NoteChunk');
});

test('sitemap and robots.txt are accessible and properly configured', function () {
    $sitemapRes = $this->get('/sitemap.xml');
    $sitemapRes->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee('https://notenux.oryphem.com/', false);

    $robotsRes = $this->get('/robots.txt');
    $robotsRes->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('Sitemap: https://notenux.oryphem.com/sitemap.xml', false);

    $robotsContent = file_get_contents(public_path('robots.txt'));
    expect($robotsContent)->toContain('Sitemap: https://notenux.oryphem.com/sitemap.xml')
        ->and($robotsContent)->toContain('Allow: /login')
        ->and($robotsContent)->toContain('Disallow: /folders')
        ->and($robotsContent)->toContain('Disallow: /profile');
});

test('landing page header renders responsive mobile menu drawer and mascot sprite starts collapsed', function () {
    $response = $this->get(route('landing'));

    $response->assertOk()
        ->assertSee('id="landing-mobile-menu-btn"', false)
        ->assertSee('id="landing-mobile-menu"', false)
        ->assertSee('hidden xl:flex', false)
        ->assertSee('id="companion-bubble" class="hidden', false);
});
