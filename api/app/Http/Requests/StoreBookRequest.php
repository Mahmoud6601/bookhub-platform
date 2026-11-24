<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'author' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'isbn' => ['nullable', 'string', 'max:20', 'unique:books,isbn'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'cover_url' => ['nullable', 'url', 'max:2048'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'author_id' => ['nullable', 'exists:authors,id'],
        ];
    }
}
