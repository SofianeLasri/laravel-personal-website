<?php

namespace Tests\Feature\Models\Creation;

use App\Http\Requests\Feature\CreateCreationDraftFeatureRequest;
use App\Models\CreationDraft;
use App\Models\Picture;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(CreateCreationDraftFeatureRequest::class)]
class CreateCreationDraftFeatureRequestTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected CreationDraft $draft;

    protected array $baseData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->draft = CreationDraft::factory()->create();
        $this->baseData = [
            'locale' => 'en',
            'title' => 'Feature Title',
            'description' => 'Feature Description',
            'picture_id' => Picture::factory()->create()->id,
        ];
    }

    #[Test]
    public function validation_passes_with_valid_data()
    {
        $response = $this->postJson(
            route('dashboard.api.creation-drafts.draft-features.store', $this->draft),
            $this->baseData
        );

        $response->assertCreated();
    }

    #[Test]
    public function locale_field_validation()
    {
        $scenarios = [
            'required' => [
                'data' => Arr::except($this->baseData, 'locale'),
                'errors' => ['locale'],
            ],
            'in_enum' => [
                'data' => array_merge($this->baseData, ['locale' => 'es']),
                'errors' => ['locale'],
            ],
            'string_type' => [
                'data' => array_merge($this->baseData, ['locale' => 123]),
                'errors' => ['locale'],
            ],
        ];

        $this->runValidationScenarios($scenarios);
    }

    #[Test]
    public function title_field_validation()
    {
        $scenarios = [
            'required' => [
                'data' => Arr::except($this->baseData, 'title'),
                'errors' => ['title'],
            ],
            'string_type' => [
                'data' => array_merge($this->baseData, ['title' => 12345]),
                'errors' => ['title'],
            ],
        ];

        $this->runValidationScenarios($scenarios);
    }

    #[Test]
    public function description_field_validation()
    {
        $scenarios = [
            'required' => [
                'data' => Arr::except($this->baseData, 'description'),
                'errors' => ['description'],
            ],
            'string_type' => [
                'data' => array_merge($this->baseData, ['description' => 12345]),
                'errors' => ['description'],
            ],
        ];

        $this->runValidationScenarios($scenarios);
    }

    #[Test]
    public function picture_id_field_validation()
    {
        $scenarios = [
            'exists_in_database' => [
                'data' => array_merge($this->baseData, ['picture_id' => 999]),
                'errors' => ['picture_id'],
            ],
            'optional' => [
                'data' => Arr::except($this->baseData, 'picture_id'),
                'errors' => [],
            ],
            'integer_type' => [
                'data' => array_merge($this->baseData, ['picture_id' => 'invalid']),
                'errors' => ['picture_id'],
            ],
        ];

        $this->runValidationScenarios($scenarios);
    }

    protected function runValidationScenarios(array $scenarios): void
    {
        foreach ($scenarios as $description => $scenario) {
            $response = $this->postJson(
                route('dashboard.api.creation-drafts.draft-features.store', $this->draft),
                $scenario['data']
            );

            if (! empty($scenario['errors'])) {
                $response->assertUnprocessable();
                foreach ($scenario['errors'] as $errorField) {
                    $response->assertJsonValidationErrors($errorField);
                }
            } else {
                $response->assertCreated();
            }
        }
    }

    #[Test]
    public function request_uses_correct_validation_rules()
    {
        $request = new CreateCreationDraftFeatureRequest;
        $rules = $request->rules();

        $this->assertEquals([
            'locale' => ['required', 'string', 'in:en,fr'],
            'title' => ['required', 'string'],
            'description' => ['required', 'string'],
            'picture_id' => ['sometimes', 'exists:pictures,id'],
        ], $rules);
    }
}
