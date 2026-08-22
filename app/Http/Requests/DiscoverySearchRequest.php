<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class DiscoverySearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $filterIdRule = static function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value === null || $value === '' || $value === 'all') {
                return;
            }

            if (! filter_var($value, FILTER_VALIDATE_INT) || (int) $value < 1) {
                $fail("The {$attribute} field must be a valid id.");
            }
        };

        return [
            'type' => ['nullable', 'in:all,event,image,video'],
            'seed' => ['nullable', 'integer', 'min:1', 'max:2147483646'],
            'q' => ['nullable', 'string', 'max:255'],
            'search' => ['nullable', 'string', 'max:255'],
            'searchQuery' => ['nullable', 'string', 'max:255'],
            'country_id' => ['nullable', $filterIdRule],
            'countryId' => ['nullable', $filterIdRule],
            'city_id' => ['nullable', $filterIdRule],
            'cityId' => ['nullable', $filterIdRule],
            'category_id' => ['nullable', $filterIdRule],
            'categoryId' => ['nullable', $filterIdRule],
            'sub_category_id' => ['nullable', $filterIdRule],
            'subCategoryId' => ['nullable', $filterIdRule],
            'tags' => ['nullable'],
            'tags_id' => ['nullable'],
            'tags_id.*' => ['integer', 'min:1'],
            'tagsIds' => ['nullable'],
            'tagsIds.*' => ['integer', 'min:1'],
            'from' => ['nullable', 'date'],
            'from_date' => ['nullable', 'date'],
            'fromDate' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date'],
            'toDate' => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $filters = $this->filters();

            if (
                $filters['from']
                && $filters['to']
                && strtotime((string) $filters['to']) < strtotime((string) $filters['from'])
            ) {
                $validator->errors()->add('to', 'The to date must be after or equal to the from date.');
            }
        });
    }

    public function filters(): array
    {
        $normalizeId = static function (mixed $value): ?int {
            if ($value === null || $value === '' || $value === 'all') {
                return null;
            }

            $id = (int) $value;

            return $id > 0 ? $id : null;
        };

        $tags = $this->query(
            'tags_id',
            $this->query('tagsIds', $this->query('tags', []))
        );

        if (! is_array($tags)) {
            $tags = explode(',', (string) $tags);
        }

        $tags = collect($tags)
            ->flatMap(fn ($id) => is_string($id) ? explode(',', $id) : [$id])
            ->filter(fn ($id) => $id !== null && $id !== '' && $id !== 'all')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        return [
            'country_id' => $normalizeId($this->query('country_id', $this->query('countryId'))),
            'city_id' => $normalizeId($this->query('city_id', $this->query('cityId'))),
            'category_id' => $normalizeId($this->query('category_id', $this->query('categoryId'))),
            'sub_category_id' => $normalizeId($this->query('sub_category_id', $this->query('subCategoryId'))),
            'tags_id' => $tags,
            'from' => $this->query('from', $this->query('from_date', $this->query('fromDate'))),
            'to' => $this->query('to', $this->query('to_date', $this->query('toDate'))),
            'q' => trim((string) $this->query('q', $this->query('searchQuery', $this->query('search', '')))),
        ];
    }

    public function resultType(): string
    {
        return (string) $this->query('type', 'all');
    }

    public function seed(): int
    {
        return (int) $this->query('seed', random_int(1, 2147483646));
    }

    public function pageNumber(): int
    {
        return max(1, (int) $this->query('page', 1));
    }

    public function perPage(): int
    {
        return max(1, min((int) $this->query('per_page', $this->query('perPage', 20)), 100));
    }
}
