<?php

declare(strict_types=1);

namespace SideRelay\Tracking\Cron;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use SideRelay\Client;
use SideRelay\Tracking\Model\Purchase;

final class SendPurchases
{
    public function __construct(private ResourceConnection $resource, private ScopeConfigInterface $config, private EncryptorInterface $encryptor, private OrderRepositoryInterface $orders, private StoreManagerInterface $stores)
    {
    }
    public function execute(): void
    {
        $db = $this->resource->getConnection();
        // Recover queue-insert failures from authoritative paid orders updated recently.
        $ordersTable = $this->resource->getTableName('sales_order');
        $enabledStores = [];
        foreach ($this->stores->getStores() as $store) {
            if ($this->config->isSetFlag('siderelay/connection/enabled', ScopeInterface::SCOPE_STORE, $store->getId())) {
                $enabledStores[] = (int) $store->getId();
            }
        }
        if (!$enabledStores) {
            return;
        }
        foreach ($db->fetchAll($db->select()->from(['o' => $ordersTable], ['entity_id','store_id','updated_at'])->joinLeft(['q' => $this->resource->getTableName('siderelay_tracking_outbox')], 'q.order_id=o.entity_id', [])->where('q.order_id IS NULL')->where('o.store_id IN (?)', $enabledStores)->where('total_paid >= grand_total')->where('grand_total > 0')->where("state IN (?)", ['processing','complete'])->where('updated_at >= ?', gmdate('Y-m-d H:i:s', time() - 86400))->limit(200)) as $paid) {
            if ($this->config->isSetFlag('siderelay/connection/enabled', ScopeInterface::SCOPE_STORE, $paid['store_id'])) {
                $db->insertOnDuplicate($this->resource->getTableName('siderelay_tracking_outbox'), ['order_id' => (int) $paid['entity_id'], 'occurred_at' => $paid['updated_at']], ['order_id']);
            }
        }
        $table = $this->resource->getTableName('siderelay_tracking_outbox');
        $rows = $db->fetchAll($db->select()->from($table)->where('status IN (?)', ['pending','retry'])->where('next_attempt_at <= UTC_TIMESTAMP()')->limit(50));
        foreach ($rows as $row) {
            // Atomic lease prevents two native cron workers sending the same row concurrently.
            if (!$db->update($table, ['next_attempt_at' => gmdate('Y-m-d H:i:s', time() + 1800)], ['order_id = ?' => $row['order_id'], 'next_attempt_at <= UTC_TIMESTAMP()', 'status IN (?)' => ['pending','retry']])) {
                continue;
            }
            try {
                $order = $this->orders->get((int) $row['order_id']);
                $storeId = (int) $order->getStoreId();
                if (!$this->config->isSetFlag('siderelay/connection/enabled', ScopeInterface::SCOPE_STORE, $storeId)) {
                    continue;
                }
                if ((float) $order->getTotalPaid() + 0.001 < (float) $order->getGrandTotal() || $order->getState() === 'canceled') {
                    continue;
                }
                $token = $this->encryptor->decrypt((string) $this->config->getValue('siderelay/connection/token', ScopeInterface::SCOPE_STORE, $storeId));
                $context = json_decode($this->encryptor->decrypt((string) $order->getData('siderelay_context')), true) ?: [];
                $site = (string) $this->config->getValue('web/unsecure/base_url', ScopeInterface::SCOPE_STORE, $storeId);
                $result = (new Client($token))->purchase(Purchase::event($order, $context, $row['occurred_at'], $site));
            } catch (\Throwable) {
                $result = ['status' => 'configuration'];
            }
            $attempt = (int) $row['attempts'] + 1;
            $status = $result['status'];
            if ($status === 'retry' && $attempt >= 10) {
                $status = 'attention';
            }
            $db->update($table, ['attempts' => $attempt, 'status' => $status, 'next_attempt_at' => gmdate('Y-m-d H:i:s', time() + min(3600, 60 * 2 ** min(10, $attempt - 1)))], ['order_id = ?' => $row['order_id']]);
        }
    }
}
