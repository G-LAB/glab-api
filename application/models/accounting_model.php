<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * G-LAB Accounting Library for Code Igniter
 * Written by Ryan Brodkin
 * Copyright 2009
 */

class Accounting_model extends CI_Model {

	function getTrialBalance () {
		$accounts = $this->getAccounts(true);

		$debit = 0;
		$credit = 0;

		foreach ($accounts as $id=>$account) {
			if ($account['mode'] === 'd') $debit = $debit + $account['balance'];
			elseif ($account['mode'] === 'c') $credit = $credit + $account['balance'];
		}

		$trialBalance = $credit - $debit;

		return abs($trialBalance);
	}

	function getAccountBalance ($acid) {

		$this->load->database();

		$account = $this->getAccountInfo($acid);

		//print_r($ledger);
		$debit = 0;
		$credit = 0;

		$debit = $this->db->query('SELECT SUM(amount) as debit FROM acc_ledger WHERE acid_debit = '.$acid);
		$debit = $debit->row_array();
		$debit = $debit['debit'];

		$credit = $this->db->query('SELECT SUM(amount) as credit FROM acc_ledger WHERE acid_credit = '.$acid);
		$credit = $credit->row_array();
		$credit = $credit['credit'];

		if ($account['mode'] == 'c') 		return $credit - $debit;
		elseif ($account['mode'] == 'd') 	return $debit - $credit;
		else return false;
	}


	function getAccountInfo ($acid) {

		if (isset($acid) != true) return false;


		$this->load->database();

		$account = $this->db->query('SELECT * FROM acc_accounts WHERE acid = '.$acid);
		$account = $account->row_array();

		$acctype = $this->db->query('SELECT * FROM acc_account_types t WHERE t.typematch = '.$account['acctnum'][0]);
		$acctype = $acctype->row_array();

		return array_merge($account,$acctype);
	}

	function getAccounts ($getBalance=false,$mode='full') {

		$this->load->database();

		$accounts = $this->db->query('SELECT * FROM acc_accounts a LEFT JOIN acc_account_types t ON (LEFT(a.acctnum,1) = t.typematch) ORDER BY acctnum');
		$accounts = $accounts->result_array();

		if ($getBalance) {
			foreach ($accounts as $id=>$account) {
				$accounts[$id]['balance'] = $this->getAccountBalance($account['acid']);
			}
		}

		if ($mode=='full') return $accounts;
		elseif ($mode=='min') {
			foreach ($accounts as $account) {
				$acid = $account['acid'];
				$acctmin[$acid] = $account['description'];
			}
			return $acctmin;
		} elseif ($mode=='minck') {
			foreach ($accounts as $account) {
				if ($account['bank_type'] == 'c') {
					$acid = $account['acid'];
					$acctmin[$acid] = $account['acctnum'].' '.$account['description'];
				}
			}
			return $acctmin;
		} elseif ($mode=='minexp') {
			foreach ($accounts as $account) {
				if ($account['typematch'] > '4') {
					$acid = $account['acid'];
					$acctmin[$acid] = $account['acctnum'].' '.$account['description'];
				}
			}
			return $acctmin;
		}
	}

	function getJournal ($aid=null) {

		$this->load->database();
		$this->load->library('users');

		$query = 'SELECT l.*, dr.description as dracc, cr.description as cracc FROM acc_ledger l LEFT JOIN (acc_accounts dr, acc_accounts cr) ON l.acid_debit = dr.acid AND l.acid_credit = cr.acid';

		if ($aid != null) $query .= ' WHERE l.acid_debit = '.$aid.' OR l.acid_credit = '.$aid;

		$ledger = $this->db->query($query);
		$ledger = $ledger->result_array();

		return $ledger;
	}

	function getCheckRegister ($aid=null) {

		$this->load->database();
		$this->load->library('users');

		$query = 'SELECT * FROM acc_ledger l RIGHT JOIN acc_checks c ON (l.legid = c.legid) WHERE checknum IS NOT NULL ORDER BY `checknum` DESC';

		$ledger = $this->db->query($query);
		$ledger = $ledger->result_array();

		return $ledger;
	}

	function getCheckNum () {

		$this->load->database();
		$this->load->library('users');

		$query = 'SELECT MAX(checknum) + 1 as nextnum FROM `acc_checks`';

		$num = $this->db->query($query);
		$num = $num->row_array();

		return $num['nextnum'];
	}

	function getAccountNum ($acid) {

		$this->load->database();

		$account = $this->db->query('SELECT * FROM acc_accounts WHERE acid = '.$acid);
		$account = $account->row_array();

		return $account['acctnum'];
	}

	function getAccountID ($acctnum) {

		$this->load->database();

		$account = $this->db->query('SELECT * FROM acc_accounts WHERE acctnum = '.$acctnum);
		$account = $account->row_array();

		return $account['acid'];
	}

	function addLedgerEntry ($amount, $acid_debit, $acid_credit, $memo=null, $ivimid=null) {

		$data = array(
			'acid_debit' => $acid_debit,
			'acid_credit' => $acid_credit,
			'amount' => $amount,
			'memo' => $memo,
			'ivimid' => $ivimid,
			'event' => $this->event->log('acc_ledger_entry_new',$this->profile->current()->pid)
		);
		$this->db->insert('acc_ledger',$data);

		return $this->db->insert_id();
	}

	function addCheck ($data) {

		$this->load->database();

		$ledger = $this->addLedgerEntry($data['amount'], $data['account'], $data['account_source'], $memo=$data['memo']);

		// Process the date
		$data['date'] = strtotime($data['date']);

		$data['date'] = date('Y-m-d',$data['date']);

		//echo $data['date'];

		if ($ledger != null) $query = $this->db->query('INSERT INTO acc_checks SET legid = '.$ledger.', checknum = '.$data['checkNumber'].', payee = "'.$data['payee-name'].'", pid = '.$data['payee'].', checkDate = "'.$data['date'].'" ');

		if ($query) return $data;
		else return false;
	}

}