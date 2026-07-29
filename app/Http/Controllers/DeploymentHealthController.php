<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DeploymentHealthController extends Controller
{
    public function __invoke()
    {
        try {
            DB::select('select 1');
            $migrated = Schema::hasTable('migrations')
                && Schema::hasTable('bookings')
                && Schema::hasTable('booking_slot_claims');
        } catch (\Throwable $exception) {
            report($exception);
            $migrated = false;
        }

        return response()->json([
            'status' => $migrated ? 'ok' : 'unavailable',
            'database' => $migrated ? 'ready' : 'unavailable',
            'time' => now()->toIso8601String(),
        ], $migrated ? 200 : 503)->header('Cache-Control', 'no-store');
    }
}
