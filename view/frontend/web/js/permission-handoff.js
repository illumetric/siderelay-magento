define(['mage/utils/wrapper'], function (wrapper) {
 'use strict';
 return function (placeOrder) {
  return wrapper.wrap(placeOrder, function (original, paymentData, messageContainer) {
   try { if (window.SideRelay && window.SideRelay.getBackendContext) {
    paymentData.additional_data = paymentData.additional_data || {};
    paymentData.additional_data.siderelay_context = JSON.stringify(window.SideRelay.getBackendContext('checkout'));
   }
   } catch (error) { /* Missing choices stay unknown. */ }
   return original(paymentData, messageContainer);
  });
 };
});
