<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * / used to be the Laravel welcome page while the real homepage was built at
 * /mockups/hero-banner. The header logo links to /, so every page on the site
 * depends on / being the homepage.
 */
class HomepageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_root_url_renders_the_homepage_with_the_site_header(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertViewIs('mockups.hero-banner')
            ->assertSee('<header class="ams-site-header', false)
            ->assertSee('aria-label="AMS Training home"', false)
            ->assertDontSee('laravel.com/docs', false)
            // The server has no Node, and public/build is not committed.
            ->assertDontSee('/build/', false);
    }

    public function test_the_root_url_is_named_home(): void
    {
        $this->assertSame(url('/'), route('home'));
    }

    public function test_the_old_mockup_url_permanently_redirects_to_the_homepage(): void
    {
        $this->get('/mockups/hero-banner')
            ->assertStatus(301)
            ->assertRedirect('/');
    }
}
