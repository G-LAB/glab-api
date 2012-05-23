<?php

class Profile_model extends CI_Model {

	function get_addresses ($pid, $limit=10, $offset=0)
	{
		return $this->db
			->where('pid', $pid)
			->get('profiles_address')
			->result_array();
	}

	function get_base($pid)
	{
		return $this->db
			->where('pid', $pid)
			->limit(1)
			->get('profiles')
			->row_array();
	}

	function get_delegates($pid, $limit=50, $offset=0)
	{
		return $this->db
			->where('pid_c', $pid)
			->limit($limit, $offset)
			->get('profiles_manager')
			->result_array();
	}

	function get_emails($pid, $limit=10, $offset=0)
	{
		return $this->db
			->select('email, is_primary')
			->where('pid', $pid)
			->order_by('is_primary','DESC')
			->limit($limit, $offset)
			->get('profiles_email')
			->result_array();
	}

	function get_email($pid)
	{
		return element('email', array_shift(array_values($this->get_emails($pid, 1))));
	}

	function get_managers($pid, $limit=50, $offset=0)
	{
		return $this->db
			->where('pid_p', $pid)
			->limit($limit, $offset)
			->get('profiles_manager')
			->result_array();
	}

	function get_meta($pid, $limit=50, $offset=0)
	{
		$this->load->helper('glib_array');

		$data = $this->db
			->where('pid', $pid)
			->limit($limit, $offset)
			->get('profiles_meta')
			->result_array();

		return array_flatten($data, 'meta_key', 'meta_value');
	}

	function get_meta_key($pid, $key)
	{
		$data = $this->db
			->where('pid', $pid)
			->where('meta_key', $key)
			->get('profiles_meta')
			->row_array();

		return element('meta_value', $data);
	}

	function set_meta_key($pid, $key, $value)
	{
		return $this->db->query("
			REPLACE INTO profiles_meta (pid,meta_key,meta_value)
			VALUES ('".$pid."','".$key."','".$value."')
		");
	}

	function get_tels($pid, $limit=10, $offset=0)
	{
		return $this->db
			->where('pid', $pid)
			->limit($limit, $offset)
			->get('profiles_tel')
			->result_array();
	}

}
?>