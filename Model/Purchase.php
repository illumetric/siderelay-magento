<?php

declare(strict_types=1);

namespace SideRelay\Tracking\Model;

use SideRelay\Client;

final class Purchase
{
    public static function permissionContext(array $input): array
    {
        $consent = ['analytics' => 'unknown', 'advertising' => 'unknown', 'personalization' => 'unknown', 'source' => 'custom'];
        foreach (['analytics', 'advertising', 'personalization'] as $purpose) {
            if (in_array($input['consent'][$purpose] ?? '', ['granted','denied'], true)) {
                $consent[$purpose] = $input['consent'][$purpose];
            }
        }
        foreach (['global_privacy_control','sale_sharing_opt_out','targeted_advertising_opt_out'] as $flag) {
            $consent[$flag] = ($input['consent'][$flag] ?? false) === true;
        }
        if ($consent['global_privacy_control'] || $consent['sale_sharing_opt_out'] || $consent['targeted_advertising_opt_out']) {
            $consent['advertising'] = $consent['personalization'] = 'denied';
        }
        $identity = [];
        foreach ((array) ($input['identity'] ?? []) as $key => $value) {
            $allowed = in_array($key, ['anonymous_id','session_id','ga_client_id','matomo_visitor_id','fbp','fbc','gclid','gbraid','wbraid','ttclid','msclkid','sc_click_id','li_fat_id','epik','rdt_cid'], true);
            if ($allowed && is_string($value) && strlen($value) <= 512 && ($consent['advertising'] === 'granted' || ($consent['analytics'] === 'granted' && in_array($key, ['anonymous_id','session_id','ga_client_id','matomo_visitor_id'], true)))) {
                $identity[$key] = $value;
            }
        }
        return ['consent' => $consent, 'identity' => $identity];
    }
    public static function event(object $order, array $context, string $paidAt, string $site): array
    {
        $context = self::permissionContext($context);
        if ($context['consent']['advertising'] === 'granted' && $order->getCustomerEmail()) {
            $context['identity']['email_sha256'] = Client::hashIdentity($order->getCustomerEmail());
        }
        return ['event_id' => 'purchase:magento:' . substr(hash('sha256', $site), 0, 12) . ':' . $order->getId(), 'occurred_at' => (new \DateTimeImmutable($paidAt, new \DateTimeZone('UTC')))->format(DATE_ATOM), 'consent' => $context['consent'], 'identity' => $context['identity'], 'properties' => ['transaction_id' => (string) $order->getIncrementId(), 'value' => (float) $order->getGrandTotal(), 'currency' => $order->getOrderCurrencyCode()]];
    }
}
