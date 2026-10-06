<?php

declare(strict_types=1);

namespace SideRelay\Tracking\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\ResourceConnection;

final class QueuePurchase implements ObserverInterface
{
    public function __construct(private ResourceConnection $resource, private \Magento\Framework\App\Config\ScopeConfigInterface $config)
    {
    }
    public function execute(Observer $observer): void
    {
        try {
            $order = $observer->getEvent()->getInvoice()->getOrder();
            if (!$this->config->isSetFlag('siderelay/connection/enabled', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $order->getStoreId())) {
                return;
            }
            $db = $this->resource->getConnection();
            $db->insertOnDuplicate($this->resource->getTableName('siderelay_tracking_outbox'), ['order_id' => (int) $order->getId()], ['order_id']);
        } catch (\Throwable) { /* The paid order remains authoritative; operators can run the reconciliation command. */
        }
    }
}
