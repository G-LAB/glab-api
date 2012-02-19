<?php

class Billing_model extends CI_Model {

	function __construct () {
		parent::__construct();
		$this->load->helper(array('array','glib_array'));
		$this->load->model('accounting_model','accounting');
	}

	/**
	 * Get Total Count of Batch Errors
	 * @return int
	 */
	function getBatchErrorCount() {
		return array_sum($this->getBatchErrorCountsByGateway());
	}

	/**
	 * Get Count of Batch Errors Separated by Gateway
	 * @return array
	 */
	function getBatchErrorCountsByGateway () {

		$counts = array();

		foreach ($this->getGatewayInstances() as $pgid=>$instance) {
			if (method_exists($instance, 'get_batch_errors')) $counts[$pgid] = count($instance->get_batch_errors());
		}

		return $counts;
	}

	/**
	 * Get Batch Errors Array Separated by Gateway
	 * @return array
	 */
	function getBatchErrorsByGateway () {

		$errors = array();

		foreach ($this->getGatewayInstances() as $pgid=>$instance) {
			if (method_exists($instance, 'get_batch_errors')) $errors[$pgid] = $instance->get_batch_errors();
		}

		return $errors;
	}

	/**
	 * Get Batch Errors Array for a Gateway
	 * @param  int $pgid Gateway ID
	 * @return array
	 */
	function getBatchErrors ($pgid) {
		return $this->getGatewayInstance($pgid)->get_batch_errors();
	}

	/**
	 * Get Gateway
	 * @param  int $pgid Gateway ID
	 * @return array
	 */
	function getGateway ($pgid) {
		return $this->db->get_where('billing_paygateway', array('pgid'=>$pgid))->row_array();
	}

	/**
	 * Get Gateways Array
	 * @param  bool $show_disabled=false
	 * @return array
	 */
	function getGateways ($show_disabled=false) {

		// Hide Disabled Gateways
		if (!$show_disabled) $this->db->where('status', '1');

		$results = $this->db->get('billing_paygateway')->result_array();

		foreach ($results as $row) {
			$data[$row['pgid']] = $row;
		}

		return $data;
	}

	/**
	 * Instanciate Gateway
	 * @param  int/array $gateway Gateway ID (pgid) or DB Row of Gateway Instance
	 * @return class
	 */
	function getGatewayInstance ($gateway) {

		if (is_numeric($gateway)) $data = $this->getGateway($gateway);
		elseif (is_array($gateway)) $data = $gateway;
		else exit('Method getGatewayInstance() only accepts pgid or array.');

		if (isset($data, $data['library'])) {
			$class = 'Gateway'.ucwords($data['library']);

			if (file_exists(APPPATH.'/libraries/'.$class.'.php')) {
				require_once APPPATH.'/libraries/'.$class.'.php';
				$instance = new $class ($data);
				return $instance;

			} else {
				log_message('debug', 'Could not locate '.$class.' class.');
			}

		} else {
			show_error('No such gateway.');
		}
	}

	/**
	 * Instanciate Each Gateway
	 * @param  bool $show_disabled=true
	 * @return array
	 */
	function getGatewayInstances ($show_disabled=true) {
		$data = $this->getGateways($show_disabled);

		$instances = array();
		foreach ($data as $pgid=>$gateway) {
			$instances[$pgid] = $this->getGatewayInstance($gateway);
		}

		return $instances;
	}

	/**
	 * Get Invoices Array
	 * @param  int $limit
	 * @param  int $offset
	 * @return array
	 */
	function getInvoices($limit, $offset) {
		$this->db->select("*, (subtotal + tax) as total, (SELECT COUNT(*) FROM billing_invoices_items WHERE ivid = billing_invoices.ivid) as countItems",FALSE);
		$this->db->limit($limit, $offset);
		$query = $this->db->get('billing_invoices');
		return $query->result_array();
	}

