<?php defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Domain Registration Controller
 *
 * @package		CodeIgniter
 * @subpackage	Rest Server
 * @category	Controller
 * @author		Ryan Brodkin
*/

class Domain extends REST_Controller
{
	function __construct()
	{
		parent::__construct();

		$this->load->model('domain_model','domain');
	}

	/**
	 * Get a Domain Registration
	 */
	function registration_get($domain)
	{
		$data = $this->domain->get($domain);

		if (is_array($data) === true)
		{
			$this->response($data, 200);
		}
		else
		{
			$this->response(array('error'=>'Domain not found.'), 404);
		}
	}

	/**
	 * Get Array of Domain Registrations
	 */
	function registrations_get()
	{
		if (ctype_digit($this->get('limit')) === true)
		{
			$limit = $this->get('limit');
		}
		else
		{
			$limit = 50;
		}

		$data = $this->domain->get_list($limit,$this->get('offset'));

		if (is_array($data) === true)
		{
			$this->response($data, 200);
		}
		else
		{
			$this->response(array('error'=>'Error retrieving data from provider.'), 400);
		}
	}
}