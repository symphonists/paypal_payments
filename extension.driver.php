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
						 'version' => '1.0',
						 'release-date' => '2009-07-10',
						 'author' => array('name' => 'Max Wheeler',
										   'website' => 'http://makenosound.com/',
										   'email' => 'max@makenosound.com'),
 						 'description' => 'Allows you to process and track Website Payments Standard transactions from PayPal.'
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
					`payment_type` varchar(255) NOT NULL,
					`payment_date` datetime NOT NULL,
					`payment_status` varchar(255) NOT NULL,
					`address_status` varchar(255) NOT NULL,
					`payer_status` varchar(255) NOT NULL,
					`first_name` varchar(255) NOT NULL,
					`last_name` varchar(255) NOT NULL,
					`payer_email` varchar(255) NOT NULL,
					`payer_id` varchar(255) NOT NULL,
					`address_name` varchar(255) NOT NULL,
					`address_country` varchar(255) NOT NULL,
					`address_country_code` varchar(255) NOT NULL,
					`address_zip` varchar(255) NOT NULL,
					`address_state` varchar(255) NOT NULL,
					`address_city` varchar(255) NOT NULL,
					`address_street` varchar(255) NOT NULL,
					`residence_country` varchar(255) NOT NULL,
					`tax` decimal(10,2) NOT NULL,
					`mc_currency` varchar(3) NOT NULL,
					`mc_fee` decimal(10,2) NOT NULL,
					`mc_gross` decimal(10,2) NOT NULL,
					`txn_type` varchar(255) NOT NULL,
					`txn_id` varchar(255) NOT NULL,
					`notify_version` varchar(255) NOT NULL,
					`invoice` varchar(255) NOT NULL,
					`verify_sign` varchar(255) NOT NULL,
					PRIMARY KEY (`id`)
				)
			");
		  return true;
		}
		
		public function getSubscribedDelegates()
		{
			return array(
				array(
					'page' => '/system/preferences/',
					'delegate' => 'Save',
					'callback' => 'save_preferences'
				),
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
			
			# Country <select>
			$countries = array(
				'Australia',
				'United Kingdom',
				'United States',
			);
			$selected_country = $this->_get_country();
			foreach ($countries as $country)
			{
				$selected = ($country == $selected_country) ? TRUE : FALSE;
				$options[] = array($country, $selected);
			}
			
			$label = Widget::Label();
			$select = Widget::Select('settings[paypal-payments][country]', $options);
			$label->setValue('PayPal Country' . $select->generate());
			$group->appendChild($label);
			$group->appendChild(new XMLElement('p', 'Country you want to target.', array('class' => 'help')));
			
			# Sandbox
			$label = Widget::Label();
			$input = Widget::Input('settings[paypal-payments][sandbox]', 'yes', 'checkbox');
			if($this->_Parent->Configuration->get('sandbox', 'paypal-payments') == 'yes') $input->setAttribute('checked', 'checked');
			$label->setValue($input->generate() . ' Enable testing mode');
			$group->appendChild($label);
			$group->appendChild(new XMLElement('p', 'Directs payments to PayPal’s Sandbox: <code>http://www.sandbox.paypal.com/</code>', array('class' => 'help')));
			
			$context['wrapper']->appendChild($group);
		}
		
		public function save_preferences($context)
		{
			if( ! isset($context['settings']['paypal-payments']['sandbox']))
			{
				$context['settings']['paypal-payments']['sandbox'] = 'no';
			}
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
		
		private function _sandbox_enabled()
		{
			return ($this->_Parent->Configuration->get('sandbox', 'paypal-payments') == 'yes') ? TRUE : FALSE;
		}
		
		private function _get_country()
		{
			$country = $this->_Parent->Configuration->get('country', 'paypal-payments');
			return (isset($country)) ? $country : 'United States';
		}
		
		public function _build_paypay_url()
		{
			$countries_tld = array(
				'Australia'			 => 'com.au',
				'United Kingdom' => 'co.uk',
				'United States'	 => 'com',
			);
			
			if ($this->_sandbox_enabled()) $url = 'http://www.sandbox.paypal.com';
			else $url = 'https://www.paypal.' . $countries_tld[$this->_get_country()];
			$url .= '/cgi-bin/webscr';
			return $url;
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
					l.payment_date DESC
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
					l.payment_date DESC
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
		  $context['options'][] = array('paypal-payments', @in_array('paypal-payments', $context['selected']) ,'PayPal Payments: Reroute to PayPal');
		}
		
		public function add_filter_documentation_to_event($context)
		{
      if ( ! in_array('paypal-payments', $context['selected'])) return;

      $context['documentation'][] = new XMLElement('h3', 'PayPal Payments: Reroute to PayPal');
			$context['documentation'][] = new XMLElement('p', 'You can pass data to PayPal&#8217;s server by mapping fields to most of the variables/fields listed in <a href="https://cms.paypal.com/us/cgi-bin/?cmd=_render-content&content_ID=developer/e_howto_html_Appx_websitestandard_htmlvariables">Website Payments Standard documentation</a>. The example below shows how you would map <code>amount</code>, <code>first-name</code>/<code>last-name</code> and <code>description</code> to their PayPal equivalents:');
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
			$context['documentation'][] = new XMLElement('p', 'Note that the <code>id</code> of the newly created entry will be automatically passed to PayPal as the <code>invoice</code>. Multiple fields can be mapped by separating them with commas, they will be joined with a space. All field mappings are optional.');
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
					'You need to set the your business ID/email in the preferences.'
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
				'invoice' =>		$context['entry']->get('id'),
				'business' =>		$this->_get_paypal_business()
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
			
			# Figure out URL
			$url = $this->_build_paypay_url();
			
			# Build up faker HTML output
			$output = '<html><head><title>Continue to PayPal</title></head>
<style type="text/css">button{background:#eee;border:3px #ccc solid;color:#444;display:block;font:normal 200%/1.4 Georgia, Palatino, serif;margin:19% auto 40px;padding:20px 40px;-moz-border-radius:30px;-webkit-border-radius:30px;cursor:pointer;}button:hover{background:#444;color:#fff;border-color:#222;}</style>
<script type="text/javascript">
document.write(\'<style type="text/css">button{display:none}</style>\');
</script>
<body onload="document.forms.paypal.submit();">
<form id="paypal" method="post" action="'.$url.'">';
			foreach($data as $field => $value)
			{
				$output .= '  <input type="hidden" name="' . $field .'" value="' . $value . '"/>' . "\n";
			}
			$output .= '<button type="submit">Continue to PayPal to make payment</button>
</form>
</body>
</html>';
			print($output);
			exit();
		}
	}