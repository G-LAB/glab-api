<?php

class Entity_model extends CI_Model {

	public $entities;

	function refreshSession ($eid) {
		$userData = $this->get($eid, TRUE, TRUE);
		$entity = $this->session->set_userdata('userData', $userData);
		$eid = $this->session->set_userdata('eid', $userData['eid']);

		if ($entity && $eid) return TRUE;
		else return FALSE;
	}

	function get($eid=null, $getPrefs=FALSE, $getAdmin=FALSE) {
		trigger_error('The Entity model has been depreciated, use the profile model instead.',E_USER_DEPRECATED);
		if (is_array($eid)) $is_array = TRUE;
		elseif (is_string($eid) || is_numeric($eid)) $is_array = FALSE;
		else return FALSE;

		if (is_numeric($eid)) $entities = array('eid'=>$eid);
		elseif (is_array($eid)) $entities = $eid;
		else return FALSE;

		foreach ($entities as $eid) {

			// Check If EID Not Set
			if (!isset($this->entities[$eid])) {
				$query = $this->db->get_where('entities', 'eid = '.$eid);

				if ($query->num_rows()>0) {
					$result = $query->row_array();

					$this->entities[$eid] = $result;
					if (!$result['isCompany']) {
						$this->entities[$eid]['name'] = $result['firstName'].' '.$result['lastName'];
						$this->entities[$eid]['name-reverse'] = $result['lastName'].', '.$result['firstName'];
					} else {
						$this->entities[$eid]['name'] = $result['companyName'];
						$this->entities[$eid]['name-reverse'] = $result['companyName'];
					}

					// PREFERENCES
					if ($getPrefs) {
						$this->entities[$eid]['prefs'] = array();
						$prefs = $this->db->get_where('entities_prefs', 'eid = '.$eid);
						if ($prefs->num_rows() > 0) $this->entities[$eid]['prefs'] = $prefs->row_array();
					}

					// ADMIN
					if ($getAdmin) {
						$this->entities[$eid]['admin'] = array();
						$admin = $this->db->get_where('entities_admin', 'eid = '.$eid);
						if ($admin->num_rows() > 0) $this->entities[$eid]['admin'] = $admin->row_array();
					}

				}
				else return FALSE;
			}

			// RETURN IF SINGLE REQUEST
			if (!$is_array) return $this->entities[$eid];
			// ELSE PASS TO FINAL RETURN
			else $data[] = $this->entities[$eid];
		}

		// RETURN ARRAY ON MULTIPLE REQUESTS
		return $data;
	}

	function getEID () {
		$CI =& get_instance();
		return $CI->session->userdata('eid');
	}

	function getEidByAcctnum ($acctnum) {

		$data = $this->db	->where('acctnum', $acctnum)
							->get('entities')
							->row_array();

		return element('eid', $data);
	}

	function getEidByEmail ($email) {

		$q = $this->db->query('SELECT eid FROM emailbook WHERE email = "'.$email.'" LIMIT 1');
		$r = $q->row_array();

		if (isset($r['eid'])) return $r['eid'];
		else return FALSE;
	}

	function getEidByPhone ($phone) {

		$this->db->select('eid');
		$this->db->limit(1);
		$q = $this->db->get_where('phonebook','num = '.$phone);
		$r = $q->row_array();

		if (isset($r['eid'])) return $r['eid'];
		else return FALSE;
	}

	function getValue($var,$eid=FALSE,$zone=FALSE) {
		$CI =& get_instance();
		$CI->load->helper('array');

		if ($eid === FALSE) $eid = $CI->session->userdata('eid');

		// Change Source Data
		if ($zone != FALSE) $source = $this->get($eid, TRUE, TRUE);
		else $source = $this->get($eid);

		// Limit Scope
		if ($zone != FALSE) $source = $source[$zone];

		return element($var,$source);
	}

	function getAdminValue($var,$eid=FALSE) {
		$CI =& get_instance();
		$CI->load->helper('array');

		if ($eid === FALSE) $eid = $CI->session->userdata('eid');

		// Source Data
		$source = $this->get($eid, FALSE, TRUE);

		$source = element('admin',$source);

		return element($var,$source);
	}

	function getEmail ($eid) {
		$CI =& get_instance();

		// Lookup Default Email
		$this->db->limit(1);
		$where['eid'] = $eid;
		$where['isDefault'] = TRUE;
		$q = $this->db->get_where('emailbook',$where);
		$r = $q->row_array();

		// Lookup Most Recently Added Email
		if (!isset($r['email'])) {
			$this->db->limit(1);
			$this->db->order_by('emid','desc');
			$q = $this->db->get_where('emailbook','eid = '.$eid);
			$r = $q->row_array();
		}

		if (isset($r['email'])) return $r['email'];
		else return FALSE;
	}

	function getEmails ($eid) {
		$CI =& get_instance();

		$this->db->limit(10);
		$this->db->order_by('emid','desc');
		$q = $this->db->get_where('emailbook','eid = '.$eid);

		if ($q->num_rows() > 0) return $q->result_array();
		else return FALSE;
	}

	function getSubentities ($eid,$expand=TRUE) {

		if (!is_numeric($eid)) return FALSE;

		$this->db->select('child as eid');
		$q = $this->db->get_where('subentities','parent = '.$eid);
		$r = $q->result_array();

		foreach ($r as $entity) $subentities[] = $entity['eid'];

		// Return Array of People
		if (isset($subentities) && $expand==TRUE) return $this->get($subentities);
		// Return Array of EIDs
		elseif (isset($subentities)) return $subentities;
		else return FALSE;
	}

