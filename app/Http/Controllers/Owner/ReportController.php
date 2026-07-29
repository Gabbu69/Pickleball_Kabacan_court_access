<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Court;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request, ReportService $reports)
    {
        [$from, $to, $courtIds] = $this->scope($request);

        return view('owner.reports.index', [
            'from' => $from,
            'to' => $to,
            'courts' => Court::whereIn('id', $courtIds)->orderBy('name')->get(),
            ...$reports->summarize($courtIds, $from, $to),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        [$from, $to, $courtIds] = $this->scope($request);
        $rows = Booking::whereIn('court_id', $courtIds)
            ->whereBetween('starts_at', [$from, $to])
            ->with(['court', 'courtUnit', 'user'])
            ->orderBy('starts_at')
            ->get();

        return response()->streamDownload(function () use ($rows) {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['Reference', 'Court', 'Unit', 'Player', 'Start', 'End', 'Status', 'Payment', 'Price PHP']);
            foreach ($rows as $booking) {
                fputcsv($stream, array_map($this->csvCell(...), [
                    $booking->reference,
                    $booking->court->name,
                    $booking->courtUnit->name,
                    $booking->user->name,
                    $booking->starts_at->format('Y-m-d H:i'),
                    $booking->ends_at->format('Y-m-d H:i'),
                    $booking->status->value,
                    $booking->payment_status->value,
                    number_format($booking->price_centavos / 100, 2, '.', ''),
                ]));
            }
            fclose($stream);
        }, 'kabacan-pickleplay-bookings.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function scope(Request $request): array
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'court' => ['nullable', 'integer'],
        ]);
        $from = CarbonImmutable::parse($data['from'] ?? now()->startOfMonth())->startOfDay();
        $to = CarbonImmutable::parse($data['to'] ?? now()->endOfMonth())->endOfDay();

        if ($from->diffInDays($to) > 366) {
            throw ValidationException::withMessages(['to' => 'Reports are limited to a 12-month range.']);
        }

        $courtIds = $request->user()->isAdmin()
            ? Court::pluck('id')
            : $request->user()->courts()->pluck('courts.id');

        if ($data['court'] ?? null) {
            abort_unless($courtIds->contains((int) $data['court']), 403);
            $courtIds = collect([(int) $data['court']]);
        }

        return [$from, $to, $courtIds];
    }

    private function csvCell(mixed $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@\t\r]/u', $value) ? "'".$value : $value;
    }
}
