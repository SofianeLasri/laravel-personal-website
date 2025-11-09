<?php

namespace App\Http\Requests\Screenshot;

use App\Models\CreationDraft;
use App\Models\CreationDraftScreenshot;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReorderCreationDraftScreenshotsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'screenshots' => ['required', 'array'],
            'screenshots.*.id' => ['required', 'integer', 'exists:creation_draft_screenshots,id'],
            'screenshots.*.order' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $creationDraft = $this->route('creation_draft');
            if (! $creationDraft instanceof CreationDraft) {
                return;
            }

            $screenshots = $this->input('screenshots', []);
            $screenshotIds = array_column($screenshots, 'id');
            $orders = array_column($screenshots, 'order');

            // Check all screenshots belong to the creation draft
            $belongingCount = CreationDraftScreenshot::whereIn('id', $screenshotIds)
                ->where('creation_draft_id', $creationDraft->id)
                ->count();

            if ($belongingCount !== count($screenshotIds)) {
                $validator->errors()->add('screenshots', 'All screenshots must belong to the creation draft.');

                return;
            }

            // Check that we have all the screenshots for this draft
            $totalCount = CreationDraftScreenshot::where('creation_draft_id', $creationDraft->id)->count();

            if (count($screenshotIds) !== $totalCount) {
                $validator->errors()->add('screenshots', 'All screenshots must be included in the reorder request.');

                return;
            }

            // Check orders form a continuous sequence (1, 2, 3, ...)
            sort($orders);
            $expectedSequence = range(1, count($orders));

            if ($orders !== $expectedSequence) {
                $validator->errors()->add('screenshots', 'Order values must form a continuous sequence starting from 1.');

                return;
            }

            // Check for duplicate IDs
            if (count($screenshotIds) !== count(array_unique($screenshotIds))) {
                $validator->errors()->add('screenshots', 'Duplicate screenshot IDs are not allowed.');

                return;
            }

            // Check for duplicate orders
            if (count($orders) !== count(array_unique($orders))) {
                $validator->errors()->add('screenshots', 'Duplicate order values are not allowed.');
            }
        });
    }
}
