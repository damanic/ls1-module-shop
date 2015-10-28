#Shop Module
###Lemonstand Version 1
This updated module can be installed using the updatecenter module: https://github.com/damanic/ls1-module-updatecenter

####Variant Invoice Templates
Allows for quotes, proforma-invoice, invoice or any number of print variants. Example config in '/invoice_templates/ls_variant'

####Updates
- 1.30.0 Invoice templates updated to support variant commercial document templates (eg. quote, proforma-invoice) and some render override events (eg. pass html to a PDF renderer).
- 1.30.1 Minor improvement in the API. Added new API event: shop:onCustomerBeforeCreate
- 1.30.2 Minor improvement in the API. Added new API event: shop:onBeforeCheckoutStepPay
- 1.30.3 Minor improvement in internal classes. Shop_OrderHelper deprecated some methods in Shop_Orders controller
