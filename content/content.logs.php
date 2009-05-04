<?php

	require_once(TOOLKIT . '/class.administrationpage.php');
	
	class contentExtensionSecureTrading_PaymentsLogs extends AdministrationPage {
		protected $_errors = array();
		protected $_fields = array();
		protected $_action = '';
		protected $_status = '';
		protected $_template = 0;
		protected $_valid = false;
		protected $_editing = false;
		protected $_prepared = false;
		protected $_driver = null;
		protected $_conditions = array();
		
		public function __construct(&$parent)
		{
			parent::__construct($parent);
			$this->_driver = $this->_Parent->ExtensionManager->create('securetrading_payments');
		}
		
		public function __actionIndex()
		{
    	$checked = @array_keys($_POST['items']);

			if (is_array($checked) and !empty($checked)) {
				switch ($_POST['with-selected']) {
					case 'delete':
						foreach ($checked as $log_id) {
							$this->_Parent->Database->query("
								DELETE FROM
									`tbl_stpayments_logs`
								WHERE
									`id` = {$log_id}
							");
						}

						redirect(URL . '/symphony/extension/securetrading_payments/logs/');
						break;
				}
			}
		}
		
		public function __viewIndex()
		{	  
			$this->setPageType('table');
			$this->setTitle('Symphony &ndash; SecureTrading Payments: Transaction Logs');
			$this->appendSubheading('Logs');
			
			$per_page = 20;
			$page = (@(integer)$_GET['pg'] > 1 ? (integer)$_GET['pg'] : 1);
			$logs = $this->_driver->_get_logs_by_page($page, $per_page);
			$start = max(1, (($page - 1) * $per_page));
			$end = ($start == 1 ? $per_page : $start + count($logs));
			$total = $this->_driver->_count_logs();
			$pages = ceil($total / $per_page);
								
			$sectionManager = new SectionManager($this->_Parent);
			$entryManager = new EntryManager($this->_Parent);
							
			$tableHead = array(
				array('Date', 'col'),
				array('Name', 'col'),
				array('Address', 'col'),
				array('Postcode', 'col'),
				array('Amount', 'col'),
				array('ST Reference', 'col'),
				array('Card No.', 'col'),
				array('Result', 'col'),
			);
						
			if ( ! is_array($logs) or empty($logs)) {
				$tableBody = array(
					Widget::TableRow(array(Widget::TableData(__('None Found.'), 'inactive', null, count($tableHead))))
				);

			} else {
				foreach ($logs as $log)
				{
				  # Spit out $log_name vars
					extract($log, EXTR_PREFIX_ALL, 'log');
          
          # Get the entry/section data
          $entries = $entryManager->fetch($log_orderref, null, null, null, null, null, false, true);
          $entry = $entries[0];
          if (isset($entry))
          {
            $section_id = $entry->_fields['section_id'];
  					$section = $sectionManager->fetch($section_id);
            $column = array_shift($section->fetchFields());
            $data = $entry->getData($column->get('id'));
            # Build link to parent section
            $link = URL . '/symphony/publish/' . $section->get('handle') . '/edit/' . $entry->get('id') . '/';

            # Date
  					$col_date = Widget::TableData(
  						Widget::Anchor(
  							DateTimeObj::get(__SYM_DATETIME_FORMAT__, strtotime($log_timestamp)), $link)
  					);
          }
          else
          {
            $col_date = Widget::TableData(
  							DateTimeObj::get(__SYM_DATETIME_FORMAT__, strtotime($log_timestamp))
  					);
          }
					$col_date->appendChild(Widget::Input("items[{$log_id}]", null, 'checkbox'));     

          # Name
          if ( ! empty($log_name)) $col_name = Widget::TableData(General::sanitize($log_name));
				  else $col_name = Widget::TableData('None', 'inactive');
				  
				  # Address
          if ( ! empty($log_address)) $col_address = Widget::TableData(General::sanitize($log_address));
				  else $col_address = Widget::TableData('None', 'inactive');
				  
				  # Postcode
          if ( ! empty($log_postcode)) $col_postcode = Widget::TableData(General::sanitize($log_postcode));
				  else $col_postcode = Widget::TableData('None', 'inactive');

				  # Amount
          if ( ! empty($log_inputamount)) $col_amount = Widget::TableData(General::sanitize($log_inputamount));
				  else $col_amount = Widget::TableData('None', 'inactive');				  
				  
				  # Reference
          if ( ! empty($log_streference)) $col_reference = Widget::TableData(General::sanitize($log_streference));
				  else $col_reference = Widget::TableData('None', 'inactive');				
				  
				  # Card No.
          if ( ! empty($log_truncccnumber)) $col_card_no = Widget::TableData(General::sanitize($log_truncccnumber));
 				  else $col_card_no = Widget::TableData('None', 'inactive');
				  
				  # Result
          if ( ! empty($log_stresult))
          {
            $result_message = ($log_stresult == 1) ? 'Success' : 'Fail';
            $col_result = Widget::TableData($result_message);
          } 
				  else $col_result = Widget::TableData('None', 'inactive');

					$tableBody[] = Widget::TableRow(
						array(
							$col_date,
							$col_name,
							$col_address,
              $col_postcode,
              $col_amount,
              $col_reference,
              $col_card_no,
              $col_result
						)
					);
				}
			}

			$table = Widget::Table(
				Widget::TableHead($tableHead), null, 
				Widget::TableBody($tableBody)
			);
			
			$this->Form->appendChild($table);
			
			$actions = new XMLElement('div');
			$actions->setAttribute('class', 'actions');
			
			$options = array(
				array(null, false, 'With Selected...'),
				array('delete', false, 'Delete')									
			);

			$actions->appendChild(Widget::Select('with-selected', $options));
			$actions->appendChild(Widget::Input('action[apply]', 'Apply', 'submit'));
			
			$this->Form->appendChild($actions);
			
			# Pagination:
			if ($pages > 1) {
				$ul = new XMLElement('ul');
				$ul->setAttribute('class', 'page');
				
				## First
				$li = new XMLElement('li');				
				if ($page > 1) {
					$li->appendChild(
						Widget::Anchor('First', $this->_Parent->getCurrentPageURL() . '?pg=1')
					);					
				} else {
					$li->setValue('First');
				}				
				$ul->appendChild($li);
				
				## Previous
				$li = new XMLElement('li');				
				if ($page > 1) {
					$li->appendChild(
						Widget::Anchor('&larr; Previous', $this->_Parent->getCurrentPageURL(). '?pg=' . ($page - 1))
					);					
				} else {
					$li->setValue('&larr; Previous');
				}				
				$ul->appendChild($li);

				## Summary
				$li = new XMLElement('li', 'Page ' . $page . ' of ' . max($page, $pages));				
				$li->setAttribute('title', 'Viewing ' . $start . ' - ' . $end . ' of ' . $total . ' entries');				
				$ul->appendChild($li);

				## Next
				$li = new XMLElement('li');				
				if ($page < $pages) {
					$li->appendChild(
						Widget::Anchor('Next &rarr;', $this->_Parent->getCurrentPageURL(). '?pg=' . ($page + 1))
					);					
				} else {
					$li->setValue('Next &rarr;');
				}				
				$ul->appendChild($li);

				## Last
				$li = new XMLElement('li');				
				if ($page < $pages) {
					$li->appendChild(
						Widget::Anchor('Last', $this->_Parent->getCurrentPageURL(). '?pg=' . $pages)
					);					
				} else {
					$li->setValue('Last');
				}				
				$ul->appendChild($li);
				$this->Form->appendChild($ul);	
			}
		}
	}
	
?>