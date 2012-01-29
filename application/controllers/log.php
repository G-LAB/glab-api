<?php defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Log Controller
 *
 * @package		CodeIgniter
 * @subpackage	Rest Server
 * @category	Controller
 * @author		Ryan Brodkin
*/

class Log extends REST_Controller
{
	/**
	 * Get Profile Base Data
	 */
	function event_post()
	{
		$q = $this->db	->set('event_type', $this->post('event_type'))
						->set('ip_address', 'INET_ATON(\''.$this->post('ip_address').'\')',false);

		if ($this->post('pid') == false)
		{
			$q->set('pid', $this->post('pid'));
		}

		if ($this->post('data') == false)
		{
			$q->set('data',$this->post('data'));
		}

		if ($q->insert('event_log'))
		{
			$this->response(null, 200);
		}
		else
		{
			$this->response(null, 500);
		}
	}
}