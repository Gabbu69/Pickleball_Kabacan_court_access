<?php

namespace App\Http\Controllers;

use App\Services\MaintenanceService;
use Illuminate\Http\Request;

class MaintenanceController extends Controller
{
    public function __invoke(Request $request, MaintenanceService $maintenance)
    {
        $secret = (string) config('services.cron.secret');
        abort_if($secret === '', 503, 'Cron maintenance is not configured.');
        abort_unless(hash_equals('Bearer '.$secret, (string) $request->header('Authorization')), 401);

        return response()->json([
            'ok' => true,
            'processed' => $maintenance->run(),
            'ran_at' => now()->toIso8601String(),
        ]);
    }
}
