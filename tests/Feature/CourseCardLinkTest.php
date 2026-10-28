<?php

namespace Tests\Feature;

use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The admin's Slug field is the single source of truth for where a homepage
 * course card links. These cover the two ways that used to break: a slug
 * stored as a full pasted URL, and a card linking somewhere else entirely.
 */
class CourseCardLinkTest extends TestCase
{
    use RefreshDatabase;

    private function course(array $attributes = []): Course
    {
        return Course::create(array_merge([
            'title' => 'Work Safely At Heights',
            'code' => 'RIIWHS204E',
            'status' => 'active',
            'price' => 110,
            'show_on_homepage' => true,
        ], $attributes));
    }

    public function test_a_blank_slug_is_generated_from_the_title_and_code(): void
    {
        $this->assertSame(
            'work-safely-at-heights-riiwhs204e',
            $this->course()->slug
        );
    }

    public function test_a_slug_pasted_as_a_full_url_is_reduced_to_the_slug(): void
    {
        // Copying the address out of the browser used to be stored verbatim,
        // which silently 404'd the course page.
        $course = $this->course([
            'slug' => 'http://127.0.0.1:8000/courses/work-safely-at-heights-online-refresher-riiwhs204e/',
        ]);

        $this->assertSame('work-safely-at-heights-online-refresher-riiwhs204e', $course->slug);
    }

    public function test_a_hand_typed_slug_is_left_alone(): void
    {
        $course = $this->course(['slug' => 'my-custom-slug']);

        $this->assertSame('my-custom-slug', $course->slug);
    }

    public function test_the_detail_url_is_built_from_the_slug(): void
    {
        $course = $this->course(['slug' => 'my-custom-slug']);

        $this->assertSame(route('courses.show', 'my-custom-slug'), $course->detailUrl());
        $this->get($course->detailUrl())->assertOk();
    }

    public function test_an_inactive_course_has_no_link(): void
    {
        $course = $this->course(['status' => 'draft', 'slug' => 'my-custom-slug']);

        // The detail route 404s for an inactive course, so the card must not
        // offer a link into it.
        $this->assertNull($course->detailUrl());
    }

    public function test_the_homepage_card_links_to_the_course_page(): void
    {
        $course = $this->course(['slug' => 'my-custom-slug']);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('href="' . route('courses.show', $course->slug) . '"', false);
    }
}
