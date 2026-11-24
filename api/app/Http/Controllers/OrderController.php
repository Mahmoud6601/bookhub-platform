<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::with(['items.book' => fn ($q) => $q->withTrashed()])
            ->where('user_id', $request->user()->id);

        if ($request->user()->is_admin) {
            $query = Order::with([
                'items.book' => fn ($q) => $q->withTrashed(),
                'user:id,name,email',
            ]);
        }

        return response()->json($query->latest()->paginate(15));
    }

    public function show(Request $request, $id)
    {
        $order = Order::with([
            'items.book' => fn ($q) => $q->withTrashed(),
            'user:id,name,email',
        ])->findOrFail($id);

        if (! $request->user()->is_admin && $order->user_id !== $request->user()->id) {
            abort(403);
        }

        return response()->json($order);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.book_id' => ['required', 'integer', Rule::exists('books', 'id')->whereNull('deleted_at')],
            'items.*.qty' => 'required|integer|min:1',
        ]);

        // Aggregate duplicate book lines and sort IDs ascending to prevent deadlocks.
        $qtyByBook = [];
        foreach ($data['items'] as $item) {
            $bookId = (int) $item['book_id'];
            $qtyByBook[$bookId] = ($qtyByBook[$bookId] ?? 0) + (int) $item['qty'];
        }
        ksort($qtyByBook);

        return DB::transaction(function () use ($request, $qtyByBook) {
            $totalCents = 0;
            $order = Order::create([
                'user_id' => $request->user()->id,
                'status' => 'pending',
                'total' => 0,
            ]);

            foreach ($qtyByBook as $bookId => $qty) {
                $book = Book::whereKey($bookId)->lockForUpdate()->first();
                if (! $book) {
                    abort(422, 'Selected book is no longer available.');
                }
                if ($book->stock < $qty) {
                    abort(422, "Not enough stock for book: {$book->title}");
                }

                $book->decrement('stock', $qty);
                $unitCents = (int) round(((float) $book->price) * 100);
                $totalCents += $unitCents * $qty;

                OrderItem::create([
                    'order_id' => $order->id,
                    'book_id' => $book->id,
                    'qty' => $qty,
                    'price' => $book->price,
                ]);
            }

            $order->update(['total' => round($totalCents / 100, 2)]);

            return response()->json($order->load('items.book'), 201);
        });
    }

    public function updateStatus(Request $request, $id)
    {
        $data = $request->validate(['status' => ['required', Rule::in(Order::STATUSES)]]);

        return DB::transaction(function () use ($id, $data) {
            $order = Order::with('items')->lockForUpdate()->findOrFail($id);

            if (! $order->canTransitionTo($data['status'])) {
                abort(422, "Cannot transition order from {$order->status} to {$data['status']}.");
            }

            if ($data['status'] === 'cancelled') {
                foreach ($order->items as $item) {
                    Book::withTrashed()
                        ->whereKey($item->book_id)
                        ->lockForUpdate()
                        ->increment('stock', $item->qty);
                }
            }

            $order->update(['status' => $data['status']]);

            return response()->json(
                $order->fresh([
                    'items.book' => fn ($q) => $q->withTrashed(),
                    'user:id,name,email',
                ])
            );
        });
    }
}
