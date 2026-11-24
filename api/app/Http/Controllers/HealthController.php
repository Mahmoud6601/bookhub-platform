<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    public function __invoke()
    {
        try {
            DB::select('SELECT 1');

            return response()->json(['status' => 'ok', 'database' => 'ok']);
        } catch (Throwable $e) {
            return response()->json(['status' => 'error', 'database' => 'unreachable'], 503);
        }
    }
}