	/**
	 * Get Invoice
	 * @param  int $ivid Invoice ID
	 * @return array
	 */
	function getInvoice($ivid) {
		$this->db->select('*, (subtotal + tax) as total',FALSE);
		$query = $this->db->get_where('billing_invoices',array('ivid' => $ivid),1);
		return $query->row_array();
	}

	/**
	 * Get Items in Invoice
	 * @param  int $ivid Invoice ID
	 * @return array
	 */
	function getInvoiceItems($ivid) {
		$invoice = $this->getInvoice($ivid);
		$items_order = $this->getOrderItems($invoice['orid']);
		$items_invoice = null;

		foreach ($this->getInvoiceIvimids($ivid) as $ivimid=>$orimid) $items_invoice[$ivimid] = element($orimid,$items_order);

		return $items_invoice;
	}

	/**
	 * Get Item IDs in Invoice
	 * @param  int $ivid Invoice ID
	 * @return array
	 */
	function getInvoiceIvimids ($ivid) {
		$this->db->select('ivimid, orimid');
		$q = $this->db->get_where('billing_invoices_items','ivid = '.$ivid);
		return array_flatten($q->result_array(),'ivimid','orimid');
	}

	/**
	 * Get Payments on Invoice
	 * @param  int $ivid Invoice ID
	 * @return array
	 */
	function getInvoicePayments($ivid) {
		$this->db->join('billing_payaccts a','a.pactid = p.pactid');
		$this->db->join('billing_paygateway g','g.pgid = a.pgid');
		$query = $this->db->get_where('billing_payments p',array('p.ivid' => $ivid));
		return $query->result_array();
	}

	/**
	 * Get Products Array
	 * @param  int $limit
	 * @param  int $offset
	 * @return array
	 */
	function getProducts($limit, $offset) {
		$this->db->order_by('s.sku','DESC');
		$this->db->join('(SELECT * FROM billing_products_versions ORDER BY skuvid DESC) v','s.sku=v.sku','left',FALSE);
		$this->db->group_by('s.sku');
		$this->db->limit($limit, $offset);
		$query = $this->db->get('billing_products_skus s');
		return $query->result_array();
	}

	/**
	 * Get Product at Current Version
	 * @param  int $sku
	 * @return array
	 */
	function getProduct($sku) {
		$this->db->order_by('s.sku','DESC');
		$this->db->join('(SELECT * FROM billing_products_versions ORDER BY skuvid DESC) v','s.sku=v.sku','left',FALSE);
		$this->db->group_by('s.sku');
		$this->db->where('s.sku',$sku);
		$query = $this->db->get('billing_products_skus s');
		return $query->row_array();
	}

	/**
	 * Get Orders Array
	 * @param  string $status=false Filter by status
	 * @param  int $limit
	 * @param  int $offset
	 * @return array
	 */
	function getOrders($status=false, $limit, $offset) {

		if ($status) $this->db->where('status',$status);
		else $this->db->where_not_in('status', array('cancelled', 'complete'));

		$this->db->limit($limit, $offset);

		$query = $this->db->get('billing_orders');

		return $query->result_array();
	}

	/**
	 * Get Order
	 * @param  int $orid Order ID
	 * @return array
	 */
	function getOrder($orid) {
		$query = $this->db->get_where('billing_orders o',array('orid' => $orid),1);
		return $query->row_array();
	}

	/**
	 * Get Subtotal of Order
	 * @param  int $orid Order ID
	 * @return float
	 */
	function getOrderSubtotal ($orid) {
		$query = "	SELECT SUM(extended) as subtotal FROM (SELECT (IFNULL(orderPrice, price)*orderQty) as extended
					FROM (billing_orders_items i)
					LEFT JOIN billing_invoices_items iv ON i.orimid = iv.orimid
					LEFT JOIN billing_products_versions p ON i.skuvid = p.skuvid
					WHERE i.orid = $orid
					GROUP BY i.orimid) AS data
				";

		$q = $this->db->query($query);
		$r = $q->row_array();

