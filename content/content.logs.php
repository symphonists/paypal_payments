<?php

	require_once(TOOLKIT . '/class.administrationpage.php');
	
	class contentExtensionPaypal_paymentsLogs extends AdministrationPage {
		protected $_errors = array();
		protected $_action = '';
		protected $_status = '';
		protected $_driver = NULL;
		
		public function __construct()
		{
			parent::__construct();
			$this->_driver = Symphony::ExtensionManager()->create('paypal_payments');
		}
		
		public function __actionIndex()
		{
			$checked = @array_keys($_POST['items']);

			if (is_array($checked) and !empty($checked)) {
				switch ($_POST['with-selected']) {
					case 'delete':
						foreach ($checked as $log_id) {
							Symphony::Database()->query("
								DELETE FROM
									`tbl_paypalpayments_logs`
								WHERE
									`id` = {$log_id}
							");
						}

						redirect(SYMPHONY_URL . '/extension/paypal_payments/logs/');
						break;
				}
			}
		}
		
		public function __viewIndex()
		{		
			$this->setPageType('table');
			$this->setTitle('Symphony &ndash; PayPal Payment Transactions');
			$this->appendSubheading('Logs');
			$this->addStylesheetToHead(URL . '/extensions/paypal_payments/assets/logs.css', 'screen', 81);
			
			$per_page = 20;
			$page = (@(integer)$_GET['pg'] > 1 ? (integer)$_GET['pg'] : 1);
			$logs = $this->_driver->_get_logs_by_page($page, $per_page);
			$start = max(1, (($page - 1) * $per_page));
			$end = ($start == 1 ? $per_page : $start + count($logs));
			$total = $this->_driver->_count_logs();
			$pages = ceil($total / $per_page);
								
			$sectionManager = new SectionManager(Administration::instance());
			
			$entryManager = new EntryManager(Administration::instance());
			
			$th = array(
				array('Invoice/Entry', 'col'),
				array('Date', 'col'),
				array('Payment Type', 'col'),
				array('Payment Status', 'col'),
				array('Name', 'col'),
				array('Email', 'col'),
				array('Address', 'col'),
				array('Currency', 'col'),
				array('Tax', 'col'),
				array('Gross', 'col'),
				array('Fee', 'col'),
				array('Transaction Type', 'col'),
				array('Transaction ID', 'col'),
			);
						
			if ( ! is_array($logs) or empty($logs)) {
				$tb = array(
					Widget::TableRow(array(Widget::TableData(__('None Found.'), 'inactive', NULL, count($th))))
				);

			} else {
				foreach ($logs as $log)
				{
					$col = array();
					# Spit out $log_name vars
					extract($log, EXTR_PREFIX_ALL, 'log');
					$prefix = $this->_driver->_get_invoice_prefix();
					$log_invoice_to_search_on = $log_invoice;
					
					# If prefix exists, lets remove it to link to an entry.
					if (substr($log_invoice, 0, strlen($prefix)) == $prefix) {
						$log_invoice_to_search_on = substr($log_invoice, strlen($prefix));
					}
					
					# Get the entry/section data
					$entries = $entryManager->fetch($log_invoice_to_search_on, NULL, NULL, NULL, NULL, NULL, FALSE, TRUE);
					$entry = $entries[0];
					if (isset($entry))
					{
						$section_id = $entry->get('section_id');
						$section = $sectionManager->fetch($section_id);
						$column = array_shift($section->fetchFields());
						$data = $entry->getData($column->get('id'));
						# Build link to parent section
						$link = SYMPHONY_URL . '/publish/' . $section->get('handle') . '/edit/' . $entry->get('id') . '/';

						# Date
						$col[] = Widget::TableData( Widget::Anchor( General::sanitize($log_invoice), $link ) );
					} else {
						$col[] = Widget::TableData( General::sanitize($log_invoice) );
					}
					$col[0]->appendChild(Widget::Input("items[{$log_id}]", NULL, 'checkbox'));
					
					if ( ! empty($log_payment_date)) $col[] = Widget::TableData( DateTimeObj::get(__SYM_DATETIME_FORMAT__, strtotime($log_payment_date)) );
					else $col[] = Widget::TableData('None', 'inactive');
					
					if ( ! empty($log_payment_type)) $col[] = Widget::TableData(General::sanitize(ucwords($log_payment_type)));
					else $col[] = Widget::TableData('None', 'inactive');
					
					if ( ! empty($log_payment_status)) $col[] = Widget::TableData(General::sanitize($log_payment_status));
					else $col[] = Widget::TableData('None', 'inactive');
					
					if ( ! empty($log_first_name) && ! empty($log_last_name)) $col[] = Widget::TableData(General::sanitize($log_first_name) . " " . General::sanitize($log_last_name));
					else $col[] = Widget::TableData('None', 'inactive');					
					
					if ( ! empty($log_payer_email)) $col[] = Widget::TableData(General::sanitize($log_payer_email));
					else $col[] = Widget::TableData('None', 'inactive');
					
					if ( ! empty($log_address_street)) $col[] = Widget::TableData(General::sanitize($log_address_street));
 					else $col[] = Widget::TableData('None', 'inactive');
					
					if ( ! empty($log_mc_currency)) $col[] = Widget::TableData(General::sanitize($log_mc_currency));
 					else $col[] = Widget::TableData('None', 'inactive');
					
					if ( ! empty($log_tax)) $col[] = Widget::TableData(General::sanitize($log_tax));
 					else $col[] = Widget::TableData('None', 'inactive');
					
					if ( ! empty($log_mc_gross)) $col[] = Widget::TableData(General::sanitize($log_mc_gross));
 					else $col[] = Widget::TableData('None', 'inactive');
					
					if ( ! empty($log_mc_fee)) $col[] = Widget::TableData(General::sanitize($log_mc_fee));
 					else $col[] = Widget::TableData('None', 'inactive');
					
					if ( ! empty($log_txn_type)) $col[] = Widget::TableData(General::sanitize($log_txn_type));
 					else $col[] = Widget::TableData('None', 'inactive');
					
					if ( ! empty($log_txn_id)) $col[] = Widget::TableData(General::sanitize($log_txn_id));
 					else $col[] = Widget::TableData('None', 'inactive');
					
					$tr = Widget::TableRow($col);
					if ($log_payment_status == 'Denied') $tr->setAttribute('class', 'denied');
					$tb[] = $tr;
				}
			}

			$table = Widget::Table(
				Widget::TableHead($th), NULL, 
				Widget::TableBody($tb), 'selectable'
			);
			
			
			$this->Form->appendChild($table);

			$actions = new XMLElement('div');
			$actions->setAttribute('class', 'actions');

			$options = array(
				array(null, false, __('With Selected...')),
				array('delete', false, __('Delete'))
			);

			$actions->appendChild(Widget::Apply($options));
			$this->Form->appendChild($actions);
			# Pagination:
			if ($pages > 1) {
				$ul = new XMLElement('ul');
				$ul->setAttribute('class', 'page');
				
				## First
				$li = new XMLElement('li');				
				if ($page > 1) {
					$li->appendChild(
						Widget::Anchor('First', Administration::instance()->getCurrentPageURL() . '?pg=1')
					);					
				} else {
					$li->setValue('First');
				}				
				$ul->appendChild($li);
				
				## Previous
				$li = new XMLElement('li');				
				if ($page > 1) {
					$li->appendChild(
						Widget::Anchor('&larr; Previous', Administration::instance()->getCurrentPageURL(). '?pg=' . ($page - 1))
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
						Widget::Anchor('Next &rarr;', Administration::instance()->getCurrentPageURL(). '?pg=' . ($page + 1))
					);					
				} else {
					$li->setValue('Next &rarr;');
				}				
				$ul->appendChild($li);

				## Last
				$li = new XMLElement('li');				
				if ($page < $pages) {
					$li->appendChild(
						Widget::Anchor('Last', Administration::instance()->getCurrentPageURL(). '?pg=' . $pages)
					);					
				} else {
					$li->setValue('Last');
				}				
				$ul->appendChild($li);
				$this->Form->appendChild($ul);	
			}
		}
	}