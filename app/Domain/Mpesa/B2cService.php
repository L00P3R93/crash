<?php

namespace App\Domain\Mpesa;

use App\Domain\Aviator\PlayerLimitsGuard;
use App\Domain\Wallet\WalletService;
use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Models\Wallet;
use App\Models\WalletTransaction;

/**
 * Withdrawals via M-Pesa B2C. There's no dedicated `withdrawals` table — the
 * `wallet_transactions` ledger itself is the source of truth: the debit is
 * written as `pending` the instant a withdrawal is requested (so the balance
 * reflects it immediately), then MpesaCallbackHandler::handleB2cResult()
 * completes or reverses it once Daraja's async result arrives.
 */
class B2cService
{
    public function __construct(
        private readonly DarajaClient $client = new DarajaClient,
        private readonly WalletService $wallet = new WalletService,
        private readonly PlayerLimitsGuard $limits = new PlayerLimitsGuard,
    ) {}

    public function initiate(Wallet $wallet, float $amount, string $msisdn, string $reference, string $remarks = 'Aviator withdrawal'): WalletTransaction
    {
        $this->limits->assertCanWithdraw($wallet->player);

        $transaction = $this->wallet->debit(
            wallet: $wallet,
            amount: $amount,
            type: WalletTransactionType::Withdrawal,
            reference: $reference,
            metadata: ['msisdn' => $msisdn],
            status: WalletTransactionStatus::Pending,
        );

        $response = $this->client->post('/mpesa/b2c/v3/paymentrequest', [
            'OriginatorConversationID' => $reference,
            'InitiatorName' => config('mpesa.initiator_name'),
            'SecurityCredential' => $this->securityCredential(),
            'CommandID' => 'BusinessPayment',
            'Amount' => (int) round($amount),
            'PartyA' => config('mpesa.shortcode'),
            'PartyB' => $msisdn,
            'Remarks' => $remarks,
            'QueueTimeOutURL' => config('mpesa.b2c_timeout_url'),
            'ResultURL' => config('mpesa.b2c_result_url'),
            'Occasion' => 'AviatorWithdrawal',
        ]);

        $transaction->forceFill([
            'metadata' => array_merge($transaction->metadata ?? [], [
                'conversation_id' => $response['ConversationID'] ?? null,
            ]),
        ])->save();

        return $transaction;
    }

    /**
     * Safaricom requires the initiator password encrypted with their public
     * certificate (base64 RSA-encrypted). Plugging in the real certificate
     * and encryption is a pre-launch task — this passes the raw value
     * through so the rest of the flow is testable without it.
     */
    private function securityCredential(): string
    {
        return (string) config('mpesa.initiator_password');
    }
}
