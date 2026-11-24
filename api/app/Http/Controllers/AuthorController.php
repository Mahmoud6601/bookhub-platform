<?php

namespace App\Http\Controllers;

use App\Models\Author;
use Illuminate\Http\Request;

class AuthorController extends Controller
{
    public function index()
    {
        return response()->json(Author::withCount('books')->paginate(15));
    }

    public function show($id)
    {
        return response()->json(Author::with('books')->findOrFail($id));
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:255', 'bio' => 'nullable|string']);
        return response()->json(Author::create($data), 201);
    }

    public function update(Request $request, $id)
    {
        $author = Author::findOrFail($id);
        $data = $request->validate(['name' => 'sometimes|required|string|max:255', 'bio' => 'nullable|string']);
        $author->update($data);
        return response()->json($author);
    }

    public function destroy($id)
    {
        Author::findOrFail($id)->delete();
        return response()->json(null, 204);
    }
}
