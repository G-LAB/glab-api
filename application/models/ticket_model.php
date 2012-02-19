<?php

class Ticket_model extends CI_Model {

	public $queues;

	private $S3_bucket;

	function __construct ()
	{
		parent::__construct();

		$this->load->helper('array');

		$this->get_queues();

		$this->load->config('buckets');
		$this->S3_bucket = $this->config->item('bucket_attachments');

		$this->load->library('S3');

		$accessKey = $this->config->item('auth_aws_key_access');
		$secretKey = $this->config->item('auth_aws_key_secret');

		S3::setAuth($accessKey, $secretKey);
	}

	function get_attachments ($fingerprint)
	{
		$data = array();

		$r = S3::getBucket($this->S3_bucket,$fingerprint);

		if (is_array($r)) foreach ($r as $file_name=>$attachment)
		{
			$data[basename($file_name)] = $attachment;
		}

		return $data;
	}

	function get_attachment_url ($uri)
	{
		return S3::getAuthenticatedURL($this->S3_bucket, $uri, 900, false, true);
	}

	function get_queues()
	{
		$this->db->order_by('name');
		$query = $this->db->get('com_queues');
		$queues = $query->result_array();

		$data = array();

		foreach ($queues as $q) {
			$qid = $q['qid'];
			$data[$qid] = $q;
		}

		$this->queues = $data;

		return $data;
	}

	function get_queue($qid)
	{
		return element($qid,$this->queues);
	}

	function fetch_qid ($email)
	{
		$this->load->database();

		$q = $this->db->query('SELECT qid FROM com_queues WHERE email = "'.$email.'" LIMIT 1');
		$r = $q->row_array();

		if ( isset($r['qid']) ) return $r['qid'];
		else return 0;
	}

	function get_watchers ($tiknum)
	{
		if (is_numeric($tiknum) !== true OR $tiknum === 0)
		{
			trigger_error('Ticket number "'.$tiknum.'" is not valid.');
			return false;
		}

		$this->load->helper('glib_validation');

		$q = $this->db->get_where('com_entry','tiknum = '.$tiknum);

		$watchers = array();

		foreach ($q->result_array() as $entry) if (is_email(element('source',$entry)))
		{
			$watchers[element('source',$entry)] = null;
		}
		return array_keys($watchers);
	}

	function get_count ($qid=null, $activeOnly = true)
	{
		$active = array('new','waiting-agent');

		if ($qid != null) $this->db->where('qid', $qid);
		if ($activeOnly) $this->db->where_in('status', $active);
		$this->db->from('com_tickets_v');
		return $this->db->count_all_results();
	}

	function get_ticket($tiknum)
	{
		$q = $this->db	->select('t.*')
						->select('e.status')
						->select('e.source')
						->select('e.subject')
						->select('e.type')
						->select('l.timestamp')
						->join('com_entry e', 't.tiknum=e.tiknum', 'left outer')
						->join('event_log l', 'e.event=l.evid', 'left outer')
						->where('t.tiknum',$tiknum);

		return $q->get('com_tickets t')->row_array();
	}

	function get_tickets($qid=null, $status=null)
	{
		$q = $this->db	->select('t.*')
						->select('e.source')
						->select('e.subject')
						->select('e.type')
						->select('l.timestamp')
						->join('com_entry e', 't.tiknum=e.tiknum', 'left outer')
						->join('event_log l', 'e.event=l.evid', 'left outer')
						->group_by('t.tiknum');

		if ($status != null)
		{
			$q->where_in('t.status', $status);
		}

		if ($qid != null  && $qid != 0)
		{
			$q->where('t.qid',$qid);
		}

		return $q->get('com_tickets_v t')->result_array();
	}

	function get_entries ($tiknum)
	{
		$q = $this->db	->select('e.*')
						->select('l.timestamp')
						->select('l.profile as status_pid')
						->join('event_log l', 'e.event=l.evid', 'left outer')
						->where('tiknum',$tiknum);

		$r = $q->get('com_entry e')->result_array();

		foreach ($r as &$entry)
		{
			$entry['attachments'] = $this->get_attachments(element('fingerprint',$entry));
		}

		return $r;
	}

	function fetch_id($tikid)
	{
		$tikid = preg_replace('/-/', '', trim($tikid));
		$query = $this->db->get_where('com_tickets', array('tikid'=>$tikid) );
		$result = $query->row_array();
		if (isset($result['tiknum'])) return $result['tiknum'];
		else return FALSE;
	}

	function add_attachment($fingerprint, $filename, $data)
	{
		$path = $fingerprint.'/'.$filename;
		$tmpfname = tempnam("/tmp", "attachment_");
		$handle = fopen($tmpfname, "w");
		fwrite($handle, $data);
		fclose($handle);

		S3::putObject(
			S3::inputFile($tmpfname),
			$this->S3_bucket,
			$path,
			S3::ACL_PRIVATE,
			array(),
			array( // Custom $requestHeaders
				"Cache-Control" => "max-age=315360000",
				"Expires" => gmdate("D, d M Y H:i:s T", strtotime("+5 years"))
			)
		);

		unlink($tmpfname);
	}