	function getAddresses ($eid) {
		$q = $this->db->get_where('addrbook','eid = '.$eid);
		$r = $q->result_array();

		$data = array();
		foreach ($r as $addr) $data[element('addrid',$addr)] = $addr;
		return $data;
	}

	function getAddress($addrid) {
		if (!$addrid) return FALSE;

		$this->db->limit(1);
		$q = $this->db->get_where('addrbook','addrid = '.$addrid);
		return $q->row_array();
	}

	function getAddressFormatted ($addrid) {
		if (!$addrid) return FALSE;

		$addr = $this->getAddress($addrid);

		$output = $addr['addr1']."\n";

		if ($addr['addr2']) $output.= $addr['addr2']."\n";

		$output.= $addr['city'].', '.$addr['state'].' '.$addr['zip5'];
		if ($addr['zip4']) $output.= '-'.$addr['zip4'];

		return $output;
	}

	function add($data) {

		if ($data['isCompany']) {
			// Company
			$insert['isCompany'] = $data['isCompany'];
			$insert['companyName'] = $data['companyName'];
		} else {
			// Person
			$insert['isCompany'] = $data['isCompany'];
			$insert['firstName'] = $data['firstName'];
			$insert['lastName'] = $data['lastName'];
		}

		// Account Number
		$insert['acctnum'] = $this->getNewAcctnum();

		$success = $this->db->insert('entities',$insert);

		if ($success) return $this->db->insert_id();
		else return FALSE;
	}

	function addPerm($parent,$child,$jobTitle) {
		$sub['parent'] = $parent;
		$sub['child'] = $child;
		$sub['jobTitle'] = $jobTitle;
		$sub['creator'] = $this->getValue('eid');

		$this->db->insert('subentities',$sub);
	}

	function addAdmin ($eid) {
		$entity = $this->get($eid, TRUE);
		if (! isset($entity['prefs'])) $this->db->insert('entities_prefs',array('eid'=>$eid));
		$this->db->insert('entities_admin',array('eid'=>$eid));
	}

	function addEmail($eid,$email) {
		$data['eid'] = $eid;
		$data['email'] = $email;

		return $this->db->insert('emailbook',$data);
	}

	function addPhone($eid,$phone,$type,$label=FALSE) {
		$data['eid'] = $eid;
		$data['num'] = ltrim($phone,'1');
		$data['type'] = $type;
		if ($label != FALSE) $data['label'] = $label;

		return $this->db->insert('phonebook',$data);
	}

	function updateDefaultEmail ($eid,$emid) {
		$this->db->set('isDefault',FALSE);
		$this->db->where('eid',$eid);
		$this->db->update('emailbook');

		$this->db->set('isDefault',TRUE);
		$this->db->where('eid',$eid);
		$this->db->where('emid',$emid);
		$this->db->limit(1);
		return $this->db->update('emailbook');
	}

	function updatePassword ($eid,$old,$new) {
		$this->db->select('password');
		$this->db->where('eid',$eid);
		$this->db->limit(1);
		$q = $this->db->get('entities');
		$r = $q->row_array();

		if ( isset($r['password']) && $r['password'] == sha1($old) ) {
			$this->db->set('password',sha1($new));
			$this->db->where('eid',$eid);
			$this->db->limit(1);
			return $this->db->update('entities');
		}
		else return FALSE;
	}

	function addAddress($eid,$data) {

		if (isset($data['zip']) && strlen($data['zip']) == 10) {
			$zip = explode('-',$data['zip']);
			$data['zip5'] = $zip[0];
			if (isset($zip[1])) $data['zip4'] = $zip[1];

		} elseif (isset($data['zip']) && strlen($data['zip']) == 9) {
			$data['zip5'] = substr($data['zip'], 0, 5);
			$data['zip4'] = substr($data['zip'], 5, 4);

		} elseif (isset($data['zip'])) {
			$data['zip5'] = substr($data['zip'], 0, 5);
		}

		$insert['eid'] = $eid;
		$insert['type'] = $data['type'];

		$insert['addr1'] = $data['addr1'];
		if (isset($data['addr2'])) $insert['addr2'] = $data['addr2'];
		$insert['city'] = $data['city'];
		$insert['state'] = $data['state'];
		$insert['zip5'] = $data['zip5'];
		if (isset($data['zip4'])) $insert['zip4'] = $data['zip4'];

		if (isset($data['label'])) $insert['label'] = $data['label'];

		$this->db->insert('addrbook',$insert);
	}

	function deleteEmail ($emid) {
		$this->db->where('emid',$emid);
		return $this->db->delete('emailbook');
	}

	function getNewAcctnum ($acctnum=null) {

		if ($acctnum==null) $acctnum = time();

		$q = $this->db->get_where('entities','acctnum ='.$acctnum);
		$r = $q->result_array();

		if (count($r) == 0) return $acctnum;
		else return $this->getNewAcctnum($acctnum + 1);
	}

	function search ($q) {

		$this->db->or_like('acctnum',preg_replace("/[0-9]/",'',$q));
		$this->db->or_like('companyName',$q);
		$this->db->or_like('firstName',$q);
		$this->db->or_like('lastName',$q);

		$this->db->limit(10);

		$q = $this->db->get('entities');
		$r = $q->result_array();

		foreach ($r as $id=>$result) {
			// Name
			if (!$result['isCompany']) $r[$id]['name'] = $result['firstName'].' '.$result['lastName'];
			else $r[$id]['name'] = $result['companyName'];
			// Account Number
			$r[$id]['acctnum'] = acctnum_format($result['acctnum']);
		}

		return $r;
	}

}

// End of File