<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateImageTagsRequest extends FormRequest
{
    /**
     * Determine whether the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data before validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => is_string($this->title)
                ? trim($this->title)
                : $this->title,

            'description' => is_string($this->description)
                ? trim($this->description)
                : $this->description,

            'language' => strtolower(
                trim((string) ($this->language ?: 'ar'))
            ),
        ]);
    }

    /**
     * Get the validation rules.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => [
                'required',
                'string',
                'max:255',
            ],

            'description' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'images' => [
                'required',
                'array',
                'min:1',
                'max:'.max(1, (int) config('ai_tags.images_limit', 5)),
            ],

            'images.*' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],

            'language' => [
                'nullable',
                'string',
                'in:ar,en,fr,ru,zh',
            ],
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'عنوان المحتوى مطلوب.',
            'title.string' => 'عنوان المحتوى يجب أن يكون نصًا.',
            'title.max' => 'عنوان المحتوى يجب ألا يتجاوز 255 حرفًا.',

            'description.string' => 'وصف المحتوى يجب أن يكون نصًا.',
            'description.max' => 'وصف المحتوى يجب ألا يتجاوز 5000 حرف.',

            'images.required' => 'يجب رفع صورة واحدة على الأقل.',
            'images.array' => 'يجب إرسال الصور في صورة قائمة.',
            'images.min' => 'يجب رفع صورة واحدة على الأقل.',
            'images.max' => 'لا يمكن رفع أكثر من 5 صور في الطلب الواحد.',

            'images.*.required' => 'إحدى الصور المرسلة غير موجودة.',
            'images.*.file' => 'يجب أن يكون كل عنصر ملفًا صالحًا.',
            'images.*.image' => 'يجب أن تكون الملفات المرفوعة صورًا.',
            'images.*.mimes' => 'الصيغ المدعومة هي JPG وJPEG وPNG وWEBP فقط.',
            'images.*.max' => 'حجم كل صورة يجب ألا يتجاوز 5 ميجابايت.',

            'language.in' => 'اللغة المختارة غير مدعومة.',
        ];
    }

    /**
     * Get custom names for validated attributes.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'العنوان',
            'description' => 'الوصف',
            'images' => 'الصور',
            'images.*' => 'الصورة',
            'language' => 'اللغة',
        ];
    }
}
