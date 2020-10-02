# PayPal Payments, digital payment become more fast #

The *PayPal Payments* extension allows you to reroute standard Symphony events
to PayPal Standard Payments.

## Installation ##
 
1. Upload the `paypal_payments` folder in this archive to your Symphony
	 `extensions` folder.
 
2. Enable it by selecting the *PayPal Payments*, choose Enable from the
	 with-selected menu, then click Apply.
 
3. Go to "System" -> "Preferences" and enter your merchant email/ account ID
	 under the *PayPal Payments*.

## Usage ##

The extension includes an event filter that handles the redirection
and a "Save IPN data" Event that logs transactions and reconciles returned
data with the newly created entry.

### Filter: Reroute to PayPal ###

The *Reroute to PayPal* filter lets you pass data to PayPal’s server by mapping fields to most of the variables/fields listed in [Website Payments Standard documentation][1]. The example below shows how you would map `amount`, `first-name`/`last-name` and `description` to their PayPal equivalents:

	<form action="" method="post">
		<input name="fields[amount]" type="text" />
		<input name="fields[first-name]" type="text" />
		<input name="fields[last-name]" type="text" />
		<textarea name="fields[description]"></textarea>

		<input name="paypal-payments[cmd]" value="_xclick" type="hidden" />
		<input name="paypal-payments[notify_url]" value="{$root}/paypal/" type="hidden" />
		<input name="paypal-payments[amount]" value="amount" type="hidden" />
		<input name="paypal-payments[name]" value="first-name,last-name" type="hidden" />
		<input name="paypal-payments[item_name]" value="description" type="hidden" />
	
		<input type="submit" name="action[save-entry]"/>
	</form>

Note that the `id` of the newly created entry will be automatically passed to PayPal as the `invoice`. Multiple fields can be mapped by separating them by commas, they will be joined with a space. All field mappings are optional. Fields that do not match a 'mapped' field will be passed on unchanged, as with `cmd` in the example above.

### Event: Save IPN data ###

This event is used to deal with data returned by [PayPal’s Instant Payment Notification][2] (IPN). It does the following:</p>

1. Saves the transaction details to the transaction log.
2. Reconciles the data return by PayPal with matching fields in the originating entry.

A number of default fields are logged in the transaction log. They are:

* `invoice`
* `payment_type`
* `payment_date`
* `payment_status`
* `address_status`
* `payer_status`
* `first_name`
* `last_name`
* `payer_email`
* `payer_id`
* `address_name`
* `address_country`
* `address_country_code`
* `address_zip`
* `address_state`
* `address_city`
* `address_street`
* `residence_country`
* `tax`
* `mc_currency`
* `mc_fee`
* `mc_gross`
* `txn_type`
* `txn_id`
* `notify_version`
* `verify_sign`

Any of these fields (and most of the other fields returned by the IPN — see the valid variables list below) can be saved back into the original entry by including a field in the matching section with the *exact* same name. Your IPN data *must* include an `invoice` field that matches an entry ID in your site otherwise the data will be discarded (this means when testing via the PayPal sandbox you'll have to manually set the `invoice` value).

*Note: for the event to work you'll need to make sure the your IPN URL points to the page that has this event attached.*

### Valid Variables ###

* `address`
* `address_city`
* `address_country`
* `address_country_code`
* `address_name`
* `address_state`
* `address_status`
* `address_street`
* `address_zip`
* `adjustment_reversal`
* `authorization`
* `auth_amount`
* `auth_exp`
* `auth_id`
* `auth_status`
* `business`
* `buyer-complaint`
* `chargeback`
* `chargeback_reimbursement`
* `chargeback_settlement`
* `charset`
* `confirmed`
* `contact_phone`
* `custom`
* `echeck`
* `exchange_rate`
* `first_name`
* `guarantee`
* `instant`
* `intl`
* `invoice`
* `item_name`
* `item_number`
* `last_name`
* `mc_currency`
* `mc_fee`
* `mc_gross`
* `mc_handling`
* `mc_shipping`
* `memo`
* `multi-currency`
* `notify_version`
* `order`
* `verify_sign`
* `other`
* `parent_txn_id`
* `payer_business_name`
* `payer_email`
* `payer_id`
* `payer_status`
* `paymentreview`
* `payment_date`
* `payment_fee`
* `payment_gross`
* `payment_status`
* `payment_type`
* `pending_reason`
* `protection_eligibility`
* `quantity`
* `reason_code`
* `receiver_email`
* `receiver_id`
* `refund`
* `remaining_settle`
* `residence_country`
* `settle_amount`
* `settle_currency`
* `shipping`
* `shipping_method`
* `tax`
* `test_ipn`
* `transaction_entity`
* `txn_id`
* `txn_type`
* `unconfirmed`
* `unilateral`
* `unverified`
* `upgrade`
* `verified`
* `verify`

## Notes ##

As the information needs to be submitted to PayPal via POST and that POST data can't be manipulated we have to 'fake' a way of passing it on. This is done by redirecting the user to a dynamically generated form that is automatically submitting via JavaScript—the downside obviously being that if a user does not have JavaScript enabled then they'll have to click through to continue onto PayPal.

[1]: https://cms.paypal.com/us/cgi-bin/?cmd=_render-content&content_ID=developer/e_howto_html_Appx_websitestandard_htmlvariables
[2]: https://cms.paypal.com/cms_content/US/en_US/files/developer/IPNGuide.pdf
