<?php

  require_once(TOOLKIT . '/class.sectionmanager.php');
  require_once(TOOLKIT . '/class.entrymanager.php');
  require_once(TOOLKIT . '/class.fieldmanager.php');

	Class extension_securetrading_payments extends Extension
	{	  
	/*-------------------------------------------------------------------------
		Extension definition
	-------------------------------------------------------------------------*/
		public function about()
		{
			return array('name' => 'SecureTrading Payments',
						 'version' => '0.1',
						 'release-date' => '2009-02-23',
						 'author' => array('name' => 'Icelab',
										   'website' => 'http://www.icelab.com.au',
										   'email' => 'hello@icelab.com.au'),
 						 'description' => 'Allows you to process payments using SecureTrading Payment Pages'
				 		);
		}
		
		public function uninstall()
		{
			# Remove tables
			$this->_Parent->Database->query("DROP TABLE `tbl_stpayments_logs`");
			
			# Remove preferences
      $this->_Parent->Configuration->remove('st-payments');
      $this->_Parent->saveConfig();
		}
		
		public function install()
		{
		  # Create tables
		  $this->_Parent->Database->query("
				CREATE TABLE IF NOT EXISTS `tbl_stpayments_logs` (
          `id` int(11) unsigned NOT NULL auto_increment,
          `address` varchar(255) NOT NULL,
          `currency` varchar(3) NOT NULL,
          `inputamount` decimal(10,2) NOT NULL,
          `name` varchar(255) NOT NULL,
          `orderref` varchar(255) NOT NULL,
          `postcode` varchar(255) NOT NULL,
          `stauthcode` varchar(255) NOT NULL,
          `streference` varchar(255) NOT NULL,
          `stresult` int(1) NOT NULL,
          `timestamp` datetime NOT NULL,
          `truncccnumber` int(4) NOT NULL,
					PRIMARY KEY (`id`)
				)
			");
			
		  return true;
		}
		
		public function getSubscribedDelegates() {
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
					'callback' => 'check_st_preferences'
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
			$group->appendChild(new XMLElement('legend', 'SecureTrading Payments'));

      # Add Site Reference field
			$label = Widget::Label('Site Reference');
			$label->appendChild(Widget::Input('settings[st-payments][site-reference]', General::Sanitize($this->_get_st_site_reference())));
			$group->appendChild($label);
			$group->appendChild(new XMLElement('p', 'Sign up at <a href="http://www.securetrading.com/applyhere.html">SecureTrading</a> to get your site reference key.', array('class' => 'help')));

      # Add Merchant Email field
			$label = Widget::Label('Merchant Email');
			$label->appendChild(Widget::Input('settings[st-payments][merchant-email]', General::Sanitize($this->_get_st_merchant_email())));
			$group->appendChild($label);
			$group->appendChild(new XMLElement('p', 'Make sure this matches the email associated with the site reference you entered above.', array('class' => 'help')));
			
			$context['wrapper']->appendChild($group);						
		}

  	/*-------------------------------------------------------------------------
  		Navigation
  	-------------------------------------------------------------------------*/
  	
  	#
  	#   TODO: Need to remove nav item if logging not enabled
  	#  	
		public function fetchNavigation()
		{
		  $nav = array();
		  $nav[] = array(
				'location'	=> 260,
				'name'		=> 'SecureTrading Payments',
				'children'	=> array(
					array(
						'name'		=> 'Transaction Logs',
						'link'		=> '/logs/',
						'limit'   => 'primary',
					)
				)
			);
      return $nav;
		}
		
  	/*-------------------------------------------------------------------------
  		Helpers
  	-------------------------------------------------------------------------*/

  	private function _get_st_site_reference()
		{
			return $this->_Parent->Configuration->get('site-reference', 'st-payments');
		}
		
		private function _get_st_merchant_email(){
			return $this->_Parent->Configuration->get('merchant-email', 'st-payments');
		}

		public function _count_logs()
		{
			return (integer)$this->_Parent->Database->fetchVar('total', 0, "
				SELECT
					COUNT(l.id) AS `total`
				FROM
					`tbl_stpayments_logs` AS l
			");
		}
		
		public function _get_logs_by_page($page, $per_page)
		{
			$start = ($page - 1) * $per_page;
			
			return $this->_Parent->Database->fetch("
				SELECT
					l.*
				FROM
					`tbl_stpayments_logs` AS l
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
					`tbl_stpayments_logs` AS l
				ORDER BY
					l.timestamp DESC
			");
		}
		
		public function _get_log($log_id) {
			return $this->_Parent->Database->fetchRow(0, "
				SELECT
					l.*
				FROM
					`tbl_stpayments_logs` AS l
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
		  $context['options'][] = array('st-payments', @in_array('st-payments', $context['selected']) ,'SecureTrading: Process payment');
		}
		
		public function add_filter_documentation_to_event($context)
		{
      if ( ! in_array('st-payments', $context['selected'])) return;

      $context['documentation'][] = new XMLElement('h3', 'SecureTrading Payments');
			$context['documentation'][] = new XMLElement('p', 'You can pass data to the SecureTrading server by mapping fields to any of the variables/fields listed in the <a href="http://www.securetrading.com/download/DOC_COM_ST-PAYMENT-PAGES-SETUP-GUIDE[1].pdf">ST Payment Pages Setup Guide</a>. The example below shows how you would map <code>amount</code>, <code>name</code> and <code>description</code> to their SecureTrading equivalents:');
			$code = '<input name="fields[amount]" type="text" />
<input name="fields[first-name]" type="text" />
<input name="fields[last-name]" type="text" />
<textarea name="fields[description]"></textarea>

<input name="st-payments[inputamount]" value="amount" type="hidden" />
<input name="st-payments[name]" value="first-name,last-name" type="hidden" />
<input name="st-payments[orderinfo]" value="description" type="hidden" />
      ';
			$context['documentation'][] = contentBlueprintsEvents::processDocumentationCode($code);
			$context['documentation'][] = new XMLElement('p', 'Note that the <code>id</code> of the newly created entry will be automatically passed to SecureTrading as the <code>orderref</code>. Multiple fields can be mapped by separating them with commas, they will be joined with a space. All field mappings are optional.');
	  }
	  
	  public function check_st_preferences($context)
	  {
      if ( ! in_array('st-payments', $context['event']->eParamFILTERS)) return;
	    
	    $merchant =       $this->_get_st_site_reference();
      $merchantemail =  $this->_get_st_merchant_email();
      
      if( ! isset($merchant) OR ! isset($merchantemail)){
				$context['messages'][] = array('st-payments', FALSE, 'Both site reference and merchant email need to be set.');
				return;
			}
	  }
	  
	  public function process_event_data($context)
	  {			
			# Check if in included filters
      if ( ! in_array('st-payments', $context['event']->eParamFILTERS)) return;
      
      # Set the default dataset
      $data = array(
        'orderref' =>       $context['entry']->get('id'),
        'merchant' =>       $this->_get_st_site_reference(),
        'merchantemail' =>  $this->_get_st_merchant_email()
      );
      
      $mapping = $_POST['st-payments'];
      
      if ( isset($mapping))
      {
        foreach ($mapping as $key => $val)
        {
          # Join multiple fields unless it's the `requiredfields` field
          if (strpos($val, ",") && $key != 'requiredfields') {
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
      
      $encoded_data = "";      
      foreach ($data as $key => $val)
      {
        $encoded_data[] = urlencode($key)."=".urlencode($val);
      }
      redirect('https://securetrading.net/authorize/form.cgi?' . implode("&",$encoded_data));
      return;
    }
	}

/* End of file: extension.driver.php */