	function add_entry($tiknum, $type, $source, $fingerprint, $subject, $body_text, $body_html=null, $status=false, $notify=true, $cdr=null)
	{
		$profile = $this->profile->get($source);
		$ticket = $this->get_ticket($tiknum);
		$queue = $this->get_queue(element('qid',$ticket));

		// Generate Reply-To Email
		if (isset($queue['email']) === true)
		{
			$reply_to = preg_replace('/@/', '+'.element('tikid',$ticket).'@', element('email',$queue));
		}
		else
		{
			$reply_to = 'cms+'.element('tikid',$ticket).'@glabstudios.com';
		}

		// Determine Status if Not Explicitly Provided
		if (empty($status) == true)
		{
			if ($profile->is_employee() === true)
			{
				$status = 'waiting-client';
			}
			else
			{
				$status = 'waiting-agent';
			}
		}

		$evid = $this->event->log('ticket_reply',$profile->pid, array('tikid'=>element('tikid',$ticket), 'source'=>$source));

		$q = $this->db	->set('tiknum',$tiknum)
						->set('type',$type)
						->set('source',$source)
						->set('status',$status)
						->set('fingerprint',$fingerprint)
						->set('subject',$subject)
						->set('body_text',$body_text)
						->set('body_html',$body_html)
						->set('event',$evid)
						->set('cdr',$cdr)
						->insert('com_entry');

		if ($this->db->affected_rows() > 0)
		{

			if ($notify === true)
			{
				$watchers = $this->get_watchers($tiknum);

				// If There Are Watchers
				if ($watchers !== false)
				{
					// From Employee to Client
					if ($profile->is_employee() === true)
					{
						$email['subject'] = $subject;
						$email['message'] = $body_text;
						$email['from']['name'] = $profile->name->full;
						$email['from']['title'] = $profile->meta->employee_title;
						$email['tikid'] = tikid_format($this->input->post('tikid'));
						$this->notification->email('client/ticket_reply', $email, $watchers, element('brand',$queue), $reply_to);
					}
					// From Client to G LAB
					else
					{
						// Notify G LAB or Client?
					}
				}
			}

			return true;
		}
		else
		{
			return false;
		}
	}

	function add_ticket($type, $qid, $source, $fingerprint, $subject, $body_text, $body_html=null, $status='new', $notify=true, $cdr=null)
	{
		$profile = $this->profile->get($source);
		$queue = $this->get_queue($qid);
		$tikid = uniqid();

		// Generate Reply-To Email
		if (isset($queue['email']) === true)
		{
			$reply_to = preg_replace('/@/', '+'.$tikid.'@', $queue['email']);
		}
		else
		{
			$reply_to = 'cms+'.$tikid.'@glabstudios.com';
		}

		$this->db->trans_start();

		$evid = $this->event->log('ticket_open',$profile->pid, array('tikid'=>$tikid, 'source'=>$source));

		$q = $this->db	->set('tikid',$tikid)
						->set('pid',$profile->pid)
						->set('qid',$qid)
						->insert('com_tickets');

		$tiknum = $this->db->insert_id();

		$this->add_entry($tiknum, $type, $source, $fingerprint, $subject, $body_text, $body_html, $status, false, $cdr);

		if ($this->db->trans_complete())
		{
			if ($notify === true)
			{
				$watchers = $this->get_watchers($tiknum);

				// If There Are Watchers
				if ($watchers !== false)
				{
					$email['subject'] = $subject;
					$email['message'] = $body_text;
					$email['agent_name'] = $profile->name->friendly;
					$email['tikid'] = tikid_format($this->input->post('tikid'));
					$this->notification->email('client/ticket_open', $email, $watchers, element('brand',$queue), $reply_to);
				}
			}

			return true;
		}
		else
		{
			return false;
		}
	}

	function import_cdr()
	{
		$data = array();

		$cdr_entries = S3::getBucket($this->S3_bucket,'pbx/cdr_');

		foreach ($cdr_entries as $uri=>$object)
		{
			$object = S3::getObject($this->S3_bucket,$uri);

			if ($object->error === false AND is_string($object->body) === true)
			{
				$this->load->helper('glib_string');

				$data_cdr = unserialize($object->body);

				// Check for Existing Ticket Matching Call ID
				$q = $this->db->where('fingerprint',element('uniqueid',$data_cdr))->limit(1)->get('com_entry');

				// Add Entry to Existing Ticket
				if ($q->num_rows() > 0)
				{
					$r = $q->row_array();

					$data[element('uniqueid',$data_cdr)] = $this->append_cdr(element('enid',$r),$object->body);
				}
				// Open New Ticket
				else
				{
					$this->load->helper('glib_array');
					$extension_map = array_flatten($this->queues,'extension','qid');

					$extension = element('dst',$data_cdr);

					$qid = element($extension,$extension_map);

					$data[element('uniqueid',$data_cdr)] = $this->add_ticket('phone', $qid, tel_dialstring(element('src',$data_cdr)), element('uniqueid',$data_cdr), null, null, null, 'closed', false, $object->body);
				}

				// Delete CDR File Upon Success
				if ($data[element('uniqueid',$data_cdr)] === true)
				{
					S3::deleteObject($this->S3_bucket,$uri);
				}
			}
		}

		return $data;
	}

	function append_cdr($enid,$cdr)
	{
		if (is_numeric($enid) !== true OR is_string($cdr) !== true)
		{
			return false;
		}

		$q = $this->db	->set('cdr',$cdr)
						->where('enid',$enid)
						->limit(1)
						->update('com_entry');

		if ($this->db->affected_rows() > 0)
		{
			return true;
		}
		else
		{
			return false;
		}
	}

}

// End of File