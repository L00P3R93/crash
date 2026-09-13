<?php

namespace App\Domain\Mpesa;

use App\Domain\Wallet\WalletService;
use App\Enums\TopupStatus;
use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Models\Topup;
use App\Models\WalletTransaction;

class MpesaCallbackHandler
{
    public function __construct(
        private readonly WalletService $wallet = new WalletService
    ) {}

    /**
     * Idempotent on the topup's current status: a duplicate callback for an
     * already-completed or already-failed top-up is a silent no-op — Daraja
     * can and does retry callbacks (architecture doc §19).
     *
     * @param  array<string, mixed>  $payload
     */
    public function handleStkCallback(array $payload): void
    {
        $callback = $payload['Body']['stkCallback'] ?? null;

        if (! is_array($callback)) {
            return;
        }

        $checkoutRequestId = $callback['CheckoutRequestID'] ?? null;

        $topup = Topup::query()->where('provider_reference', $checkoutRequestId)->first();

        if (! $topup || $topup->status !== TopupStatus::Pending) {
            return;
        }

        if ((int) ($callback['ResultCode'] ?? 1) !== 0) {
            $topup->forceFill(['status' => TopupStatus::Failed, 'completed_at' => now()])->save();

            return;
        }

        $metadata = $this->flattenCallbackMetadata($callback['CallbackMetadata']['Item'] ?? []);

        $transaction = $this->wallet->credit(
            wallet: $topup->wallet,
            amount: (float) $topup->amount,
            type: WalletTransactionType::Topup,
            reference: "TOPUP-{$topup->id}",
            related: $topup,
            metadata: $metadata,
        );

        $topup->forceFill([
            'status' => TopupStatus::Completed,
            'completed_at' => now(),
            'wallet_transaction_id' => $transaction->id,
        ])->save();
    }

    /**
     * Reconciles a pending withdrawal ledger entry (written by B2cService)
     * against the async B2C result: completes it on success, or reverses the
     * debit with a `refund` entry on failure so the player is never left
     * short by money that never actually left.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handleB2cResult(array $payload): void
    {
        $result = $payload['Result'] ?? null;

        if (! is_array($result)) {
            return;
        }

        $conversationId = $result['ConversationID'] ?? null;

        $transaction = WalletTransaction::query()
            ->where('type', WalletTransactionType::Withdrawal)
            ->where('status', WalletTransactionStatus::Pending)
            ->where('metadata->conversation_id', $conversationId)
            ->first();

        if (! $transaction) {
            return;
        }

        if ((int) ($result['ResultCode'] ?? 1) === 0) {
            $transaction->forceFill(['status' => WalletTransactionStatus::Completed])->save();

            return;
        }

        $transaction->forceFill(['status' => WalletTransactionStatus::Failed])->save();

        $this->wallet->credit(
            wallet: $transaction->wallet,
            amount: abs((float) $transaction->amount),
            type: WalletTransactionType::Refund,
            reference: "{$transaction->reference}-REFUND",
            metadata: [
                'reversed_transaction_id' => $transaction->id,
                'reason' => $result['ResultDesc'] ?? null,
            ],
        );
    }

    /**
     * @param  array<int, array{Name?: string, Value?: mixed}>  $items
     * @return array<string, mixed>
     */
    private function flattenCallbackMetadata(array $items): array
    {
        $flattened = [];

        foreach ($items as $item) {
            if (isset($item['Name'])) {
                $flattened[$item['Name']] = $item['Value'] ?? null;
            }
        }

        return $flattened;
    }
}
