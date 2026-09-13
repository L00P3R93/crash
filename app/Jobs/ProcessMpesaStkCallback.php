<?php

namespace App\Jobs;

use App\Domain\Mpesa\MpesaCallbackHandler;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessMpesaStkCallback implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly array $payload
    ) {}

    public function handle(MpesaCallbackHandler $handler): void
    {
        $handler->handleStkCallback($this->payload);
    }
}
