<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * G-LAB Document Model for Code Igniter v2
 * Written by Ryan Brodkin
 * Copyright 2011
 */

class Document_model extends CI_Model
{
	private $S3_bucket;

	function __construct ()
	{
		$this->load->config('buckets');
		$this->S3_bucket = $this->config->item('bucket_documents');

		$this->load->library('S3');

		$accessKey = $this->config->item('auth_aws_key_access');
		$secretKey = $this->config->item('auth_aws_key_secret');

		S3::setAuth($accessKey, $secretKey);
	}

	function add ($stream,$pid,$name=null,$description=null,$method='system')
	{

		if (in_array($method, array('fax','upload','system')) !== true)
		{
			return false;
		}

		$this->load->database();
		$this->load->helper('file');

		$file_id = uniqid();
		$tmp_path = sys_get_temp_dir().'/'.$file_id;

		// Create Temporary Directory
		mkdir($tmp_path);

		// Write File to Disk
		write_file($tmp_path.'/original.pdf', $stream);

		// Get Number of Pages
		ob_start();
		passthru('/usr/bin/identify '.$tmp_path.'/original.pdf', $magick);
		$magick = ob_end_clean();
		$page_count = count(explode("\n", `/usr/bin/identify `.$tmp_path.`/original.pdf`));

		// Generate Thumbnails
		exec('/usr/bin/convert -thumbnail 150x '.$tmp_path.'/original.pdf '.$tmp_path.'/thumb.png');
		exec('/usr/bin/convert '.$tmp_path.'/original.pdf '.$tmp_path.'/page.png');

		foreach (get_filenames($tmp_path,true) as $path)
		{
			S3::putObject(
			    S3::inputFile($path),
			    $this->S3_bucket,
			    $file_id.'/'.basename($path),
			    S3::ACL_PRIVATE,
			    array(),
			    array( // Custom $requestHeaders
			        "Cache-Control" => "max-age=315360000",
			        "Expires" => gmdate("D, d M Y H:i:s T", strtotime("+5 years"))
			    )
			);

		}

		// Delete Temporary Directory and All Contents
		delete_files($tmp_path); // NOT WORKING???

		$evid = $this->event->log('event_document_created',$pid,array('file_id'=>$file_id));

		$q = $this->db	->set('source',$method)
						->set('file_id',$file_id)
						->set('pid',$pid)
						->set('name',$name)
						->set('description',$description)
						->set('page_count',$page_count)
						->set('event',$evid);

		if (strtolower($method) === 'fax')
		{
			$q->set('is_new',true);
		}

		$q->insert('documents');

		if ($q->affected_rows() > 0) return true;
		else return false;
	}

	function add_fax ($stream, $tel)
	{
			$this->load->helper(array('file','glib_string'));
			$this->load->model('profile');

			$pid = $this->profile->get($tel)->pid;
			if (is_numeric($pid) != true) $pid = null;

			return $this->add($stream,$pid,'Faxed Document','Incoming fax from '.tel_format($tel),'fax');
	}

	function fetch_array ($offset=0,$limit=10,$type=false)
	{
		$q = $this->db	->limit($limit,$offset)
						->order_by('dcid','DESC');

		if ($type !== false)
		{
			if (strtolower($type) === 'new')
			{
				$q->where('is_new',true);
			}
			else
			{
				$q->where('source',$type);
			}
		}

		$r = $q->get('documents')->result();

		foreach ($r as &$doc)
		{
			$s3 = $this->get_original($doc->file_id);
			$doc->file_info = (object) array_shift(array_values($s3));
		}

		return $r;
	}

	function get ($dcid)
	{
		if (ctype_xdigit($dcid) === true)
		{
			$dcid = hexdec($dcid);
		}

		$data = $this->db->where('dcid',$dcid)->limit(1)->get('documents');

		if ($data->num_rows() == 1)
		{
			return $data->row();
		}
	}

	function get_count_new ()
	{
		return $this->db->where('is_new',TRUE)->count_all_results('documents');
	}

	function get_original ($file_id)
	{
		return S3::getBucket($this->S3_bucket, $file_id.'/original.pdf');
	}

	function get_pages ($file_id)
	{
		return S3::getBucket($this->S3_bucket, $file_id.'/page');
	}

	function get_tmp_path ($file_id)
	{
		$this->load->helper('file');

		$data = @file_get_contents($this->get_url($file_id.'/original.pdf'));

		$path = tempnam(sys_get_temp_dir(),'fax_');

		if (empty($data) !== true)
		{
			write_file($path,$data);
			return $path;
		}
	}

	function get_thumbs ($file_id)
	{
		return S3::getBucket($this->S3_bucket, $file_id.'/thumb');
	}

	function get_url ($uri)
	{
		return S3::getAuthenticatedURL($this->S3_bucket, $uri, 900, false, true);
	}

	function set_read ($dcid)
	{
		$q = $this->db->set('is_new',FALSE)->where('dcid',$dcid)->update('documents');

		if ($this->db->affected_rows() > 0) return true;
		else return false;
	}
}