<?php defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Brand Controller
 *
 * @package		CodeIgniter
 * @subpackage	Rest Server
 * @category	Controller
 * @author		Ryan Brodkin
*/

class Brand extends REST_Controller
{
	function __construct()
	{
		parent::__construct();

		$this->load->model('brand_model','brand');
	}

	function identity_get($brid=false)
	{
		if (ctype_digit($brid) !== true)
		{
			$this->response(array('error'=>'Brand Identity ID is required.'), 400);
		}

		$r = $this->brand->get_identity($brid);

		if (count($r) > 0)
		{
			$this->response($r, 200);
		}
		else
		{
			$this->response(array('error'=>'Brand not found.'), 404);
		}
	}

	function identities_get()
	{
		// Set Limit
		if (ctype_digit($this->get('limit')) === true)
		{
			$limit = $this->get('limit');
		}
		else
		{
			$limit = 10;
		}

		$r = $this->brand->get_identities($limit,$this->get('offset'));

		$this->response($r, 200);
	}
}