<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoursePackage extends Model
{
    protected $fillable = [
        'title',
        'codes',
        'banner_text',
        'course_url',
        'image_1',
        'icon_1',
        'image_2',
        'icon_2',
        'image_3',
        'icon_3',
        'display_order',
        'show_on_homepage',
        'status',
    ];

    protected $casts = [
        'display_order' => 'integer',
        'show_on_homepage' => 'boolean',
    ];

    /**
     * Ordered image/icon pairs that actually have an image, for the card stack.
     *
     * @return array<int, array{image: string, icon: ?string}>
     */
    public function imageItems(): array
    {
        $items = [];

        foreach ([1, 2, 3] as $i) {
            $image = $this->{"image_{$i}"};

            if (filled($image)) {
                $items[] = [
                    'image' => $image,
                    'icon' => $this->{"icon_{$i}"},
                ];
            }
        }

        return $items;
    }
}
