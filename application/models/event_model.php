<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * G-LAB Event Model for Code Igniter v2
 * Written by Ryan Brodkin
 * Copyright 2011
 */

class Event_model extends CI_Model
{
	// Log New Event
	public function log($event_type,$pid=false,$data=false)
	{
		$data['ip_address'] = $this->input->ip_address();
		$data['event_type'] = $event_type;
		$data['data'] = $data;

		$profile = $this->profile->get($pid);

		if ($pid !== false AND $profile->exists() === true)
		{
			$data['pid'] = $profile->pid;
		}
		else
		{
			$data['pid'] = $this->profile->current();
		}

		$this->api->request('get', 'log/event', $data);
	}

	public function get($evid)
	{
		$this->load->helper(array('array'));
		$this->load->language('event');

		$data = $this->db
					->select('*')
					->select('inet_ntoa(ip_address) as ip_address',false)
					->limit(1)
					->where('evid',$evid)
					->get('event_log')
					->row();

		if (count($data) > 0)
		{
			$data->template = $this->lang->line('event_'.$data->event_type);
			return $data;
		}
		else
		{
			$empty = new StdClass;
			return $empty;
		}
	}

	// Get Logfile
	public function fetch_array($filter=false,$limit=30,$offset=0)
	{
		$this->load->helper(array('array'));
		$this->load->language('event');

		$data = array();

		$result = $this->db
					->select('*')
					->select('inet_ntoa(ip_address) as ip_address',false)
					->order_by('timestamp','desc')
					->limit($limit,$offset)
					->get('event_log')
					->result_array();

		foreach ($result as $row) {
			$template = $this->lang->line('event_'.element('event_type',$row));
			$data[] = array_merge($row,array('event_template'=>$template));
		}

		return $data;
	}

	// Parse Data Into Language File Entry
	private function parse_lang($event_row)
	{
		// Accepts entire row from event log as argument.
	}

}
// End of File