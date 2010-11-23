<?php


	Final Class eventPaypal_payments_ipn extends Event{

		public static function about()
		{					
			return array(
						 'name' => 'PayPal Payments: Save IPN data',
						 'author' => array('name' => 'Max Wheeler',
											 'website' => 'http://www.icelab.com.au',
											 'email' => 'max@icelab.com.au'),
						 'version' => '1.0.3',
						 'release-date' => '2010-11-24',
					);						 
		}
		
		public function __construct(&$parent)
		{
			parent::__construct($parent);
			$this->_driver = $this->_Parent->ExtensionManager->create('paypal_payments');
		}
		
		public function load()
		{
			# Ensure we're receiving IPN data by checking for `verify_sign`
			if ( ! isset($_POST['verify_sign'])) return NULL;
			
			# Verify the data
			# Set the request paramaeter 
			$req = 'cmd=_notify-validate'; 

			# Run through the posted array 
			foreach ($_POST as $key => $value)
			{ 
				# If magic quotes is enabled strip slashes 
				if (get_magic_quotes_gpc()) 
				{ 
					$_POST[$key] = stripslashes($value); 
					$value = stripslashes($value); 
				} 
				$value = urlencode($value); 
				# Add the value to the request parameter 
				$req .= "&$key=$value";
			} 
			
			$url = $this->_driver->_build_paypay_url(true);
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL,$url);
			curl_setopt($ch, CURLOPT_FAILONERROR, 1); 
			curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
			curl_setopt($ch, CURLOPT_TIMEOUT, 3);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $req);
			$result = curl_exec($ch);
			curl_close($ch);
						
			# Check that we have data and that it’s VERIFIED
			if (strcmp ($result, "VERIFIED") == 0 && is_array($_POST) && ! empty($_POST)) return $this->__trigger();
			return NULL;
		}

		public static function documentation()
		{
			$docs = array();
			$docs[] = '
<p>This event is used to deal with data returned by PayPal&#8217;s Instant Payment Notification (IPN). It does the following:</p>
<ol>
	<li>Saves the transaction details to <a href="' . URL . '/symphony/extension/paypal_payments/logs/">the log</a>.</li>
	<li>Reconciles the data return by PayPal with matching fields in the originating entry.</li>
	<li>Outputs said data as XML.</li>
</ol>
<p>For the event to work you&#8217;ll need to make sure the your IPN URL points to the page that has this event attached.</p>
<h3>Transaction Logs</h3>
<p>The transaction logs store the following data from PayPal:</p>
<ul>
	<li><code>invoice</code></li>
	<li><code>payment_type</code></li>
	<li><code>payment_date</code></li>
	<li><code>payment_status</code></li>
	<li><code>address_status</code></li>
	<li><code>payer_status</code></li>
	<li><code>first_name</code></li>
	<li><code>last_name</code></li>
	<li><code>payer_email</code></li>
	<li><code>payer_id</code></li>
	<li><code>address_name</code></li>
	<li><code>address_country</code></li>
	<li><code>address_country_code</code></li>
	<li><code>address_zip</code></li>
	<li><code>address_state</code></li>
	<li><code>address_city</code></li>
	<li><code>address_street</code></li>
	<li><code>residence_country</code></li>
	<li><code>tax</code></li>
	<li><code>mc_currency</code></li>
	<li><code>mc_fee</code></li>
	<li><code>mc_gross</code></li>
	<li><code>txn_type</code></li>
	<li><code>txn_id</code></li>
	<li><code>notify_version</code></li>
	<li><code>verify_sign</code></li>
</ul>
<p>Any other valid PayPal fields are ignored by the logs, though they are still able to be used for reconciling data in the originating entry or in the XML ouput.</p>

<h3>Reconciling Data</h3>
<p>To save any of the PayPal data to the originating entry, you simply need to include a field in that section with the <em>exact</em> name of the matching PayPal variable. The event will process any valid PayPal fields and update the matching named fields in the entry. A list of valid fields is available on the <a href="https://cms.paypal.com/us/cgi-bin/?cmd=_render-content&content_ID=developer/e_howto_html_IPNandPDTVariables">IPN and PDT Variables page</a>.</p>
<p>All variables are dumped unprocessed into their matching fields, so you’ll need to make sure the field types in the section match the data in the response from PayPal.</p>

<h3>XML Output</h3>
<p>All data returned from PayPal is included as the <code>&lt;paypal-payments-ipn&gt;</code> node in the XML output for use in frontend pages.</p>
';
			return implode("\n", $docs);
		}
		
		protected function __trigger()
		{			
			# Array of valid variables from PayPal
			$valid_variables = array(
				'address',											'address_city',
				'address_country',							'address_country_code',
				'address_name',									'address_state',
				'address_status',								'address_street',
				'address_zip',									'adjustment_reversal',
				'authorization',								'auth_amount',
				'auth_exp',											'auth_id',
				'auth_status',									'business',
				'buyer-complaint',							'chargeback',
				'chargeback_reimbursement',			'chargeback_settlement',
				'charset',											'confirmed',
				'contact_phone',								'custom',
				'echeck',												'exchange_rate',
				'first_name',										'guarantee',
				'instant',											'intl',
				'invoice',											'item_name',
				'item_number',									'last_name',
				'mc_currency',									'mc_fee',
				'mc_gross',											'mc_handling',
				'mc_shipping',									'memo',
				'multi-currency',								'notify_version',
				'order',												'verify_sign',
				'other',												'parent_txn_id',
				'payer_business_name',					'payer_email',
				'payer_id',											'payer_status',
				'paymentreview',								'payment_date',
				'payment_fee',									'payment_gross',
				'payment_status',								'payment_type',
				'pending_reason',								'protection_eligibility',
				'quantity',											'reason_code',
				'receiver_email',								'receiver_id',
				'refund',												'remaining_settle',
				'residence_country',						'settle_amount',
				'settle_currency',							'shipping',
				'shipping_method',							'tax',
				'test_ipn',											'transaction_entity',
				'txn_id',												'txn_type',
				'unconfirmed',									'unilateral',
				'unverified',										'upgrade',
				'verified',											'verify',
			);
			
			$required_variables = array(
				'invoice',
				'payment_type',
				'payment_date',
				'payment_status',
				'address_status',
				'payer_status',
				'first_name',
				'last_name',
				'payer_email',
				'payer_id',
				'address_name',
				'address_country',
				'address_country_code',
				'address_zip',
				'address_state',
				'address_city',
				'address_street',
				'residence_country',
				'tax',
				'mc_currency',
				'mc_fee',
				'mc_gross',
				'txn_type',
				'txn_id',
				'notify_version',
				'verify_sign',
			);
			
			# Find any matches in the $_POST data
			$matches = array();
			foreach ($_POST as $key => $val)
			{
				if (in_array($key, $valid_variables)) $matches[$key] = utf8_encode(General::sanitize($val));
			}

			# Output the matches in XML
			$output = new XMLElement('paypal-payments-ipn');
			$log = array();

			if ( ! empty($matches))
			{
				foreach ($matches as $key => $val)
				{
					$output->appendChild(new XMLElement($key, $val));
					# If in required vars, add to log
					if (in_array($key, $required_variables))
					{
						if ($key == 'payment_date') $val = strftime('%Y-%m-%d %H:%M:%S', strtotime($val));
						$log[$key] = $val;
					}
				}
				
				# Reconcile with original entry
				$entry_id = $log['invoice'];
				
				$entryManager = new EntryManager($this->_Parent);
				$fieldManager = new FieldManager($this->_Parent);
				
				$entries = $entryManager->fetch($entry_id, null, null, null, null, null, false, true);
				if (count($entries) > 0)
				{
					$entry = $entries[0];
					$section_id = $entry->_fields['section_id'];
					$fields = $this->_Parent->Database->fetch("
						SELECT `id`, `label` FROM `tbl_fields` WHERE `parent_section` = '$section_id'
					");
			
					foreach ($fields as $field)
					{
						$label = $field['label'];
						# Check if entry fields match values returned from PayPal
						if (in_array($label, $valid_variables))
						{
							$value = $log[$label];
							$entry->setData($field['id'], array(
									'handle'	=> Lang::createHandle($value),
									'value'	 => $value
								)
							);
						}
					}	
					# Transfom and move out
					$entry->commit();
				} else {
					$output->appendChild(new XMLElement('error', 'No matching entry, could not reconcile payment data.'));
				}
				
				# Save log
				$this->_Parent->Database->insert($log, 'tbl_paypalpayments_logs');
				return $result;
			}
			else
			{
				return NULL;
			}
		}
	}