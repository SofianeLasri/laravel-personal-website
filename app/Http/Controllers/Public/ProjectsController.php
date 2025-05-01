<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Creation;
use App\Models\SocialMediaLink;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ProjectsController extends Controller
{
    private string $locale = 'fr';

    public function __invoke()
    {
        $this->locale = app()->getLocale();

        return inertia('public/Projects', [
            'locale' => $this->locale,
            'translations' => [
                'projects' => __('projects'),
            ],
            'socialMediaLinks' => $this->getSocialMediaLinks(),
            'creations' => $this->getCreations(),
        ]);
    }

    private function getSocialMediaLinks(): Collection
    {
        return SocialMediaLink::all();
    }

    private function getCreations(): Collection
    {
        $creations = Creation::with([
            'technologies',
            'logo',
            'coverImage',
            'shortDescriptionTranslationKey.translations' => function ($query) {
                $query->where('locale', $this->locale);
            },
        ])->get();

        return $creations->map(function (Creation $creation) {
            $shortDescriptionTranslation = $creation->shortDescriptionTranslationKey->translations->first();

            return [
                'id' => $creation->id,
                'name' => $creation->name,
                'slug' => $creation->slug,
                'logo' => $creation->logo->getUrl('medium', 'avif'),
                'coverImage' => $creation->coverImage->getUrl('medium', 'avif'),
                'startedAt' => $creation->started_at,
                'endedAt' => $creation->ended_at,
                'startedAtFormatted' => $this->formatDate($creation->started_at),
                'endedAtFormatted' => $this->formatDate($creation->ended_at),
                'type' => $creation->type->label(),
                'shortDescription' => $shortDescriptionTranslation ? $shortDescriptionTranslation->text : '',
                'technologies' => $creation->technologies->map(function ($technology) {
                    return [
                        'name' => $technology->name,
                        'svgIcon' => $technology->svg_icon,
                    ];
                }),
            ];
        });
    }

    /**
     * Format a date according to the user's preferred locale with month in CamelCase.
     *
     * @param  string|Carbon|null  $date  The date to format
     * @return string|null Formatted date or null
     */
    private function formatDate(Carbon|string|null $date): ?string
    {
        if (! $date) {
            return null;
        }

        if (! $date instanceof Carbon) {
            $date = Carbon::parse($date);
        }

        $month = Str::ucfirst($date->translatedFormat('F'));

        return $month.' '.$date->format('Y');
    }
}
