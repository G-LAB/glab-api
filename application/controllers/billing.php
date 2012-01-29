<?php defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Billing Controller
 *
 * @package		CodeIgniter
 * @subpackage	Rest Server
 * @category	Controller
 * @author		Ryan Brodkin
*/

class Billing extends REST_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->model('billing_model','billing');
	}

	/**
	 * Get Gateways Array
	 */
	function gateways_get()
	{
		$r = $this->billing->getGateways($this->get('show_disabled'));

		$this->response($r, 200);
	}

	/**
	 * Get Invoice
	 */
	function invoice_get($ivid=false)
	{
		if (ctype_digit($ivid) !== true)
		{
			$this->response(array('error'=>'Invoice ID is a required parameter.'), 400);
		}

		$r = $this->billing->getInvoice($ivid);

		if (count($r) == 0)
		{
			$this->response(array('error'=>'Invoice could not be found.'), 404);
		}

		if ($this->get('extended') == true)
		{
			$r['items'] = $this->billing->getInvoiceItems($ivid);
			$r['payments'] = $this->billing->getInvoicePayments($ivid);
		}

		$this->response($r, 200);
	}

	/**
	 * Get Invoices Array
	 */
	function invoices_get()
	{
		if ($this->get('limit'))
		{
			$limit = $this->get('limit');
		}
		else
		{
			$limit = 20;
		}

		$r = $this->billing->getInvoices($limit, $this->get('offset'));

		if ($this->get('extended'))
		{
			foreach ($r as &$invoice)
			{
				$invoice['items'] = $this->billing->getInvoiceItems($invoice['ivid']);
				$invoice['payments'] = $this->billing->getInvoicePayments($invoice['ivid']);
			}
		}

		$this->response($r, 200);
	}

	/**
	 * Get Order
	 */
	function order_get($orid=false)
	{
		if (ctype_digit($orid) !== true)
		{
			$this->response(array('error'=>'Order ID is a required parameter.'), 400);
		}

		$r = $this->billing->getOrder($orid);

		if (count($r) == 0)
		{
			$this->response(array('error'=>'Order could not be found.'), 404);
		}

		if ($this->get('extended') == true)
		{
			$r['items'] = $this->billing->getOrderItems($orid);
			$r['invoices'] = $this->billing->getOrderInvoices($orid);
		}

		$this->response($r, 200);
	}

	/**
	 * Get Orders Array
	 */
	function orders_get()
	{
		if ($this->get('limit'))
		{
			$limit = $this->get('limit');
		}
		else
		{
			$limit = 20;
		}

		$r = $this->billing->getOrders($this->get('status'), $limit, $this->get('offset'));

		if ($this->get('extended'))
		{
			foreach ($r as &$order)
			{
				$order['items'] = $this->billing->getOrderItems($order['orid']);
				$order['invoices'] = $this->billing->getOrderInvoices($order['orid']);
			}
		}

		$this->response($r, 200);
	}

	/**
	 * Get Product at Current Version
	 * @param  int $sku
	 */
	function product_get($sku)
	{
		if (ctype_digit($sku) !== true)
		{
			$this->response(array('error'=>'Product SKU is a required parameter.'), 400);
		}

		$r = $this->billing->getProduct($sku);

		if (count($r) == 0)
		{
			$this->response(array('error'=>'Product could not be found.'), 404);
		}

		$this->response($r, 200);
	}

	/**
	 * Get Products Array
	 */
	function products_get()
	{
		if ($this->get('limit'))
		{
			$limit = $this->get('limit');
		}
		else
		{
			$limit = 20;
		}

		$r = $this->billing->getProduct($limit, $this->get('offset'));

		$this->response($r, 200);
	}
}