<?php

class Domain_model extends CI_Model {

	function __construct ()
	{
		parent::__construct();
		$this->load->config('auth');
		$this->load->helper('array');
		$this->load->library('OpenSRS');
	}

	function get ($domain)
	{
		return $this->opensrs->domain()->get($domain);
	}

	function get_list ($limit,$offset)
	{
		$domains = $this->db->limit($limit, $offset);
		// @todo ->order_by("CONCAT(lastName,firstName,companyName)");
		// if ($pid) $domains = $domains->where('pid', $pid);
		$domains = $domains->get('domains')->result_array();

		foreach ($domains as $row) {
			$domain = $this->opensrs->domain();
			$data[element('pid',$row)][element('domain',$row)] = $domain->get(element('domain',$row));
		}

		return $data;
	}

	function is_available ($domain)
	{
		return $this->opensrs->domain()->lookup($domain);
	}

}

// End of File