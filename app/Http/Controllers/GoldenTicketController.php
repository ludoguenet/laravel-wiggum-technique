<?php

namespace App\Http\Controllers;

use App\Models\Tour;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GoldenTicketController extends Controller
{
    /**
     * Show the golden ticket counter.
     */
    public function show(): JsonResponse
    {
        return $this->tourState($this->wonkaTour());
    }

    /**
     * Claim a ticket using the (stale) model loaded at the start of the request.
     */
    public function claimNaively(Request $request): JsonResponse
    {
        $tour = $this->wonkaTour();

        $this->letVerucaClaimFirst($request, $tour);

        $claimed = DB::transaction(function () use ($tour) {
            if (! $tour->hasTicketsLeft()) {
                return false;
            }

            $tour->decrement('tickets_left');
            $tour->increment('tickets_sold');

            return true;
        });

        return $this->claimOutcome($claimed, $tour);
    }

    /**
     * Claim a ticket after reloading the model with a FOR UPDATE lock.
     */
    public function claimSafely(Request $request): JsonResponse
    {
        $tour = $this->wonkaTour();

        $this->letVerucaClaimFirst($request, $tour);

        $claimed = DB::transaction(function () use ($tour) {
            $tour->refreshForUpdate();

            if (! $tour->hasTicketsLeft()) {
                return false;
            }

            $tour->decrement('tickets_left');
            $tour->increment('tickets_sold');

            return true;
        });

        return $this->claimOutcome($claimed, $tour);
    }

    /**
     * Put the last golden ticket back on the shelf.
     */
    public function reset(): JsonResponse
    {
        $tour = $this->wonkaTour();

        $tour->update(['tickets_left' => 1, 'tickets_sold' => 0]);

        return $this->tourState($tour);
    }

    /**
     * Load the Wonka factory tour, creating it with its last ticket if needed.
     */
    private function wonkaTour(): Tour
    {
        return Tour::query()->firstOrCreate(
            ['name' => 'Visite de la chocolaterie Wonka'],
            ['tickets_left' => 1],
        );
    }

    /**
     * Simulate a concurrent request that buys a ticket right after our model was loaded.
     */
    private function letVerucaClaimFirst(Request $request, Tour $tour): void
    {
        if (! $request->boolean('veruca', true)) {
            return;
        }

        Tour::query()->whereKey($tour->getKey())->update([
            'tickets_left' => DB::raw('tickets_left - 1'),
            'tickets_sold' => DB::raw('tickets_sold + 1'),
        ]);
    }

    /**
     * Respond with the claim outcome and the tour's real state in the database.
     */
    private function claimOutcome(bool $claimed, Tour $tour): JsonResponse
    {
        return $this->tourState($tour, [
            'message' => $claimed
                ? 'Charlie a son Golden Ticket ! 🎫'
                : 'Trop tard, Veruca a eu le dernier ticket. 🍫',
        ], $claimed ? 200 : 409);
    }

    /**
     * Respond with the tour's ticket counters, read fresh from the database.
     *
     * @param  array<string, string>  $extra
     */
    private function tourState(Tour $tour, array $extra = [], int $status = 200): JsonResponse
    {
        $tour = $tour->fresh();

        return response()->json([
            ...$extra,
            'tour' => $tour->name,
            'tickets_left' => $tour->tickets_left,
            'tickets_sold' => $tour->tickets_sold,
        ], $status, options: JSON_UNESCAPED_UNICODE);
    }
}
