<?php

namespace App\Http\Controllers;

use App\Http\Resources\ReviewResource;
use App\Models\Book;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index($bookId)
    {
        Book::findOrFail($bookId);

        $reviews = Review::with('user:id,name')
            ->where('book_id', $bookId)
            ->latest()
            ->paginate(15);

        $reviews->through(fn (Review $review) => (new ReviewResource($review))->resolve());

        return response()->json($reviews);
    }

    public function store(Request $request, $bookId)
    {
        $book = Book::findOrFail($bookId);

        $data = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:2000',
        ]);

        $review = Review::updateOrCreate(
            ['user_id' => $request->user()->id, 'book_id' => $book->id],
            ['rating' => $data['rating'], 'comment' => $data['comment'] ?? null]
        );

        $status = $review->wasRecentlyCreated ? 201 : 200;

        return response()->json(new ReviewResource($review->load('user:id,name')), $status);
    }

    public function destroy(Request $request, $id)
    {
        $review = Review::findOrFail($id);
        if (! $request->user()->is_admin && $review->user_id !== $request->user()->id) {
            abort(403);
        }

        $review->delete();

        return response()->json(null, 204);
    }
}
