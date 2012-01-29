<?php defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Controller_Name Controller
 *
 * @package		CodeIgniter
 * @subpackage	Rest Server
 * @category	Controller
 * @author		Ryan Brodkin
*/

class Controller_Name extends REST_Controller
{
	function __construct()
	{
		parent::__construct();
	}

	function method_get()
	{
		$this->response(null, 200);
	}
}