<?php defined('BASEPATH') OR exit('No direct script access allowed');

require APPPATH.'/libraries/REST_Controller.php';

/**
 * Hosting Controller
 *
 * @package		CodeIgniter
 * @subpackage	Rest Server
 * @category	Controller
 * @author		Ryan Brodkin
*/

class Hosting extends REST_Controller
{
	function __construct()
	{
		parent::__construct();

		$this->load->model('hosting_model','hosting');
	}

	/**
	 * Get a Subscription
	 */
	function subscription_get()
	{

	}

	/**
	 * Get Subscription Matching a Domain
	 */
	function domain_get($domain)
	{

	}
}