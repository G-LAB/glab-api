<?php
class Brand_model extends CI_Model
{
	function get_identity ($brid)
	{
		settype($brid, 'integer');

		if ($brid == 0)
		{
			$brid = 1;
		}

		$q = $this->db
			->where(array('brid'=>$brid))
			->limit(1)
			->get('brands');

		return $q->row_array();
	}

	function get_identities ($limit,$offset)
	{
		$q = $this->db
			->limit($limit,$offset)
			->get('brands');

		return $q->result_array();
	}

}
?>