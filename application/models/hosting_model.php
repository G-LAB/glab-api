<?php

class Hosting_model extends CI_Model {

	function __construct () {

		parent::__construct();

		$this->load->library('Plesk');

	}

	function addCustomer ($psid,$eid) {

		// Set API Server
		$this->setServer($psid);

	}

	function updateCustomer ($psid,$eid) {

		// Set API Server
		$this->setServer($psid);

	}

	function updatePasswordCustomer ($psid, $eid, $password=false) {

		$pcid = $this->getPcid($psid, $eid);

		if ($pcid) {

			if (!$password) $password = $this->genPass();

			$customer_data = $this->getCustomer($psid, $eid);

			if (!$customer_data) return false;

			$reset['username'] = $customer_data['login'];
			$reset['password'] = $password;
			$reset['success'] = $this->plesk->updateCustomerPassword($pcid,$password);

			if ($reset['success']) {
				$reset['email'] = $this->notification->email('client/hosting_resetpassword_customer', $reset, $eid);
				return $reset;

			} else {
				return false;
			}

		} else {
			return false;
		}

	}

	function updatePasswordFTP ($psid,$domain,$password=false) {

		if (!$password) $password = $this->genPass();

		$domain_data = $this->hosting->getDomain($psid, $domain);

		if (!$domain_data) return false;

		$reset['domain'] = $domain;
		$reset['username'] = $domain_data['hosting']['ftp_login'];
		$reset['password'] = $password;
		$reset['success'] = $this->plesk->updateSubscriptionPassword($domain,$domain_data['hosting']['ip_address'],$password);

		if ($reset['success']) {
			$reset['email'] = $this->notification->email('client/hosting_resetpassword_ftp',$reset,1);
			return $reset;
		}
		else return false;

	}

	function getCustomer ($psid, $eid) {

		// Set API Server
		$this->setServer($psid);

		$pcid = $this->getPcid($psid, $eid);

		if ($pcid) return $this->plesk->getCustomer($pcid);
		else return false;

	}

	function getCustomers ($psid,$offset=0) {

		// Load Dependencies
		$this->load->helper('array');

		// Set API Server
		$this->setServer($psid);

		// Get Clients
		$this->db->limit(5,$offset);
		$this->db->select('CONCAT("a",e.acctnum) as acctnum, e.eid');
		$this->db->where('psid',$psid);
		$this->db->join('entities e','e.eid = hc.eid');
		$this->db->order_by('CONCAT(companyName,e.firstName," ",e.lastName)');
		$q = $this->db->get('host_clients hc');
		$r = $q->result_array();
		$r = array_flatten($r,'acctnum','eid');

		$customers = $this->plesk->getCustomers(array_keys($r));

		reset($r);

		foreach ($customers as $pcid=>$customer) {

			$eid = current($r);
			next($r);

			$current['eid'] = $eid;
			$current['name'] = $this->entity->getValue('name',$eid);

			$data[$pcid] = array_merge($current, $customer);

		}

		return $data;

	}

	function getDomain ($psid,$domain) {

		// Multi-Server Search
		if ($psid == '*') {
			foreach ($this->getPsids() as $psid) {
				$result = $this->getDomain($psid,$domain);
				if ($result) return $result;
			}

		// Single Domain Result (Referenced Above)
		} else {
			// Set API Server
			$this->setServer($psid);

			$data = $this->plesk->getSubscription($domain);

			// Return
			if (isset($data['gen_info'])) {
				return array_merge(	$data,
									array(
										'psid'=>$psid,
										'eid'=>$this->getEid($psid, $data['gen_info']['owner-id'])
									)
				);

			} else {
				return false;
			}
		}


	}

	function getDomains ($psid,$eid=null,$offset=0) {

		// Set API Server
		$this->setServer($psid);

		return $this->plesk->getSubscriptions();

	}


	private function getServer ($psid) {

		$this->db->select('*, INET_NTOA(ip_address) as ip_address',FALSE);
		$this->db->where('psid',$psid);
		$this->db->limit(1);

		$q = $this->db->get('host_servers');

		return $q->row_array();
	}

	function getServerProfile ($psid) {

			$this->load->library('MediaTemple');

			// Set API Server, Returns DB Entry
			$data['profile'] = $this->setServer($psid);

			// Tack on Media Temple Profile
			$data['mt'] = $this->mediatemple->get_service($data['profile']['mt_svc']);

			// Get Profile From Plesk
			$plesk = $this->plesk->getServer();

			return array_merge($data,$plesk);
		}

	function controlServices ($psid,$services,$action='restart') {

		// Set API Server
		$this->setServer($psid);

		return $this->plesk->controlService($services,$action);
	}

	function getPcid ($psid, $eid) {

		// Set API Server
		$this->setServer($psid);

		$acctnum = $this->entity->getValue('acctnum', $eid);

		$data = $this->plesk->getCustomer('a'.$acctnum);

		return element('id', $data);
	}

	function getEid ($psid, $pcid) {

		// Set API Server
		$this->setServer($psid);

		$data = $this->plesk->getCustomer($pcid);

		return $this->entity->getEidByAcctnum(substr($data['login'], 1));

	}

	private function getPsids () {

		$this->db->select('psid');
		$q = $this->db->get('host_servers');
		$r = $q->result_array();

		$output = array();
		foreach ($r as $server) {
			$output[] = $server['psid'];
		}

		return $output;
	}

	private function setServer ($psid) {

		$server = $this->getServer($psid);

		$host = $server['ip_address'];
		$username = $server['auth_user'];
		$password = $server['auth_pass'];

		$this->plesk->init($host,$username,$password);

		return $server;
	}

	private function genPass ($length = 8) {

		// start with a blank password
		$password = "";

		// define possible characters - any character in this string can be
		// picked for use in the password, so if you want to put vowels back in
		// or add special characters such as exclamation marks, this is where
		// you should do it
		$possible = "2346789bcdfghjkmnpqrtvwxyzBCDFGHJKLMNPQRTVWXYZ";

		// we refer to the length of $possible a few times, so let's grab it now
		$maxlength = strlen($possible);

		// check for length overflow and truncate if necessary
		if ($length > $maxlength) {
		  $length = $maxlength;
		}

		// set up a counter for how many characters are in the password so far
		$i = 0;

		// add random characters to $password until $length is reached
		while ($i < $length) {

		  // pick a random character from the possible ones
		  $char = substr($possible, mt_rand(0, $maxlength-1), 1);

		  // have we already used this character in $password?
		  if (!strstr($password, $char)) {
		    // no, so it's OK to add it onto the end of whatever we've already got...
		    $password .= $char;
		    // ... and increase the counter by one
		    $i++;
		  }

		}

		return $password;

	}

}

// End of File