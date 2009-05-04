<?php


	Final Class eventSecuretrading_payments_callback extends Event{

		public static function about(){
					
			return array(
						 'name' => 'SecureTrading Payments: Save callback data',
						 'author' => array('name' => 'Max Wheeler',
										   'website' => 'http://www.icelab.com.au',
										   'email' => 'max@icelab.com.au'),
						 'version' => '0.1',
						 'release-date' => '2009-02-24',						 
					);						 
		}
		
		public function __construct(&$parent)
		{
			parent::__construct($parent);
			$this->_driver = $this->_Parent->ExtensionManager->create('securetrading_payments');
		}
		
		public function load()
		{
		  # Check that the POST data contains the `streference` value
			if (is_array($_POST) &&  ! empty($_POST) && isset($_POST['streference'])) return $this->__trigger();
			return NULL;
		}

		public static function documentation()
		{
		  $docs = array();
			$docs[] = '
<p>This event is used to deal with data returned by the SecureTrading callback. It does the following:</p>
<ol>
  <li>Saves the transaction details to <a href="' . URL . '/symphony/extension/securetrading_payments/logs/">the log</a>.</li>
  <li>Reconciles the SecureTrading data with matching fields in the originating entry.</li>
  <li>Outputs the SecureTrading data as XML.</li>
</ol>
<p>For the event to work you’ll need to make sure the <code>callback.txt</code> and <code>callback-f.txt</code> files on the SecureTrading server point to the page that has this event attached. The method specified should be POST. You can attach the event to multiple pages for different outputs if you wish.</p>
<h3>Transaction Logs</h3>
<p>The transaction logs store the following data from SecureTrading:</p>
<ul>
  <li><code>address</code></li>
  <li><code>currency</code></li>
  <li><code>inputamount</code></li>
  <li><code>name</code></li>
  <li><code>orderref</code></li>
  <li><code>postcode</code></li>
  <li><code>stauthcode</code></li>
  <li><code>streference</code></li>
  <li><code>timestamp</code></li>
  <li><code>truncccnumber</code></li>
</ul>
<p>These fields <em>must</em> be included in set of variables returned by the <code>callback.txt</code> and <code>callback-f.txt</code> files on the SecureTrading server. Any other valid SecureTrading variables are ignored by the logs, though they are still able to be used for reconciling data in the originating entry or in the XML ouput.</p>

<h3>Reconciling Data</h3>
<p>To save any of the SecureTrading data to the originating entry, you simply need to include a field in that section with the <em>exact</em> name of the SecureTrading field. The event will process any valid SecureTrading fields and update the matching named fields in the entry. A list of valid fields is available in the <a href="http://www.securetrading.com/download/DOC_COM_ST-PAYMENT-PAGES-SETUP-GUIDE[1].pdf">ST Payment Pages Setup Guide</a>.</p>
<p>All variable are dumped unprocessed into their matching fields, so you’ll need to make sure the field types in the section match the data in the response from SecureTrading. The only exception to this is the <code>stresult</code> variable, which returns "success" or "fail" in place of the SecureTrading standard 1 or 2 code.</p>

<h3>XML Output</h3>
<p>All data returned from SecureTrading is included as the <code>&lt;st-payments-callback&gt;</code> node in the XML output for use in frontend pages.</p>
';
			return implode("\n", $docs);
		}
		
		protected function __trigger()
		{			
      # Array of valid variables from Secure Trading
      $valid_st_variables = array(
        "address",                        "amount",
        "ccissue",                        "ccnumber",
        "cctype",                         "comments",
        "company",                        "country",
        "county",                         "currdate",
        "currday",                        "currency",
        "currtime",                       "email",
        "fax",                            "formattedamount",
        "formatteddate",                  "inputamount",
        "merchant",                       "month",
        "name",                           "orderref",
        "orderinfo",                      "path",
        "postcode",                       "random8",
        "random16",                       "random32",
        "settlementday",                  "securitymessage",
        "securityresponseaddress",        "securityresponsepostcode",
        "securityresponsesecuritycode",   "startmonth",
        "startyear",                      "stauthcode",
        "stconfidence",                   "streference",
        "stresult",                       "st_emailencoding",
        "telephone",                      "timestamp",
        "town",                           "truncccnumber",
        "url",                            "usadate",
        "year"
      );
      
      $required_st_variables = array(
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

      # Find any matches in the $_POST data
      $matches = array();
      foreach ($_POST as $key => $val)
      {
        if (in_array($key, $valid_st_variables)) $matches[$key] = $val;
      }

      # Output the matches in XML
      $result = new XMLElement('st-payments-callback');
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
          else if (in_array($key, $required_st_variables))
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
    			  if ($label == "stresult")
    			  {
    			    $value = ($log[$label] == 1) ? "Success" : "Fail";
    			    $entry->setData($field['id'], array(
    			        'handle'  => Lang::createHandle($value),
    			        'value'   => $value
    			      )
    			    );
    			  }
    			  # Check if entry fields match values returned from SecureTrading
    			  else if (in_array($label, $valid_st_variables))
    			  {
    			    $value = $log[$label];
    			    $entry->setData($field['id'], array(
    			        'handle'  => Lang::createHandle($value),
    			        'value'   => $value
    			      )
    			    );
    			  }
      			# Transfom and move out
      			$entry->commit();
    			}
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