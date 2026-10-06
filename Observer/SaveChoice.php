<?php

declare(strict_types=1);

namespace SideRelay\Tracking\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use SideRelay\Tracking\Model\Purchase;

final class SaveChoice implements ObserverInterface
{
    public function __construct(private EncryptorInterface $encryptor)
    {
    }
    public function execute(Observer $observer): void
    {
        try {
            $order = $observer->getEvent()->getOrder();
            $raw = $order->getPayment()->getAdditionalInformation('siderelay_context');
            $order->getPayment()->unsAdditionalInformation('siderelay_context');
            $input = is_string($raw) && strlen($raw) <= 16384 ? json_decode($this->encryptor->decrypt($raw), true) : [];
            if (!is_array($input)) {
                $input = [];
            }
            if (($_SERVER['HTTP_SEC_GPC'] ?? '') === '1') {
                $input['consent']['global_privacy_control'] = true;
            }
            $order->setData('siderelay_context', $this->encryptor->encrypt(json_encode(Purchase::permissionContext($input), JSON_THROW_ON_ERROR)));
        } catch (\Throwable) { /* Missing permission never becomes consent; tracking cannot fail an order. */
        }
    }
}
