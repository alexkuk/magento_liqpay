Installation instructions:
1. Copy/Upload "app" folder into you Magento root folder.
2. Clear you Magento cache.
3. Login to you Magento Admin panel. Click on System -> Configuration -> Payment Methods menu, 
then find "Visa/MasterCard (Liqpay)" tab. Fill out all the information requested.
4. It's desirable that you have Magento crontab configured for the extension 
to update liqpay CC validation in background (read more about Magento crontabs here:
http://www.magentocommerce.com/wiki/1_-_installation_and_configuration/how_to_setup_a_cron_job#unixbsdlinux)
Anyway, you're always able to renew validation status manually from the order managment.
5. The extension changes order status during a payment process. 
- "Pending" status - an order was submitted and user redirected to Liqpay payment page.
- "Pending payment" status - user has completed payment, but credit card is being verified by Liqpay.
- "Processing" status - payment completed.
If you have Magento crontab configured correctly, the extension will change "Pending payment" status to
"Processing" automatically after credit card is verified. Anyway, you're always 
able to renew validation status manually from the order management.