<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'author' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'isbn' => ['nullable', 'string', 'max:20', Rule::unique('books', 'isbn')->ignore($this->route('id'))],
            'price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'cover_url' => ['nullable', 'url', 'max:2048'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'author_id' => ['nullable', 'exists:authors,id'],
        ];
    }
}
