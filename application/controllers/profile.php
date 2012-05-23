<?php defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Profile Controller
 *
 * The methods in this controller should not be accessed directly.
 * Utilize profile model instead.
 *
 * @package		CodeIgniter
 * @subpackage	Rest Server
 * @category	Controller
 * @author		Ryan Brodkin
*/

class Profile extends REST_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->model('profile_model');
	}

	/**
	 * Get Profile Addresses
	 */
	function addresses_get()
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

		$r = $this->profile_model->get_addresses($this->get('pid'), $limit, $this->get('offset'));

		$this->response($r, 200);
	}

	/**
	 * Get Profile Base Data
	 */
	function base_get()
	{
		$r = $this->profile_model->get_base($this->get('pid'));

		if (count($r) > 0)
		{
			$this->response($r, 200);
		}
		else
		{
			$this->response(array('error'=>'Profile "'.$this->get('pid').'" not found.'), 404);
		}
	}

	/**
	 * Get Profile Delegates
	 */
	function delegates_get()
	{
		// Set Limit
		if (ctype_digit($this->get('limit')) === true)
		{
			$limit = $this->get('limit');
		}
		else
		{
			$limit = 50;
		}

		$r = $this->profile_model->get_delegates($this->get('pid'), $limit, $this->get('offset'));

		$this->response($r, 200);
	}

	/**
	 * Get Profile Emails
	 */
	function emails_get()
	{
		// Set Limit
		if (ctype_digit($this->get('limit')) === true)
		{
			$limit = $this->get('limit');
		}
		else
		{
			$limit = 50;
		}

		$r = $this->profile_model->get_emails($this->get('pid'), $limit, $this->get('offset'));

		$this->response($r, 200);
	}

	/**
	 * Get Profile Managers
	 */
	function managers_get()
	{
		// Set Limit
		if (ctype_digit($this->get('limit')) === true)
		{
			$limit = $this->get('limit');
		}
		else
		{
			$limit = 50;
		}

		$r = $this->profile_model->get_managers($this->get('pid'), $limit, $this->get('offset'));

		$this->response($r, 200);
	}

	/**
	 * Get Profile Meta Data
	 */
	function meta_get()
	{
		// Set Limit
		if (ctype_digit($this->get('limit')) === true)
		{
			$limit = $this->get('limit');
		}
		else
		{
			$limit = 50;
		}

		$r = $this->profile_model->get_meta($this->get('pid'), $limit, $this->get('offset'));

		$this->response($r, 200);
	}

	function meta_post()
	{
		$pid = $this->post('pid');
		$key = $this->post('key');
		$value = $this->post('value');

		if (empty($pid) === true OR empty($key) === true OR empty($value) === true)
		{
			$this->response('PID, key, and value are required.', 400);
		}

		$this->profile_model->set_meta_key($pid, $key, $value);

		if ($this->db->affected_rows() > 0)
		{
			$this->response(true, 200);
		}
		else
		{
			$this->response('Database write failed.', 503);
		}
	}

	function pid_get()
	{
		$q = $this->db;

		if ($this->get('tel'))
		{
			$q->where('tel',tel_dialstring($this->get('tel')))->from('profiles_tel');
		}
		elseif ($this->get('email'))
		{
			$q->where('email',$this->get('email'))->from('profiles_email');
		}

		$r = $this->db->select('pid')->get();

		if ($r->num_rows() > 0)
		{
			$this->response($r->row(), 200);
		}
		else
		{
			$this->response(array('error'=>'Matching profile not found.'), 404);
		}
	}

	/**
	 * Get Profile Fields for Prototype
	 */
	function prototype_fields_get()
	{
		$r = $this->db->list_fields($this->get('table'));

		$this->response($r, 200);
	}

	/**
	 * Get Profile Telephones
	 */
	function tels_get()
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

		$r = $this->profile_model->get_tels($this->get('pid'), $limit, $this->get('offset'));

		$this->response($r, 200);
	}

}