<?php
class Brand_model extends CI_Model {

	function __construct () {

		parent::__construct();

		$this->load->helper('array');

	}

	function get ($brid=1) {
		if (!is_numeric($brid)  || !$brid) $brid = 1;
		$q = $this->db->get_where('brands','brid = '.$brid);
		return $q->row_array();
	}

	function setSession ($brid=FALSE) {
		return $this->session->set_userdata('brand',$this->get($brid));
	}
}
?>