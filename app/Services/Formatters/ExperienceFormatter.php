<?php

namespace App\Services\Formatters;

use App\Enums\ExperienceType;
use App\Enums\TechnologyType;
use App\Models\Certification;
use App\Models\Experience;
use App\Models\Picture;
use App\Models\Technology;
use App\Models\TechnologyExperience;
use Illuminate\Support\Carbon;

class ExperienceFormatter
{
    /**
     * @var array<int, int|null>|null
     */
    private ?array $creationCountByTechnology = null;

    public function __construct(
        private readonly MediaFormatter $mediaFormatter,
        private readonly TranslationHelper $translationHelper,
    ) {}

    /**
     * Format the Experience model for SSR.
     *
     * @return array{
     *     id: int,
     *     title: string,
     *     organizationName: string,
     *     slug: string,
     *     logo: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}|null,
     *     location: string,
     *     websiteUrl: string|null,
     *     shortDescription: string,
     *     fullDescription: string,
     *     technologies: array<int, array{id: int, creationCount: int, name: string, description: string, type: TechnologyType, iconPicture: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}}>,
     *     type: ExperienceType,
     *     startedAt: string,
     *     endedAt: string|null,
     *     startedAtFormatted: string,
     *     endedAtFormatted: string|null
     * }
     */
    public function formatExperience(Experience $experience): array
    {
        $title = $experience->titleTranslationKey
            ? $this->translationHelper->getWithFallback($experience->titleTranslationKey->translations)
            : '';
        $shortDescription = $experience->shortDescriptionTranslationKey
            ? $this->translationHelper->getWithFallback($experience->shortDescriptionTranslationKey->translations)
            : '';
        $fullDescription = $experience->fullDescriptionTranslationKey
            ? $this->translationHelper->getWithFallback($experience->fullDescriptionTranslationKey->translations)
            : '';

        $startedAtFormatted = $this->translationHelper->formatDate($experience->started_at);
        $endedAtFormatted = $this->translationHelper->formatDate($experience->ended_at);

        return [
            'id' => $experience->id,
            'title' => $title,
            'organizationName' => $experience->organization_name,
            'slug' => $experience->slug,
            'logo' => $experience->logo ? $this->mediaFormatter->formatPicture($experience->logo) : null,
            'location' => $experience->location,
            'websiteUrl' => $experience->website_url,
            'shortDescription' => $shortDescription,
            'fullDescription' => $fullDescription,
            'technologies' => $experience->technologies->map(function (Technology $technology) {
                return $this->formatTechnology($technology);
            })->toArray(),
            'type' => $experience->type,
            'startedAt' => $experience->started_at->toDateString(),
            'endedAt' => $experience->ended_at?->toDateString(),
            'startedAtFormatted' => $startedAtFormatted ?? '',
            'endedAtFormatted' => $endedAtFormatted,
        ];
    }

    /**
     * Format the Certification model for SSR.
     *
     * @return array{
     *     id: int,
     *     name: string,
     *     level: string|null,
     *     score: string|null,
     *     date: string,
     *     dateFormatted: string,
     *     link: string|null,
     *     picture: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}}|null
     * }
     */
    public function formatCertification(Certification $certification): array
    {
        $date = Carbon::parse($certification->date);
        $dateFormatted = $this->translationHelper->formatDate($date);

        return [
            'id' => $certification->id,
            'name' => $certification->name,
            'level' => $certification->level,
            'score' => $certification->score,
            'date' => $date->format('Y-m-d'),
            'dateFormatted' => $dateFormatted ?? '',
            'link' => $certification->link,
            'picture' => ($certification->picture instanceof Picture)
                ? $this->mediaFormatter->formatPicture($certification->picture)
                : null,
        ];
    }

    /**
     * Format the TechnologyExperience model for SSR.
     *
     * @return array{
     *     id: int,
     *     technologyId: int,
     *     name: string,
     *     description: string,
     *     creationCount: int,
     *     type: TechnologyType,
     *     typeLabel: string,
     *     iconPicture: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}
     * }
     */
    public function formatTechnologyExperience(TechnologyExperience $experience): array
    {
        $technologyId = $experience->technology->id;
        $description = $this->translationHelper->getWithFallback($experience->descriptionTranslationKey->translations);

        return [
            'id' => $experience->id,
            'technologyId' => $technologyId,
            'name' => $experience->technology->name,
            'description' => $description,
            'creationCount' => $this->getCreationCountByTechnology()[$technologyId] ?? 0,
            'type' => $experience->technology->type,
            'typeLabel' => $experience->technology->type->label(),
            'iconPicture' => $this->mediaFormatter->formatPicture($experience->technology->iconPicture),
        ];
    }

    /**
     * Format the Technology model for SSR.
     *
     * @return array{id: int, creationCount: int, name: string, description: string, type: TechnologyType, iconPicture: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}}
     */
    public function formatTechnology(Technology $technology): array
    {
        $description = $this->translationHelper->getWithFallback($technology->descriptionTranslationKey->translations);

        return [
            'id' => $technology->id,
            'creationCount' => $this->getCreationCountByTechnology()[$technology->id] ?? 0,
            'name' => $technology->name,
            'description' => $description,
            'type' => $technology->type,
            'iconPicture' => $this->mediaFormatter->formatPicture($technology->iconPicture),
        ];
    }

    /**
     * Set the creation count by technology (useful for dependency injection in tests).
     *
     * @param  array<int, int|null>  $counts
     */
    public function setCreationCountByTechnology(array $counts): void
    {
        $this->creationCountByTechnology = $counts;
    }

    /**
     * Get the creation count by technology (lazy loaded).
     *
     * @return array<int, int|null>
     */
    private function getCreationCountByTechnology(): array
    {
        if ($this->creationCountByTechnology === null) {
            $this->creationCountByTechnology = $this->calculateCreationCountByTechnology();
        }

        return $this->creationCountByTechnology;
    }

    /**
     * Calculate projects count per technology.
     *
     * @return array<int, int|null>
     */
    private function calculateCreationCountByTechnology(): array
    {
        $creationCountByTechnology = [];

        $technologies = Technology::withCount('creations')->get();

        foreach ($technologies as $technology) {
            $creationCountByTechnology[$technology->id] = $technology->creations_count;
        }

        return $creationCountByTechnology;
    }
}
