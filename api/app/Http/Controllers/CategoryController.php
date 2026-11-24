<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index()
    {
        return response()->json(Category::withCount('books')->paginate(15));
    }

    public function show($id)
    {
        return response()->json(Category::with('books')->findOrFail($id));
    }

    public function store(Request $request)
    {
        $slug = $request->filled('slug')
            ? Str::slug((string) $request->input('slug'))
            : Str::slug((string) $request->input('name'));
        $request->merge(['slug' => $slug]);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:categories,slug',
        ]);

        return response()->json(Category::create($data), 201);
    }

    public function update(Request $request, $id)
    {
        $category = Category::findOrFail($id);

        if ($request->has('slug')) {
            $rawSlug = $request->input('slug');
            $slug = $rawSlug
                ? Str::slug((string) $rawSlug)
                : Str::slug((string) $request->input('name', $category->name));
            $request->merge(['slug' => $slug]);
        }

        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'slug' => 'sometimes|required|string|max:255|unique:categories,slug,' . $category->id,
        ]);

        $category->update($data);

        return response()->json($category);
    }

    public function destroy($id)
    {
        Category::findOrFail($id)->delete();

        return response()->json(null, 204);
    }
}
