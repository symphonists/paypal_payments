<?php


	Final Class eventPaypal_payments_ipn extends Event{

		public static function about()
		{					
			return array(
						 'name' => 'PayPal Payments: Save IPN data',
						 'author' => array('name' => 'Max Wheeler',
											 'website' => 'http://www.icelab.com.au',
											 'email' => 'max@icelab.com.au'),
						 'version' => '0.1',
						 'release-date' => '2009-05-04',						 
					);						 
		}
		
		public function __construct(&$parent)
		{
			parent::__construct($parent);
			$this->_driver = $this->_Parent->ExtensionManager->create('paypal_payments');
		}
		
		public function load()
		{
			# Verify the data
			
			$uri = "https://www.sandbox.paypal.com/cgi-bin/webscr?".implode('&', $_POST);
			
			if(function_exists('curl_init')){
				$ch = curl_init();
				
				curl_setopt($ch, CURLOPT_URL, $uri);
				curl_setopt($ch, CURLOPT_HEADER, 0);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				
				$tmp = curl_exec($ch);
				curl_close($ch);			
			}			
			else $tmp = @file_get_contents($uri);
			print_r($tmp);
			
			# Check that the POST data contains the `txn_id` value
			#if ($tmp == 'VERIFIED' && is_array($_POST) &&	! empty($_POST) && isset($_POST['txn_id'])) 
			return $this->__trigger();			
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
<p>The transaction logs store the following data from SecureTrading:</p>
<ul>
	<li><code>address</code></li>
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
				'item_name1',										'item_name2',
				'item_number',									'last_name',
				'mc_currency',									'mc_fee',
				'mc_fee_',											'mc_gross',
				'mc_gross_',										'mc_handling',
				'mc_shipping',									'memo',
				'multi-currency',								'notify_version',
				'num_cart_items',								'option_name1',
				'option_name2',									'option_selection1',
				'option_selection2',						'order',
				'other',												'parent_txn_id',
				'payer_business_name',					'payer_email',
				'payer_id',											'payer_status',
				'paymentreview',								'payment_date',
				'payment_fee',									'payment_gross',
				'payment_status',								'payment_type',
				'pending_reason',								'protection_eligibility',
				'quantity',											'quantity1',
				'quantity2',										'reason_code',
				'receiver_email',								'receiver_id',
				'refund',												'remaining_settle',
				'residence_country',						'settle_amount',
				'settle_currency',							'shipping',
				'shipping1',										'shipping2',
				'shipping_method',							'tax',
				'test_ipn',											'transaction_entity',
				'txn_id',												'txn_type',
				'unconfirmed',									'unilateral',
				'unverified',										'upgrade',
				'verified',											'verify',
				'verify_sign',
			);
			
			$required_variables = array(
				"address",
				"currency",
				"inputamount",
				"name",
				"orderref",
				"postcode",
				"stauthcode",
				"streference",
				"stresult",
				"timestamp",
				"truncccnumber"
			);
			
			$filename = EXTENSIONS . "/paypal_payments/log.txt";
			print_r($filename);
			$w = fopen($filename, "w");
			fwrite($w, serialize($_POST));
			fclose($w);
			
			echo 'die';
			exit();
			
			# Find any matches in the $_POST data
			$matches = array();
			foreach ($_POST as $key => $val)
			{
				if (in_array($key, $valid_variables)) $matches[$key] = $val;
			}

			# Output the matches in XML
			$result = new XMLElement('paypal-payments-ipn');
			$log = array();

			if ( ! empty($matches))
			{
				foreach ($matches as $key => $val)
				{
					$val = utf8_encode(General::sanitize($val));
					$result->appendChild(new XMLElement($key, $val));
					# If it's the timestamp, reformat as datetime
					if ($key == "timestamp") {
						$log[$key] = date("YmdHis", $val);
					}
					# If in required vars, add to log
					else if (in_array($key, $required_variables))
					{
						$log[$key] = $val;
					}
				}
				
				# Reconcile with original entry
				$entry_id = $log['orderref'];
				
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
						# Reformat `stresult` as "Success" or "Fail" 
						# Check if entry fields match values returned from SecureTrading
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
				}
				
				# Save log
				$this->_Parent->Database->insert($log, 'tbl_stpayments_logs');
				return $result;
			}
			else
			{
				return NULL;
			}
		}
	}