<?php

namespace App\Http\Controllers\Api;

use App\Domain\Aviator\BetService;
use App\Domain\Aviator\CashoutService;
use App\Enums\BetStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PlaceBetRequest;
use App\Http\Resources\AviatorBetResource;
use App\Models\AviatorBet;
use App\Models\Player;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The web leg of the same `BetService`/`CashoutService` USSD already calls
 * (architecture doc §15's "one game engine, two interfaces") — no betting
 * or payout math is re-implemented here.
 */
class AviatorBetController extends Controller
{
    public function __construct(
        private readonly BetService $bets,
        private readonly CashoutService $cashouts,
    ) {}

    public function store(PlaceBetRequest $request): JsonResponse
    {
        /** @var Player $player */
        $player = $request->user();

        $data = $request->validated();

        // Idempotency-Key (architecture doc §19): a retried request with the
        // same key and player returns the original bet rather than charging
        // twice. Without one, fall back to a fresh reference per request.
        $idempotencyKey = $request->header('Idempotency-Key');
        $betReference = $idempotencyKey
            ? 'AVIATOR-BET-WEB-'.hash('sha256', "{$player->id}:{$idempotencyKey}")
            : 'AVIATOR-BET-WEB-'.Str::upper(Str::random(16));

        $bet = $this->bets->placeBet(
            player: $player,
            stake: (float) $data['stake'],
            channel: 'web',
            betReference: $betReference,
            autoCashout: isset($data['auto_cashout']) ? (float) $data['auto_cashout'] : null,
        );

        return response()->json(['data' => new AviatorBetResource($bet)], 201);
    }

    /**
     * The player's own last ~15 resolved bets — the round-history strip. Only
     * ever the authenticated player's bets, never another player's, so this
     * is plain ownership scoping rather than anything privacy-sensitive.
     */
    public function recent(Request $request): JsonResponse
    {
        $bets = AviatorBet::query()
            ->where('player_id', $request->user()->id)
            ->whereIn('status', [BetStatus::Won, BetStatus::Lost])
            ->with('round')
            ->latest('placed_at')
            ->limit(15)
            ->get();

        return response()->json(['data' => AviatorBetResource::collection($bets)]);
    }

    /**
     * The player's own still-active bet, if any — lets the play screen
     * restore the "Cash out" panel after a mid-round page reload, since
     * `GET aviator/round` is unauthenticated and never carries bet state.
     */
    public function active(Request $request): JsonResponse
    {
        $bet = AviatorBet::query()
            ->where('player_id', $request->user()->id)
            ->where('status', BetStatus::Active)
            ->with('round')
            ->latest('placed_at')
            ->first();

        return response()->json(['data' => $bet ? new AviatorBetResource($bet) : null]);
    }

    public function show(Request $request, AviatorBet $bet): JsonResponse
    {
        $this->authorizeOwnership($request, $bet);

        return response()->json(['data' => new AviatorBetResource($bet->loadMissing('round'))]);
    }

    public function cashOut(Request $request, AviatorBet $bet): JsonResponse
    {
        $this->authorizeOwnership($request, $bet);

        $bet = $this->cashouts->cashOut($bet->id);

        return response()->json(['data' => new AviatorBetResource($bet)]);
    }

    private function authorizeOwnership(Request $request, AviatorBet $bet): void
    {
        if ($bet->player_id !== $request->user()->id) {
            throw new HttpException(404);
        }
    }
}
