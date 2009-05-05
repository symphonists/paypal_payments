<?php

  require_once(TOOLKIT . '/class.sectionmanager.php');
  require_once(TOOLKIT . '/class.entrymanager.php');
  require_once(TOOLKIT . '/class.fieldmanager.php');

	Class extension_paypal_payments extends Extension
	{	  
	/*-------------------------------------------------------------------------
		Extension definition
	-------------------------------------------------------------------------*/
		public function about()
		{
			return array('name' => 'PayPal Payments',
						 'version' => '0.1',
						 'release-date' => '2009-05-04',
						 'author' => array('name' => 'Max Wheeler',
										   'website' => 'http://makenosound.com/',
										   'email' => 'max@makenosound.com'),
 						 'description' => 'Allows you to process and track PayPal payments'
				 		);
		}
		
		public function uninstall()
		{
			# Remove tables
			$this->_Parent->Database->query("DROP TABLE `tbl_paypalpayments_logs`");
			
			# Remove preferences
			$this->_Parent->Configuration->remove('paypal-payments');
			$this->_Parent->saveConfig();
		}
		
		public function install()
		{
		  # Create tables
		  $this->_Parent->Database->query("
				CREATE TABLE IF NOT EXISTS `tbl_paypalpayments_logs` (
					`id` int(11) unsigned NOT NULL auto_increment,
					`txn_id` varchar(255) NOT NULL,
					`txn_type` varchar(255) NOT NULL,
					`auth_amount` decimal(10,2) NOT NULL,
					`auth_id` varchar(19) NOT NULL,
					`payment_date` datetime NOT NULL,
					`payment_status` varchar(255) NOT NULL,
					`payer_email` varchar(255) NOT NULL,
					`payer_status` varchar(255) NOT NULL,
					`payment_type` varchar(255) NOT NULL,
					`tax` varchar(255) NOT NULL,
					PRIMARY KEY (`id`)
				)
			");
			
		  return true;
		}
		
		public function getSubscribedDelegates()
		{
			return array(
				array(
					'page'		=> '/blueprints/events/edit/',
					'delegate'	=> 'AppendEventFilter',
					'callback'	=> 'add_filter_to_event_editor'
				),				
				array(
					'page'		=> '/blueprints/events/new/',
					'delegate'	=> 'AppendEventFilter',
					'callback'	=> 'add_filter_to_event_editor'
				),
				array(
					'page' => '/blueprints/events/new/',
					'delegate' => 'AppendEventFilterDocumentation',
					'callback' => 'add_filter_documentation_to_event'
				),					
				array(
					'page' => '/blueprints/events/edit/',
					'delegate' => 'AppendEventFilterDocumentation',
					'callback' => 'add_filter_documentation_to_event'
				),
				array(
					'page' => '/frontend/',
					'delegate' => 'EventPreSaveFilter',
					'callback' => 'check_paypal_preferences'
				),
				array(
					'page'		=> '/frontend/',
					'delegate'	=> 'EventFinalSaveFilter',
					'callback'	=> 'process_event_data'
				),
				array(
					'page' => '/system/preferences/',
					'delegate' => 'AddCustomPreferenceFieldsets',
					'callback' => 'append_preferences'
				),
			);
		}

		/*-------------------------------------------------------------------------
			Preferences
			-------------------------------------------------------------------------*/

		public function append_preferences($context)
		{
			# Add new fieldset
			$group = new XMLElement('fieldset');
			$group->setAttribute('class', 'settings');
			$group->appendChild(new XMLElement('legend', 'PayPal Payments'));
			
			# Add Merchant Email field
			$label = Widget::Label('Merchant Email/Account ID');
			$label->appendChild(Widget::Input('settings[paypal-payments][business]', General::Sanitize($this->_get_paypal_business())));
			$group->appendChild($label);
			$group->appendChild(new XMLElement('p', 'The merchant email address or account ID of the payment recipient.', array('class' => 'help')));
			
			$context['wrapper']->appendChild($group);
		}

  	/*-------------------------------------------------------------------------
  		Navigation
  	-------------------------------------------------------------------------*/
  	
		public function fetchNavigation()
		{
		  $nav = array();
		  $nav[] = array(
				'location'	=> 261,
				'name'		=> 'PayPal Payments',
				'children'	=> array(
					array(
						'name'		=> 'Transactions',
						'link'		=> '/logs/',
						'limit'   => 'developer',
					)
				)
			);
      return $nav;
		}
		
  	/*-------------------------------------------------------------------------
  		Helpers
  	-------------------------------------------------------------------------*/
		
		private function _get_paypal_business()
		{
			return $this->_Parent->Configuration->get('business', 'paypal-payments');
		}

		public function _count_logs()
		{
			return (integer)$this->_Parent->Database->fetchVar('total', 0, "
				SELECT
					COUNT(l.id) AS `total`
				FROM
					`tbl_paypalpayments_logs` AS l
			");
		}
		
		public function _get_logs_by_page($page, $per_page)
		{
			$start = ($page - 1) * $per_page;
			
			return $this->_Parent->Database->fetch("
				SELECT
					l.*
				FROM
					`tbl_paypalpayments_logs` AS l
				ORDER BY
					l.timestamp DESC
				LIMIT {$start}, {$per_page}
			");
		}
		
		public function _get_logs()
		{
			return $this->_Parent->Database->fetch("
				SELECT
					l.*
				FROM
					`tbl_paypalpayments_logs` AS l
				ORDER BY
					l.timestamp DESC
			");
		}
		
		public function _get_log($log_id) {
			return $this->_Parent->Database->fetchRow(0, "
				SELECT
					l.*
				FROM
					`tbl_paypalpayments_logs` AS l
				WHERE
					l.id = '{$log_id}'
				LIMIT 1
			");
		}
  	
  	/*-------------------------------------------------------------------------
  		Filters
  	-------------------------------------------------------------------------*/	
  	
  	public function add_filter_to_event_editor(&$context)
  	{
		  $context['options'][] = array('paypal-payments', @in_array('paypal-payments', $context['selected']) ,'PayPal Payments: Submit');
		}
		
		public function add_filter_documentation_to_event($context)
		{
      if ( ! in_array('paypal-payments', $context['selected'])) return;

      $context['documentation'][] = new XMLElement('h3', 'PayPal Payments');
			$context['documentation'][] = new XMLElement('p', 'Blah:');
			$code = '<input name="fields[amount]" type="text" />
<input name="fields[first-name]" type="text" />
<input name="fields[last-name]" type="text" />
<textarea name="fields[description]"></textarea>

<input name="paypal-payments[cmd]" value="_xclick" type="hidden" />
<input name="paypal-payments[notify_url]" value="{$root}/paypal/" type="hidden" />
<input name="paypal-payments[amount]" value="amount" type="hidden" />
<input name="paypal-payments[name]" value="first-name,last-name" type="hidden" />
<input name="paypal-payments[item_name]" value="description" type="hidden" />
      ';
			$context['documentation'][] = contentBlueprintsEvents::processDocumentationCode($code);
			$context['documentation'][] = new XMLElement('p', 'Note that the <code>id</code> of the newly created entry will be automatically passed to PayPal as the <code>item_id</code>. Multiple fields can be mapped by separating them with commas, they will be joined with a space. All field mappings are optional.');
		}
		
		public function check_paypal_preferences(&$context)
		{	
			if ( ! in_array('paypal-payments', $context['event']->eParamFILTERS)) return;
			
			$business = $this->_get_paypal_business();
			
			if( ! isset($business))
			{
				$context['messages'][] = array(
					'paypal-payments',
					FALSE,
					'You need to set the your business id/email in the preferences.'
				);
				return;
			}
		}
		
		public function process_event_data($context)
		{
			# Check if in included filters
			if ( ! in_array('paypal-payments', $context['event']->eParamFILTERS)) return;
			
			# Allowed fields
			$allowed_fields = array(
				'cmd',												'notify_url',
				'bn',													'amount',
				'item_name',									'item_number',
				'quantity',										'undefined_ quantity',
				'weight',											'weight_unit',
				'on0',												'on1',
				'os0',												'os1',
				'address_override',						'currency_code',
				'custom',											'handling',
				'invoice',										'shipping',
				'shipping2',									'tax',
				'tax_cart',										'weight_cart',
				'weight_unit',								'business',
				'return',											'rm',
				'cancel_return',							'a1',
				'p1',													't1',
				'a2',													'p2',
				't2',													'a3',
				'p3',													't3',
				'src',												'srt',
				'sra',												'no_note',
				'custom',											'usr_manage',
				'cs',													'currency_code',
				'modify',											'lc',
				'page_style',									'cbt',
				'cn',													'cpp_header_image',
				'cpp_headerback_color',				'cpp_headerborder_color',
				'cpp_payflow_color',					'image_url',
				'no_shipping',								'address1',
				'address2',										'city',
				'country',										'first_name',
				'last_name',									'lc',
				'night_phone_a',							'night_phone_b',
				'night_phone_c',							'state',
				'zip',
			);
			
			# Set the default dataset
			$data = array(
				'invoice' =>	$context['entry']->get('id'),
				'business' =>			$this->_get_paypal_business()
			);
			
			$mapping = $_POST['paypal-payments'];			
			if ( isset($mapping))
			{
				foreach ($mapping as $key => $val)
				{
					# Check the field is allowed
					if( ! in_array($key, $allowed_fields)) continue;
					# Join multiple fields
					if (strpos($val, ","))
					{
						$values = explode(",", $val);
						$combo = array();
						foreach ($values as $val)
						{
							if (preg_match("/^'[^']+'$/", $val)) $combo[] = trim($val, "'");
							else $combo[] = isset($context['fields'][$val]) ? $context['fields'][$val] : $val;
						}
						$mapping[$key] = implode(" ", $combo);
					} else {
						# If there's a match, map the value of the match. Else output the value.
						if (preg_match("/^'[^']+'$/", $val)) $mapping[$key] = trim($val, "'");
						else $mapping[$key] = isset($context['fields'][$val]) ? $context['fields'][$val] : $val;
					}
				}
				$data = array_merge($data, $mapping);
			}
			
			# Build up faker HTML output
			$output = '<html>
<head>
<title>Continue to PayPal</title>
</head>
<style type="text/css">button{background:#eee;border:3px #ccc solid;color:#444;display:block;font:normal 200%/1.4 Georgia, Palatino, serif;margin:19% auto 40px;padding:20px 40px;-moz-border-radius:30px;-webkit-border-radius:30px;cursor:pointer;}button:hover{background:#444;color:#fff;border-color:#222;}</style>
<script type="text/javascript">
document.write(\'<style type="text/css">button{display:none}</style>\');
</script>
<body onload="document.forms.paypal.submit();">
<form id="paypal" method="post" action="https://www.paypal.com/cgi-bin/webscr">';
			foreach($data as $field => $value)
			{
				$output .= '  <input type="hidden" name="' . $field .'" value="' . $value . '"/>';
			}
			$output .= '<button type="submit">Continue to PayPal to make payment</button>
</form>
</body>
</html>';
			print($output);
			exit();
		}
	}