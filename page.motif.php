<?php
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }
isset($_REQUEST['action']) ? $action = $_REQUEST['action'] : $action = 'add';

global $astman;

if($action == 'delete') {
	//Get settings from DB and see if we created a trunk
	$sql = 'SELECT * FROM `motif` WHERE `id` = '.mysql_real_escape_string($_REQUEST['id']);
	$a = sql($sql, 'getRow', DB_FETCHMODE_ASSOC);
	$s = unserialize($a['settings']);
	
	//If we created a trunk then delete it
	if(isset($s['trunk_number'])) {
		core_trunks_del($s['trunk_number']);
	}
	
	//If we created a route then delete it
	if(isset($s['route_number'])) {
		core_routing_delbyid($s['route_number']);
	}
	
	//Delete our settings from out own DB
	$sql = "DELETE FROM `motif` WHERE id = ".mysql_real_escape_string($_REQUEST['id']);
	sql($sql);
	$action = 'add';
	needreload();
}

//Check to see if Asterisk is running along with chan_motif and res_xmpp
if($astman && $astman->connected() && $astman->mod_loaded('motif') && $astman->mod_loaded('xmpp')) {
	if(isset($_REQUEST['username'])) {
		$pn = isset($_REQUEST['number']) ? mysql_real_escape_string($_REQUEST['number']) : '';
		$un = isset($_REQUEST['username']) ? mysql_real_escape_string($_REQUEST['username']) : '';
		$pw = isset($_REQUEST['password']) ? mysql_real_escape_string($_REQUEST['password']) : '';
		
		//Add '@gmail.com' if not already appended.
		$un = preg_match('/@/i',$un) ? $un : $un . '@gmail.com';
		
		$settings = array();
		//Check trunk/Routes values
		$settings['trunk'] = isset($_REQUEST['trunk']) ? true : false;
		$settings['route'] = isset($_REQUEST['route']) ? true : false;
		
		//Check to make sure all values are set and not empty
		if(!empty($pn) && !empty($un) && !empty($pw)) {
			//Add/Remove Trunk Values
			//The dial String
			$dialstring = 'Motif/g'.str_replace('@','',str_replace('.','',$un)).'/$OUTNUM$@voice.google.com';
			if($settings['trunk'] && $action == 'add') {
				$trunknum = core_trunks_add('custom', 	$dialstring, '', '', $pn, '', 'notneeded', '', '', 'off', '', 'off', 'GVM_' . $pn, '', 'off', 'r');
				$settings['trunk_number'] = $trunknum;
			} elseif($settings['trunk'] && $action == 'edit') {
				$sql = 'SELECT * FROM `motif` WHERE `id` = '.mysql_real_escape_string($_REQUEST['id']);
				$a = sql($sql, 'getRow', DB_FETCHMODE_ASSOC);
				$s = unserialize($a['settings']);
				if(isset($s['trunk_number']) && core_trunks_getTrunkTrunkName($s['trunk_number'])) {
					core_trunks_edit($s['trunk_number'], $dialstring, '', '', $pn, '', 'notneeded', '', '', 'off', '', 'off', 'GVM_' . $pn, '', 'off', 'r');
					$settings['trunk_number'] = $s['trunk_number'];
				} else {
					$trunknum = core_trunks_add('custom', $dialstring, '', '', $pn, '', 'notneeded', '', '', 'off', '', 'off', 'GVM_' . $pn, '', 'off', 'r');
					$settings['trunk_number'] = $trunknum;
				}
			} elseif(!$settings['trunk'] && $action == 'edit') {
				$sql = 'SELECT * FROM `motif` WHERE `id` = '.mysql_real_escape_string($_REQUEST['id']);
				$a = sql($sql, 'getRow', DB_FETCHMODE_ASSOC);
				$s = unserialize($a['settings']);
				if(isset($s['trunk_number'])) {
					core_trunks_del($s['trunk_number']);
				}
			}
		
			//Add/Remove Route Values
			$dialpattern[] = array(
	            'prepend_digits' => '1',
	            'match_pattern_prefix' => '',
	            'match_pattern_pass' => 'NXXNXXXXXX',
	            'match_cid' => '',
	        );
			$routename = str_replace('@','',str_replace('.','',$un));;
			if($settings['route'] && $action == 'add') {
				if(isset($settings['trunk_number'])) {
					$routenum = core_routing_addbyid($routename, '', '', '', '', '', 'default', '', $dialpattern, array($settings['trunk_number']));
					$settings['route_number'] = $routenum;
				}
			} elseif($settings['route'] && $action == 'edit') {
				$sql = 'SELECT * FROM `motif` WHERE `id` = '.mysql_real_escape_string($_REQUEST['id']);
				$a = sql($sql, 'getRow', DB_FETCHMODE_ASSOC);
				$s = unserialize($a['settings']);
				if(isset($s['trunk_number']) && isset($s['route_number'])) {
					core_routing_editbyid($s['route_number'], $routename, '', '', '', '', '', 'default', '', $dialpattern, array($s['trunk_number']));
					$settings['route_number'] = $s['route_number'];
				} elseif(isset($settings['trunk_number'])) {
					$routenum = core_routing_addbyid($routename, '', '', '', '', '', 'default', '', $dialpattern, array($settings['trunk_number']));
					$settings['route_number'] = $routenum;
				}
			} elseif(!$settings['route'] && $action == 'edit') {
				$sql = 'SELECT * FROM `motif` WHERE `id` = '.mysql_real_escape_string($_REQUEST['id']);
				$a = sql($sql, 'getRow', DB_FETCHMODE_ASSOC);
				$s = unserialize($a['settings']);
				if(isset($s['route_number'])) {
					core_routing_delbyid($s['route_number']);
				}
			}
		
			//Prepare settings to be stored in the database
			$settings = serialize($settings);

			if($action == 'add') {
				$sql = "INSERT INTO `motif` (`phonenum`, `username`, `password`, `settings`) VALUES ('" . $pn . "', '" . $un . "', '" . $pw . "', '" . $settings . "')";
			} elseif($action == 'edit') {
				$sql = "UPDATE `motif` SET `phonenum` = '".$pn."', `username` = '".$un."', `password` = '".$pw."', `settings` = '".$settings."' WHERE id = " . mysql_real_escape_string($_REQUEST['id']);
			}
			sql($sql);
			needreload();
		}
	}
	
	$sql = 'SELECT * FROM `motif`';
	$accounts = sql($sql, 'getAll', DB_FETCHMODE_ASSOC);
	
	//If editing then let's get some important data back
	if($action == 'edit') {
		$sql = 'SELECT * FROM `motif` WHERE `id` = '.mysql_real_escape_string($_REQUEST['id']);
		$account = sql($sql, 'getRow', DB_FETCHMODE_ASSOC);
		//print_r($account);
		$form_password = $account['password'];
		$form_username = $account['username'];
		$form_number = $account['phonenum'];
		
		$settings = unserialize($account['settings']);
		$form_trunk = $settings['trunk'];
		$form_route = $settings['route'];
		$id = $account['id'];
		
		$r = $astman->command("xmpp show connections");
		$status['connected'] = false;
		$context = str_replace('@','',str_replace('.','',$account['username']));
		if(preg_match('/\[g'.$context.'\] '.$account['username'].'.* (Connected)/i',$r['data'],$matches)) {
			$status['connected'] = true;
		};
		
		$r = $astman->command("xmpp show buddies");
		preg_match_all('/Client: g'.$context.'\n(?:.|\n)*/i',$r['data'],$client);
		preg_match_all('/Buddy:(.*)/i',$client[0][0],$matches);
		$buddies = array();
		foreach($matches[1] as $data) {
			if(!preg_match('/@public.talk.google.com/i',$data)) {
				$buddies[] = $data;
			}
		}		
		
	}
	include('views/main.php');
	include('views/edit.php');
} else {
	echo "<h3>This Module Requires Asterisk mod_motif & mod_xmpp to be installed and loaded</h3>";
}

/*
jabber list nodes=xmpp list nodes
jabber purge nodes=xmpp purge nodes
jabber delete node=xmpp delete node
jabber create collection=xmpp create collection
jabber create leaf=xmpp create leaf
jabber set debug=xmpp set debug
jabber show connections=xmpp show connections
jabber show buddies=xmpp show buddies
*/
