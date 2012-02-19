<?php

class Search_model extends CI_Model
{

	public $profiles;

	function __construct ()
	{
		parent::__construct();

		$this->profiles = new Search_Profiles();
	}

}

class Search_Profiles_Model extends CI_Model
{

	public function meta($key, $value='*')
	{
		$q = $this->db;

		if ($value === '*')
		{
			$q->where('meta_value IS NOT NULL', null, false);
		}
		elseif ($value === null)
		{
			$q->where('meta_value IS NULL', null, false);
		}
		else
		{
			$q->where('meta_value', $value);
		}

		$r = $q->select('pid')->where('meta_key',$key)->get('profiles_meta')->result();

		return $this->convert_pid_list($r);
	}

	private function convert_pid_list($list)
	{
		$data = array();

		foreach ($list as $row)
		{
			if ($this->profile->get($row->pid)->exists())
			{
				$data[] = $this->profile->get($row->pid);
			}
		}

		return $data;
	}

}

// End of File