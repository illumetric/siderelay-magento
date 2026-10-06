<?php

declare(strict_types=1);

namespace SideRelay\Tracking\Plugin;

use Magento\Framework\Encryption\EncryptorInterface;
use SideRelay\Tracking\Model\Purchase;

final class PaymentData
{
    public function __construct(private EncryptorInterface $encryptor)
    {
    }
    public function beforeImportData(\Magento\Quote\Model\Quote\Payment $payment, array $data): array
    {
        $raw = $data['additional_data']['siderelay_context'] ?? null;
        unset($data['additional_data']['siderelay_context']);
        try {
            if (is_string($raw) && strlen($raw) <= 8192) {
                $context = json_decode($raw, true);
                if (is_array($context)) {
                    if (($_SERVER['HTTP_SEC_GPC'] ?? '') === '1') {
                        $context['consent']['global_privacy_control'] = true;
                    }
                    $payment->setAdditionalInformation('siderelay_context', $this->encryptor->encrypt(json_encode(Purchase::permissionContext($context), JSON_THROW_ON_ERROR)));
                }
            }
        } catch (\Throwable) { /* Tracking cannot interrupt checkout. */
        }
        return [$data];
    }
}
