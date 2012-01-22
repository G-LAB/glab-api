<?php defined('BASEPATH') OR exit('No direct script access allowed');

require APPPATH.'/libraries/REST_Controller.php';

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
	/**
	 * Get Profile Addresses
	 */
	function addresses_get()
	{
		$q = $this->db->where('pid',$this->get('pid'));

		// Set Limit and Offset
		if (ctype_digit($this->get('limit')) === true)
		{
			$q->limit($this->get('limit'), $this->get('offset'));
		}
		else{
			$q->limit(10, $this->get('offset'));
		}

		$r = $q->get('profiles_address')->result_array();

		$this->response($r, 200);
	}

	/**
	 * Get Profile Base Data
	 */
	function base_get()
	{
		$q = $this->db->where('pid',$this->get('pid'));
		$r = $q->limit(1)->get('profiles');

		if ($r->num_rows() > 0)
		{
			$this->response($r->row_array(), 200);
		}
		else
		{
			$this->response(array('error'=>'Profile "'.$this->get('pid').'" not found.'), 404);
		}
	}

	/**
	 * Get Profile Delefates
	 */
	function delegates_get()
	{
		$q = $this->db->where('pid_c',$this->get('pid'));

		// Set Limit and Offset
		if (ctype_digit($this->get('limit')) === true)
		{
			$q->limit($this->get('limit'), $this->get('offset'));
		}
		else
		{
			$q->limit(50, $this->get('offset'));
		}

		$r = $q->get('profiles_manager')->result_array();

		$this->response($r, 200);
	}

	/**
	 * Get Profile Emails
	 */
	function emails_get()
	{
		$q = $this->db	->select('email, is_primary')
						->where('pid',$this->get('pid'))
						->order_by('is_primary','DESC');

		// Set Limit and Offset
		if (ctype_digit($this->get('limit')) === true)
		{
			$q->limit($this->get('limit'), $this->get('offset'));
		}
		else
		{
			$q->limit(10, $this->get('offset'));
		}

		$r = $q->get('profiles_email')->result_array();

		$this->response($r, 200);
	}

	/**
	 * Get Profile Managers
	 */
	function managers_get()
	{
		$q = $this->db->where('pid_p',$this->get('pid'));

		// Set Limit and Offset
		if (ctype_digit($this->get('limit')) === true)
		{
			$q->limit($this->get('limit'), $this->get('offset'));
		}
		else
		{
			$q->limit(50, $this->get('offset'));
		}

		$r = $q->get('profiles_manager')->result_array();

		$this->response($r, 200);
	}

	/**
	 * Get Profile Meta Data
	 */
	function meta_get()
	{
		$this->load->helpers('glib_array');

		$q = $this->db	->where('pid',$this->get('pid'));

		// Set Limit and Offset
		if (ctype_digit($this->get('limit')) === true)
		{
			$q->limit($this->get('limit'), $this->get('offset'));
		}
		else
		{
			$q->limit(100, $this->get('offset'));
		}

		$r = $q->get('profiles_meta')->result_array();

		$this->response(array_flatten($r, 'meta_key', 'meta_value'), 200);
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
		$q = $this->db->where('pid',$this->get('pid'));

		// Set Limit and Offset
		if (ctype_digit($this->get('limit')) === true)
		{
			$q->limit($this->get('limit'), $this->get('offset'));
		}
		else
		{
			$q->limit(15, $this->get('offset'));
		}

		$r = $q->get('profiles_tel')->result_array();

		$this->response($r, 200);
	}

}