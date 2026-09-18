<?php

namespace Database\Seeders;

use App\Models\Review;
use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
{
    /**
     * PLACEHOLDER RECORDS FOR DEVELOPMENT ONLY — DELETE BEFORE LAUNCH.
     *
     * These are NOT real AMS reviews and are not written to look like any. No
     * genuine review copy exists anywhere in the project, and inventing
     * customer testimonials would put fabricated social proof on a live page.
     * Every record below names itself as a placeholder so it cannot be mistaken
     * for real feedback if one is ever missed.
     *
     * Deliberately NOT registered in DatabaseSeeder: it must be an explicit,
     * conscious command. Run with:
     *     php artisan db:seed --class=ReviewSeeder
     * Remove them all again with:
     *     php artisan tinker --execute="App\Models\Review::where('source','PLACEHOLDER')->delete();"
     *
     * Idempotent: keyed on reviewer_name, so re-running updates rather than
     * duplicating.
     */
    public function run(): void
    {
        $placeholders = [
            ['Placeholder Reviewer One', 5],
            ['Placeholder Reviewer Two', 4],
            ['Placeholder Reviewer Three', 5],
            ['Placeholder Reviewer Four', 3],
            ['Placeholder Reviewer Five', 5],
            ['Placeholder Reviewer Six', 4],
        ];

        foreach ($placeholders as $index => [$name, $rating]) {
            $review = Review::firstOrNew(['reviewer_name' => $name]);

            $review->rating = $rating;
            $review->review_text = 'TEMPORARY DEVELOPMENT RECORD — this is not a real customer review. It exists only so the carousel can be checked with content in it, and should be deleted once genuine reviews have been entered at Admin → Reviews. This sentence also makes the card long enough to exercise the line clamp and the Read more control.';
            $review->review_date = null;
            $review->reviewer_avatar = null;
            $review->source = 'PLACEHOLDER';
            $review->source_url = null;
            $review->display_order = $index + 1;
            $review->is_active = true;

            $review->save();
        }
    }
}