		return $r['subtotal'];
	}

	/**
	 * Get Items in Order
	 * @param  int $orid Order ID
	 * @return array
	 */
	function getOrderItems($orid) {

		$this->db->select('i.*, (IFNULL(orderPrice,price)) as currentPrice, (IFNULL(orderPrice,price)*orderQty) as extended, p.*, iv.ivid',FALSE);
		$this->db->join('billing_invoices_items iv','i.orimid = iv.orimid','left');
		$this->db->join('billing_products_versions p','i.skuvid = p.skuvid','left');
		$query = $this->db->get_where('billing_orders_items i',array('orid' => $orid));

		$data = array();
		foreach ($query->result_array() as $item) $data[element('orimid',$item)] = $item;

		return $data;

	}

	/**
	 * Get Product Version ID by SKU
	 * @param  int $sku
	 * @return int
	 */
	function getSkuvidBySku($sku) {
		$this->db->limit(1);
		$this->db->order_by('skuvid','DESC');
		$q = $this->db->get_where('billing_products_versions','sku = '.$sku);
		$r = $q->row_array();
		return $r['skuvid'];
	}

	/**
	 * Get Invoices for Order
	 * @param  int $orid Order ID
	 * @return array
	 */
	function getOrderInvoices($orid) {

		$this->db->select("*, (subtotal + tax) as total, (SELECT COUNT(*) FROM billing_invoices_items WHERE ivid = billing_invoices.ivid) as countItems",FALSE);
		$query = $this->db->get_where('billing_invoices',array('orid' => $orid));
		return $query->result_array();

	}

	/**
	 * Get Tax Rate for State as Percentage
	 * @param  string $state Two-letter state abbreviation
	 * @return float
	 */
	function getTaxRate ($state) {
		if (!$state) return FALSE;

		$this->db->select('rate');
		$q = $this->db->get_where('billing_tax_zone',array('state'=>$state),1);
		$r = $q->row_array();
		return $r['rate'];
	}

	/**
	 * Get Tax Rate for State as Multiplier
	 * @param  string $state Two-letter state abbreviation
	 * @return float
	 */
	function getTaxMultiplier ($state) {
		return $this->getTaxRate($state)*.01;
	}

	/**
	 * Update Status of Product
	 * @param  int $sku
	 * @param  string $status
	 * @return null
	 */
	function updateProductStatus($sku, $status) {
		$id = ltrim($sku, 0);

		$this->db->where('sku', $id);
		$data['status'] = $status;
		$this->db->update('billing_products', $data);
	}

	/**
	 * Update Invoice with New Data
	 * @param  int $ivid Invoice ID
	 * @return null
	 */
	private function updateInvoice ($ivid) {
		$invoice = $this->getInvoice($ivid);
		$order = $this->getOrder($invoice['orid']);
		$addr = $this->entity->getAddress($order['addrid']); // @todo pull from profile data
		$taxMult = $this->getTaxMultiplier($addr['state']);

		$subtotal = 0;
		$tax = 0;

		foreach ($this->getInvoiceItems($ivid) as $item) {
			$subtotal = $subtotal+$item['extended'];
			if ($item['isTaxable']) $tax = $tax+($item['extended']*$taxMult);
		}

		$data = array (
			'subtotal' => $subtotal,
			'tax' => $tax
		);

		$this->db->update('billing_invoices',$data,'ivid = '.$ivid);
	}

	/**
	 * Update Order Address
	 * @param  int $orid   Order ID
	 * @param  int $addrid Address ID
	 * @return null
	 */
	function updateOrderAddress ($orid,$addrid) {
		$this->db->set('addrid',$addrid);
		$this->db->where('orid',$orid);
		$this->db->update('billing_orders');
	}

	/**
	 * Update Order Status
	 * @param  int $orid   Order ID
	 * @param  string $status
	 * @return null
	 */
	function updateOrderStatus ($orid,$status) {
		$this->db->set('status',$status);
		$this->db->where('orid',$orid);
		$this->db->update('billing_orders');
	}

	/**
	 * Insert Order
	 * @param int $pid Profile ID of Owner
	 */
	function addOrder ($pid) {

		$data['pid'] = $pid;
		$data['event'] = null; // @todo

		$this->db->insert('billing_orders',$data);
		return $this->db->insert_id();
	}

	/**
	 * Insert Item into Order
	 * @param int $orid Order ID
	 * @param int $sku  Item SKU
	 */
	function addOrderItem ($orid,$sku) {
		$skuvid = $this->getSkuvidBySku($sku);
		if ($skuvid) $this->db->insert('billing_orders_items',array('orid'=>$orid,'skuvid'=>$skuvid));
	}

	/**
	 * Generate Invoice for Order
	 * @param int $orid Order ID
	 */
	function addOrderInvoice ($orid) {
		$items_all = $this->getOrderItems($orid);

		// Process Items Never Invoiced
		$items_invoice = null;
		foreach ($items_all as $orimid=>$item) if (!$item['ivid']) $items_invoice[] = $orimid;

		if (count($items_invoice)) {
			$this->db->trans_start();
			$ivid = $this->addInvoice($orid);
			if ($ivid) {
				foreach ($items_invoice as $orimid) {
					$ivimid = $this->addInvoiceItem($ivid,$orimid);
					$item = $items_all[$orimid];
					if ($ivimid) {
						// Revenue
						if ($item['extended'] != 0) $this->accounting->addLedgerEntry($item['extended'],3,$item['revenueAcid'],null,$ivimid);

						// Expense
						if ($item['cost'] != 0) $this->accounting->addLedgerEntry($item['cost']*$item['orderQty'],$item['costAcidDr'],$item['costAcidCr'],null,$ivimid);
					}
				}
				$this->updateInvoice($ivid);
				$this->updateOrderStatus($orid,4);
			}
			$this->db->trans_complete();
		}
	}

	/**
	 * Insert Empty Invoice for Order
	 * @param int $orid Order ID
	 */
	private function addInvoice ($orid) {
		$invoice = array (
			'orid' => $orid,
			'event' => null // @todo
		);
		$this->db->insert('billing_invoices',$invoice);
		return $this->db->insert_id();
	}

	/**
	 * Insert Item into Invoice
	 * @param int $ivid   Invoice ID
	 * @param int $orimid Order Item ID
	 */
	private function addInvoiceItem ($ivid,$orimid) {
		$item = array (
			'ivid' => $ivid,
			'orimid' => $orimid
		);
		$this->db->insert('billing_invoices_items',$item);
		return $this->db->insert_id();
	}

	/**
	 * Insert Product
	 * @param array $data
	 */
	function addProduct($data) {
		$this->db->insert('billing_products', $data);
		return $this->db->insert_id();
	}

	/**
	 * Update Item in Order
	 * @param  int $orimid          Order Item ID
	 * @param  float $orderQty=null   Quantity
	 * @param  float $orderPrice=null Price
	 * @return null
	 */
	function updateOrderItem ($orimid,$orderQty=null,$orderPrice=null) {

		if (is_null($orderQty) && is_null($orderPrice)) return;

		$data = array();

		if ($orderQty) $this->db->set('orderQty',$orderQty);

		if ($orderPrice) $this->db->set('orderPrice',$orderPrice);
		if ($orderPrice === FALSE) $this->db->set('orderPrice',null);

		$this->db->where('orimid',$orimid);
		$this->db->update('billing_orders_items');
	}

	/**
	 * Line Void Order Item
	 * @param  array $data=array() Order Item IDs in Array
	 * @return null
	 */
	function voidOrderItems ($data=array()) {

		if (!is_array($data)) $data = array($data);

		foreach ($data as $item) $this->db->or_where("orimid","$item");

		$this->db->delete('billing_orders_items');
	}
}

// End of File