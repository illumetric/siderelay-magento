# SideRelay for Magento

Preview integration for Magento 2 / Adobe Commerce with PHP 8.2+.
This source is not yet certified against a running Magento store.

1. Install your SideRelay website script and privacy settings.
2. Your developer adds this module with Composer, enables `SideRelay_Tracking`,
   and runs the normal Magento database upgrade and compilation workflow.
3. In **Stores → Configuration → Sales → SideRelay**, paste one backend connection
   token and enable confirmed purchases. Magento encrypts and hides the token.
4. Complete a test payment, run the shop's existing cron, and check SideRelay Activity.
   Turn the setting off to disconnect.

```sh
composer config repositories.siderelay-php vcs https://github.com/illumetric/siderelay-php
composer config repositories.siderelay-magento vcs https://github.com/illumetric/siderelay-magento
composer require siderelay/magento:0.1.0-rc.1
bin/magento module:enable SideRelay_Tracking
bin/magento setup:upgrade
```

The module captures paid invoices and sends only fully paid orders. It uses the
shop's native cron and database outbox, not an SDK-owned persistent queue or a new
SideRelay service. Events keep stable IDs and times across retries. Duplicate
invoices and retries never create another purchase ID. Standard Magento checkout
hands actual privacy choices to the backend; missing choices remain unknown.
Marketing subscription is not tracking consent. Hyvä, headless, and custom checkouts
must provide the same payment `additional_data.siderelay_context` handoff before
advertising matching can be enabled. Purchases can still be confirmed without it.

The success page reuses the backend event ID. The ID incorporates the configured
store base URL, so keep it stable; changing it creates a new namespace. The
invoice-paid time is preserved when queued. Reconciliation recovers recently
updated paid orders if queue insertion failed and uses their recorded update time.
Activating the module may include paid orders updated in the preceding 24 hours.

Temporary failures retry up to ten native queue executions, with bounded backoff.
Check-configuration results wait for a corrected connection. After correcting it:

```sh
bin/magento siderelay:retry
```

The SDK supplies `lead` for custom authoritative lead handlers; this module does
not invent a lead event from a checkout visit or account registration.

Before enabling on a real shop, verify standard checkout permission handoff,
partial payments, invoice duplication, cron contention, token replacement,
revocation, and browser/backend deduplication on that shop's installed version.
Official platform references: [invoice payment events](https://developer.adobe.com/commerce/php/development/components/events-and-observers/event-list)
and [cron](https://developer.adobe.com/commerce/php/development/components/cron-jobs/).
