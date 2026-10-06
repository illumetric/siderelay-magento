<?php

declare(strict_types=1);

namespace SideRelay\Tracking\Block;

use Magento\Framework\View\Element\Template;
use Magento\Checkout\Model\Session;

final class Purchase extends Template
{
    public function __construct(Template\Context $context, private Session $checkout, array $data = [])
    {
        parent::__construct($context, $data);
    }
    public function event(): ?array
    {
        $order = $this->checkout->getLastRealOrder();
        if (!$order->getId() || (float) $order->getGrandTotal() <= 0 || (float) $order->getTotalPaid() + 0.001 < (float) $order->getGrandTotal()) {
            return null;
        }
        if (!$this->_scopeConfig->isSetFlag('siderelay/connection/enabled', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $order->getStoreId())) {
            return null;
        }
        $site = (string) $this->_scopeConfig->getValue('web/unsecure/base_url', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $order->getStoreId());
        $event = \SideRelay\Tracking\Model\Purchase::event($order, [], $order->getCreatedAt(), $site);
        unset($event['consent'], $event['identity']);
        $event['event_name'] = 'purchase';
        return $event;
    }
}
