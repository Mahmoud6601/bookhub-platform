<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Http\Resources\BookResource;
use App\Models\Book;
use Illuminate\Http\Request;

class BookController extends Controller
{
    public function index(Request $request)
    {
        $query = Book::query()
            ->with(['category', 'authorModel'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews');

        if ($request->filled('search')) {
            $search = trim((string) $request->string('search'));
            if ($search !== '') {
                $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search);
                $pattern = "%{$escaped}%";
                $query->where(function ($q) use ($pattern) {
                    $q->whereRaw("title LIKE ? ESCAPE '!'", [$pattern])
                        ->orWhereRaw("author LIKE ? ESCAPE '!'", [$pattern])
                        ->orWhereRaw("description LIKE ? ESCAPE '!'", [$pattern]);
                });
            }
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($request->filled('author_id')) {
            $query->where('author_id', $request->integer('author_id'));
        }

        $sort = $request->input('sort', 'created_at');
        $direction = $request->input('direction', 'desc') === 'asc' ? 'asc' : 'desc';
        if (in_array($sort, ['title', 'price', 'created_at'], true)) {
            $query->orderBy($sort, $direction);
        }

        $books = $query->paginate(15)->withQueryString();
        $books->through(fn (Book $book) => (new BookResource($book))->resolve());

        return response()->json($books);
    }

    public function show($id)
    {
        $book = Book::with([
            'category',
            'authorModel',
            'reviews' => fn ($q) => $q->latest()->with('user:id,name'),
        ])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->findOrFail($id);

        return response()->json(new BookResource($book));
    }

    public function store(StoreBookRequest $request)
    {
        $data = $request->validated();
        $data['price'] = $data['price'] ?? 0;
        $data['stock'] = $data['stock'] ?? 0;

        $book = Book::create($data)->load(['category', 'authorModel']);

        return response()->json($book, 201);
    }

    public function update(UpdateBookRequest $request, $id)
    {
        $book = Book::findOrFail($id);
        $data = $request->validated();

        if (array_key_exists('price', $data) && $data['price'] === null) {
            $data['price'] = 0;
        }
        if (array_key_exists('stock', $data) && $data['stock'] === null) {
            $data['stock'] = 0;
        }

        $book->update($data);

        return response()->json($book->load(['category', 'authorModel']), 200);
    }

    public function destroy($id)
    {
        $book = Book::findOrFail($id);
        $book->delete();

        return response()->json(null, 204);
    }
}
