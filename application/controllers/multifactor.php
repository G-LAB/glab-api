<?php defined('BASEPATH') OR exit('No direct script access allowed');

require APPPATH.'/libraries/REST_Controller.php';

/**
 * Auth Controller
 *
 * @package		CodeIgniter
 * @subpackage	Rest Server
 * @category	Controller
 * @author		Ryan Brodkin
*/

class Multifactor extends REST_Controller
{
	/**
	 * Get Profile Base Data
	 */
	function yubikey_get()
	{
		$q = $this->db->select('*')->limit(1);

		if (ctype_digit($this->get('pid')) === true)
		{
			$q->where('pid', $this->get('pid'));
		}
		elseif (ctype_alnum($this->get('prefix')) === true)
		{
			$q->where('ykid', $this->get('prefix'));
		}
		else
		{
			$this->response(array('error'=>lang('error_bad_format')), 400);
		}

		$r = $q->get('auth_mf_yubikey');

		if ($r->num_rows() == 0)
		{
			$this->response(array('error'=>lang('error_no_results')), 404);
		}
		else
		{
			$this->response($r->row(), 200);
		}
	}
}