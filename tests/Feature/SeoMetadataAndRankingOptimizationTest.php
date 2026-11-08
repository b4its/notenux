<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('landing page includes comprehensive seo meta tags, open graph, twitter cards, and canonical link', function () {
    $response = $this->get(route('landing'));

    $response->assertOk()
        ->assertSee('<title>Notenux - Aplikasi Catatan & Studio Tulis Gamifikasi Retro Modern</title>', false)
        ->assertSee('<meta name="description"', false)
        ->assertSee('notenux.oryphem.com', false)
        ->assertSee('<meta name="keywords"', false)
        ->assertSee('https://notenux.oryphem.com', false)
        ->assertSee('<link rel="canonical" href="https://notenux.oryphem.com/">', false)
        ->assertSee('<meta name="robots" content="index, follow', false)
        ->assertSee('<meta name="googlebot" content="index, follow', false)
        ->assertSee('<meta property="og:type" content="website">', false)
        ->assertSee('<meta property="og:site_name" content="Notenux">', false)
        ->assertSee('<meta property="og:url" content="https://notenux.oryphem.com/">', false)
        ->assertSee('<meta property="og:title" content="Notenux - Aplikasi Catatan & Studio Tulis Gamifikasi Retro Modern">', false)
        ->assertSee('<meta property="og:image"', false)
        ->assertSee('<meta name="twitter:card" content="summary_large_image">', false)
        ->assertSee('<meta name="twitter:url" content="https://notenux.oryphem.com/">', false)
        ->assertSee('<link rel="manifest"', false)
        ->assertSee('site.webmanifest', false);
});

test('landing page contains valid json-ld schema.org structured data for rich snippets', function () {
    $response = $this->get(route('landing'));

    $response->assertOk()
        ->assertSee('<script type="application/ld+json">', false)
        ->assertSee('"@context": "https://schema.org"', false)
        ->assertSee('"@type": "WebSite"', false)
        ->assertSee('"@type": "Organization"', false)
        ->assertSee('"@type": "WebApplication"', false)
        ->assertSee('"@type": "FAQPage"', false)
        ->assertSee('https://notenux.oryphem.com', false)
        ->assertSee('Mengapa saya perlu login untuk membuka Studio Notenux?', false);
});

test('landing page primary heading optimizes brand keyword and scribe rank value proposition', function () {
    $response = $this->get(route('landing'));

    $response->assertOk()
        ->assertSee('Notenux</span> — Abadikan Pemikiran', false)
        ->assertSee('Tingkatkan Level Scribe', false);
});

test('guest authentication pages contain optimized seo meta tags and breadcrumb structured data', function () {
    // Login
    $loginResponse = $this->get(route('login'));
    $loginResponse->assertOk()
        ->assertSee('<title>Masuk ke Studio - Notenux | Catatan Retro Modern</title>', false)
        ->assertSee('<link rel="canonical" href="https://notenux.oryphem.com/login">', false)
        ->assertSee('<meta name="robots" content="index, follow">', false)
        ->assertSee('"@type": "BreadcrumbList"', false)
        ->assertSee('https://notenux.oryphem.com/login', false);

    // Register
    $registerResponse = $this->get(route('register'));
    $registerResponse->assertOk()
        ->assertSee('<title>Daftar Scribe Baru - Notenux | Mulai Petualangan Menulis</title>', false)
        ->assertSee('<link rel="canonical" href="https://notenux.oryphem.com/register">', false)
        ->assertSee('<meta name="robots" content="index, follow">', false)
        ->assertSee('"@type": "BreadcrumbList"', false)
        ->assertSee('https://notenux.oryphem.com/register', false);
});

test('authenticated personal studio and category workspaces enforce noindex for privacy and search hygiene', function () {
    $user = User::factory()->create();

    // Studio
    $studioResponse = $this->actingAs($user)->get(route('studio'));
    $studioResponse->assertOk()
        ->assertSee('<title>Notenux Studio - Editor Catatan & Markdown Gamifikasi</title>', false)
        ->assertSee('<meta name="robots" content="noindex, nofollow, noarchive">', false)
        ->assertSee('<link rel="canonical" href="https://notenux.oryphem.com/studio">', false);

    // Categories
    $categoriesResponse = $this->actingAs($user)->get(route('categories.index'));
    $categoriesResponse->assertOk()
        ->assertSee('<title>Kelola Kategori - Notenux Studio</title>', false)
        ->assertSee('<meta name="robots" content="noindex, nofollow, noarchive">', false)
        ->assertSee('<link rel="canonical" href="https://notenux.oryphem.com/categories">', false);
});

test('robots.txt allows public pages, disallows private api and studio paths, and references sitemap', function () {
    $robotsPath = public_path('robots.txt');
    expect(file_exists($robotsPath))->toBeTrue();

    $content = file_get_contents($robotsPath);
    expect($content)->toContain('User-agent: *')
        ->toContain('Allow: /')
        ->toContain('Disallow: /api/')
        ->toContain('Disallow: /studio')
        ->toContain('Disallow: /categories')
        ->toContain('Sitemap: https://notenux.oryphem.com/sitemap.xml');
});

test('sitemap.xml route and static file serve valid xml linking canonical target urls', function () {
    $sitemapPath = public_path('sitemap.xml');
    expect(file_exists($sitemapPath))->toBeTrue();

    $response = $this->get('/sitemap.xml');
    $response->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee('https://notenux.oryphem.com/', false)
        ->assertSee('https://notenux.oryphem.com/login', false)
        ->assertSee('https://notenux.oryphem.com/register', false);
});

test('site.webmanifest exists and configures valid progressive web app parameters for search discovery', function () {
    $manifestPath = public_path('site.webmanifest');
    expect(file_exists($manifestPath))->toBeTrue();

    $manifest = json_decode((string) file_get_contents($manifestPath), true);
    expect($manifest)->toBeArray()
        ->and($manifest['name'])->toBe('Notenux Studio')
        ->and($manifest['short_name'])->toBe('Notenux')
        ->and($manifest['start_url'])->toBe('https://notenux.oryphem.com/')
        ->and($manifest['display'])->toBe('standalone');
});
