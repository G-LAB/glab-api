<?php defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Mailing List Controller
 *
 * @package		CodeIgniter
 * @subpackage	Rest Server
 * @category	Controller
 * @author		Ryan Brodkin
*/

class Email_list extends REST_Controller
{
	protected $list;
	protected $subscriber;

	function __construct()
	{
		parent::__construct();
		$this->load->library('MailChimp');

		$this->list = $this->mailchimp->list_id();
	}

	/**
	 * Get Groups
	 */
	function groups_get()
	{
		$r = $this->mailchimp->listInterestGroupings($this->list);
		$this->response($r, 200);
	}

	/**
	 * Get Subscriber Info
	 */
	function subscriber_get()
	{
		$r = $this->mailchimp->listMemberInfo($this->list, $this->_mcapi_id());

		if (isset($r[0]) === true) {
			$this->response($r[0], 200);
		}
		else
		{
			$this->response(array('error'=>'Subscriber not found.'), 404);
		}
	}

	/**
	 * Add or Update Subscriber
	 */
	function subscriber_post()
	{
		$this->load->helper('array');
		$this->load->helper('glab_number');

		// Get PID
		$pid = $this->post('pid');

		// Get MailChimp Subscriber ID and Load Profile Model
		$mcapi_id = $this->_mcapi_id();

		$data['base'] = $this->profile_model->get_base($pid);
		$data['email'] = $this->profile_model->get_email($pid);
		$data['birthdate'] = $this->profile_model->get_meta_key($pid, 'birthdate');

		// Mail Merge Data
		$merge_vars['FNAME'] = element('name_first', $data['base']);
		$merge_vars['LNAME'] = element('name_last', $data['base']);
		$merge_vars['EMAIL'] = $data['email'];
		$merge_vars['ACCT_OPEN'] = $this->profile_model->get_meta_key($pid, 'account_opened');
		$merge_vars['ACCT_NUM'] = acctnum_format($pid);
		$merge_vars['PID_HEX'] = dechex($pid);

		if ($data['birthdate'] == true)
		{
			$this->load->helper('array');
			$birthdate = date_parse($data['birthdate']);
			$merge_vars['BDAY'] = element('month', $birthdate).'/'.element('day', $birthdate);
		}
		else
		{
			$merge_vars['BDAY'] = '';
		}

		$interests = (array) $this->post('interests');
		foreach ($interests as $interest=>$groups)
		{
			if (is_numeric($interest) === true)
			{
				$merge_vars['GROUPINGS'][$interest]['id'] = $interest;
			}
			else
			{
				$merge_vars['GROUPINGS'][$interest]['name'] = $interest;
			}

			settype($groups, 'array');
			$merge_vars['GROUPINGS'][$interest]['groups'] = implode(',', $groups);
		}

		// Get Email Address in MailChimp DB
		if ($mcapi_id == true)
		{
			$subscriber = $this->mailchimp->listMemberInfo($this->list, $mcapi_id);

			if (isset($subscriber['email']) === true)
			{
				$data['email'] = $subscriber['email'];
			}
		}

		// Create or Update Subscriber
		if ($data['email'] == true)
		{
			$r = $this->mailchimp->listSubscribe($this->list, $data['email'], $merge_vars, 'html', false, true, true, true);
		}
		else
		{
			$this->response(array('error'=>'Bad MCAPI ID or no email on record. ('.$data['email'].')'), 400);
		}

		// Update MailChimp ID
		if ($r == true)
		{
			$member = $this->mailchimp->listMemberInfo($this->list, $data['email']);
			$this->profile_model->set_meta_key($pid, 'mcapi_id', $member[0]['id']);
		}

		// Return
		if ($r === false)
		{
			$this->response(array('error'=>$this->mailchimp->error()), 400);
		}
		else
		{
			$this->response($interests, 200);
		}
	}

	function _mcapi_id($pid=false)
	{
		$this->load->model('profile_model');

		if ($pid === false)
		{
			$pid = $this->input->get_post('pid');
		}

		return $this->profile_model->get_meta_key($pid, 'mcapi_id');
	}

}