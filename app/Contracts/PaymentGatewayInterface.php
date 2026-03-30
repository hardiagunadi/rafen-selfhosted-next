<?php

namespace App\Contracts;

use App\Models\Invoice;
use App\Models\Subscription;

interface PaymentGatewayInterface
{
    public function createInvoicePayment(Invoice $invoice, string $channel, int $expiryHours = 24): array;

    public function createSubscriptionPayment(Subscription $subscription, string $channel, int $expiryHours = 24): array;

    public function getTransactionDetail(string $reference): array;

    public function verifyCallback(array $data): bool;

    public function getPaymentChannels(): array;
}
