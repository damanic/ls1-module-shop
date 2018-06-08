# Shop Module

### Lemonstand Version 1
This updated module can be installed using the updatecenter module: https://github.com/damanic/ls1-module-updatecenter

#### Variant Invoice Templates
Allows for any number of 'invoice' variants to be selected for print (eg. quotes, refund, proforma-invoice, invoice). Example config in `/invoice_templates/ls_variant`

#### Updates
- 1.30.0 Invoice templates updated to support variant commercial document templates (eg. quote, proforma-invoice) and some render override events (eg. pass html to a PDF renderer).
- 1.30.1 Minor improvement in the API. Added new API event: `shop:onCustomerBeforeCreate`
- 1.30.2 Minor improvement in the API. Added new API event: `shop:onBeforeCheckoutStepPay`
- 1.30.3 Minor improvement in internal classes. Shop_OrderHelper deprecated some methods in Shop_Orders controller
- 1.30.4 Minor improvement. Shop_Settings controller triggers form record events (`core:onBeforeFormRecordUpdate`|`core:onAfterFormRecordUpdate`).
- 1.30.5 Minor improvement. Added `shop:onCustomerSaved` event to Customer Model. This is fired on both model create and update.
- 1.30.6 Minor improvement. Added filter to hide disabled shipping options in list view.
- 1.30.7 New Event: Added `shop:onOrderSetAppliedCartRules`
- 1.30.8 Minor improvement. Hidden order currency fields, updated reports to convert totals to shop/base currency if order in different currency.
- 1.30.9 Fix: `Shop_PaymentType::update_currency_data()`
- 1.30.10 New Event: `shop:onGetOptionMatrixProduct`
- 1.30.11 Added new API event: `shop:onApplyOrderEmailVars`
- 1.30.12 Fixed issue with order document/invoice templates
- 1.30.13 Added discount action 'Discount the shipping cost by fixed amount'
- 1.30.14 New methods and events to enable integration of custom order references:
    - New Shop_Order Methods:
        - `get_order_reference()` 
        - `find_by_order_reference()`
    - New Shop_Order Events: 
         - `shop:onOrderFindByOrderReference`
         - `shop:onGetOrderReference`
- 1.30.15 Shop_ProductReview::create_review() returns created review
- 1.30.16 Removed numerical restriction from invoice preg_match in paypal standard payment type
- 1.30.17 Added get_currency method to Shop_Order
- 1.30.18 Added event: shop:onAdjustCurrencyConverterRate
- 1.30.19 Added new API event: shop:onOptionMatrixGetPrice
- 1.30.20 Added new API event: shop:onAppendShippingQuoteCacheKey
- 1.30.21 Allow exchange rates to be updated by CRON
- 1.30.22 Allow API Event shop:onApplyOrderEmailVars to overwrite existing email vars
- 1.30.23 Added new API event: shop:onUpdateShippingOptions
- 1.30.24 Added new API event: shop:onOptionMatrixRecordBeforeUpdate
- 1.30.25 Minor change: shiping_sub_option_id, renamed to shipping_sub_option_id
- 1.30.26 Fixed issue with shipping multi-option hash on backend form submits. Shop_OrderHelper::getShippingSubOptionHash
- 1.31.0 Payment gateways can be set up to record and manage multiple transactions per order keeping track of amount paid/refunded/still-due
- 1.31.1 Discontinued Yahoo currency converter. Added fixer.io currency converter.
- 1.31.2 Minor Fix for get_payment_due()
- 1.31.3 Add indices to payment_transactions
- 1.31.4 Minor improvement to payment transaction status logs
- 1.31.5 Improved payment transaction totals
- 1.31.6|@phpr-patch_1.31.6 Improve order currency field display, New event shop:onCustomerDisplayField.
- 1.31.7 Adds order locks. New user permissions for order lock and permanent delete.
- 1.31.8 Adds shipping boxes to shipping settings and a box packer helper to help with shipping cost calculations
- 1.31.9 New event `shop:onOrderAfterModify` is triggered after order is created, updated or deleted
- 1.31.10 Support for multiple Attribute and Option selections in search results
- 1.31.11 Bugfix: shop_actions PHP 5.3 compatible