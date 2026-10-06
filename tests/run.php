<?php

declare(strict_types=1);
require __DIR__ . '/../vendor/siderelay/php/src/Client.php';
require __DIR__ . '/../Model/Purchase.php';
use SideRelay\Tracking\Model\Purchase;

function check(bool $condition, string $reason): void
{
    if (!$condition) {
        throw new RuntimeException($reason);
    }
}
check(Purchase::permissionContext([])['consent']['advertising'] === 'unknown', 'Missing choice stays unknown');
$context = Purchase::permissionContext(['consent' => ['advertising' => 'granted','sale_sharing_opt_out' => true],'identity' => ['gclid' => 'click','email' => 'buyer@example.com']]);
check($context['consent']['advertising'] === 'denied' && $context['identity'] === [], 'Opt-outs remove matching before storage');
$order = new class () {
    public function getCustomerEmail(): string
    {
        return 'Buyer@example.com';
    }
    public function getId(): int
    {
        return 123;
    }
    public function getIncrementId(): string
    {
        return '00000123';
    }
    public function getGrandTotal(): float
    {
        return 49.90;
    }
    public function getOrderCurrencyCode(): string
    {
        return 'EUR';
    }
};
$event = Purchase::event($order, ['consent' => ['advertising' => 'granted']], '2026-10-06 12:00:00', 'https://example.com');
check($event === Purchase::event($order, ['consent' => ['advertising' => 'granted']], '2026-10-06 12:00:00', 'https://example.com'), 'Stable retries');
check($event['event_id'] !== Purchase::event($order, [], '2026-10-06 12:00:00', 'https://other.example.com')['event_id'], 'Store namespace');
check($event['properties']['transaction_id'] === '00000123' && $event['properties']['value'] === 49.90, 'Purchase mapping');
check($event['identity']['email_sha256'] === hash('sha256', 'buyer@example.com'), 'Permitted matching hash');
check(Purchase::event($order, [], '2026-10-06 12:00:00', 'https://example.com')['identity'] === [], 'Unknown choice sends no matching');
echo "Magento adapter checks passed\n";
