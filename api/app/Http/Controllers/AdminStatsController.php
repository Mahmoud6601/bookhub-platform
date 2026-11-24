<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Borrow;
use App\Models\Order;
use App\Models\User;

class AdminStatsController extends Controller
{
    public function __invoke()
    {
        return response()->json([
            'books' => Book::count(),
            'orders' => Order::count(),
            'borrows' => Borrow::count(),
            'users' => User::count(),
            'pending_orders' => Order::where('status', 'pending')->count(),
            'active_borrows' => Borrow::whereNull('returned_at')->count(),
        ]);
    }
}
