<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Borrow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BorrowController extends Controller
{
    public function index(Request $request)
    {
        $query = Borrow::with([
            'book' => fn ($q) => $q->withTrashed(),
            'user:id,name,email',
        ])->where('user_id', $request->user()->id);

        if ($request->user()->is_admin) {
            $query = Borrow::with([
                'book' => fn ($q) => $q->withTrashed(),
                'user:id,name,email',
            ]);
        }

        return response()->json($query->latest()->paginate(15));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'book_id' => ['required', 'integer', Rule::exists('books', 'id')->whereNull('deleted_at')],
        ]);

        return DB::transaction(function () use ($request, $data) {
            $book = Book::whereKey($data['book_id'])->lockForUpdate()->first();
            if (! $book) {
                abort(404, 'Book not found.');
            }

            $alreadyBorrowed = Borrow::where('user_id', $request->user()->id)
                ->where('book_id', $book->id)
                ->whereNull('returned_at')
                ->exists();

            if ($alreadyBorrowed) {
                abort(422, 'You already have an active borrow for this book.');
            }

            if ($book->stock < 1) {
                abort(422, 'Book is out of stock.');
            }

            $book->decrement('stock');

            $now = now();
            $borrow = Borrow::create([
                'user_id' => $request->user()->id,
                'book_id' => $book->id,
                'borrowed_at' => $now,
                'due_at' => $now->copy()->addDays(14),
                'fine' => 0,
            ]);

            return response()->json($borrow->load('book'), 201);
        });
    }

    public function returnBook(Request $request, $id)
    {
        return DB::transaction(function () use ($request, $id) {
            $borrow = Borrow::lockForUpdate()->findOrFail($id);

            if (! $request->user()->is_admin && $borrow->user_id !== $request->user()->id) {
                abort(403);
            }

            if ($borrow->returned_at) {
                abort(422, 'Book already returned.');
            }

            $now = now();
            $fine = 0;
            if ($now->greaterThan($borrow->due_at)) {
                $overdueDays = max(1, (int) $borrow->due_at->diffInDays($now));
                $fine = $overdueDays * 1.00; // $1 per overdue day
            }

            $borrow->update(['returned_at' => $now, 'fine' => $fine]);

            Book::withTrashed()
                ->whereKey($borrow->book_id)
                ->lockForUpdate()
                ->increment('stock');

            return response()->json(
                $borrow->fresh([
                    'book' => fn ($q) => $q->withTrashed(),
                    'user:id,name,email',
                ])
            );
        });
    }
}
