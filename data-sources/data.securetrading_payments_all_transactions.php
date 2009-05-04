<?php

	require_once(TOOLKIT . '/class.datasource.php');
	
	Class datasourceSecuretrading_payments_all_transactions extends Datasource
	{
		
		public $dsParamROOTELEMENT = 'st-payments-log';
		
		public function about()
		{
			return array(
					 'name' => 'Secure Trading Payments: All transactions',
					 'author' => array(
							'name' => 'Max Wheeler',
							'website' => 'http://icelab.com.au',
							'email' => 'max@icelab.com.au'),
					 'description' => 'Outputs all transactions from the SecureTrading Payments log.',
					 'version' => '0.1',
					 'release-date' => '2009-02-25');	
		}

		public function __construct(&$parent, $env=NULL, $process_params=true)
		{
			parent::__construct($parent, $env, $process_params);
			$this->_driver = $this->_Parent->ExtensionManager->create('securetrading_payments');
		}

		public function grab(&$param_pool)
		{
      $xml = new XMLElement($this->dsParamROOTELEMENT);
      $count = 0;
      $order_references = array();
      
      # Get the data
			$logs = $this->_driver->_get_logs();
      foreach ($logs as $log)
      {
        $node = new XMLElement("entry", NULL, array(
            'id' => $log['id'],
            'result' => ($log['stresult'] == 1) ? 'success' : 'fail',
            'order-reference' => $log['orderref']
          )
        );
        
        $node->appendChild(new XMLElement("name", $log['name']));
        $node->appendChild(new XMLElement("address", $log['address'], array(
            'postcode' => $log['postcode']
          )
        ));
        $node->appendChild(new XMLElement("input-amount", $log['inputamount'], array(
            'currency' => $log['currency']
          )
        ));
        $node->appendChild(new XMLElement("st-reference", $log['streference']));
        $node->appendChild(new XMLElement("st-authcode", $log['stauthcode']));
        # Separate date and time
        $timestamp = strtotime($log['timestamp']);
        $node->appendChild(new XMLElement("date", date("Y-n-d", $timestamp), array(
            'time' => date("G:i", $timestamp),
            'weekday' => date("w", $timestamp)
          )
        ));
        $node->appendChild(new XMLElement("card-no", $log['truncccnumber']));
        
        # Add orderref for $param output
        array_push($order_references, $log['orderref']);
        $xml->appendChild($node);
        $count++;
      }
      ###
      #  TODO: Figure out how to make $param usable by other datasources, not really necessary but would be nice
      #
      #  $param_pool['st-payment-orderrefs'] = $order_references;
      return ($count == 0 ? NULL : $xml);
		}
	}
