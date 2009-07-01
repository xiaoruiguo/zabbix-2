<?php
/**
 * File containing CTemplate class for API.
 * @package API
 */
/**
 * Class containing methods for operations with Templates
 *
 */
class CTemplate {

	public static $error;

	/**
	 * Get Template data
	 *
	 * {@source}
	 * @access public
	 * @static
	 * @since 1.8
	 * @version 1
	 *
	 * @static
	 * @param array $options
	 * @return array|boolean Template data as array or false if error
	 */
	public static function get($options = array()) {
		global $USER_DETAILS;

		$result = array();
		$user_type = $USER_DETAILS['type'];
		$userid = $USER_DETAILS['userid'];
		
		$sort_columns = array('hostid'); // allowed columns for sorting
		
		$sql_parts = array(
			'select' => array('h.hostid, h.host'),
			'from' => array('hosts h'),
			'where' => array('h.status=3'),
			'order' => array(),
			'limit' => null);

		$def_options = array(
			'nodeids'		=> array(),
			'groupids'		=> array(),
			'templateids'	=> array(),
			'with_items'	=> false,
			'with_triggers'	=> false,
			'with_graphs'	=> false,
			'editable' 		=> false,
			'count'			=> false,
			'pattern'		=> '',
			'order'			=> '',
			'limit'			=> null);

		$options = array_merge($def_options, $options);
		
// editable + PERMISSION CHECK
		if(USER_TYPE_SUPER_ADMIN != $user_type){
			if($options['editable']){
				$permission = PERM_READ_WRITE;
			}
			else{
				$permission = PERM_READ_ONLY;
			}
			
			$sql_parts['from']['hg'] = 'hosts_groups hg';
			$sql_parts['from'][] = 'rights r, users_groups g';
			$sql_parts['where']['hgh'] = 'hg.hostid=h.hostid';
			$sql_parts['where'][] = '
					r.id=hg.groupid
					AND r.groupid=g.usrgrpid
					AND g.userid='.$userid.'
					AND r.permission>'.($permission-1).'
					AND NOT EXISTS(
						SELECT hgg.groupid
						FROM hosts_groups hgg, rights rr, users_groups gg
						WHERE hgg.hostid=hg.hostid
							AND rr.id=hgg.groupid
							AND rr.groupid=gg.usrgrpid
							AND gg.userid='.$userid.'
							AND rr.permission<'.$permission.')';
		}
// count
		if($options['count']){
			$sql_parts['select'] = array('count(h.hostid) as rowscount');
		}
// nodeids
		$nodeids = $options['nodeids'] ? $options['nodeids'] : get_current_nodeid(false);
// groupids
		if($options['groupids']){
			zbx_value2array($options['groupids']);
			$sql_parts['from']['hg'] = 'hosts_groups hg';
			$sql_parts['where'][] = DBcondition('hg.groupid', $options['groupids']);
			$sql_parts['where']['hgh'] = 'hg.hostid=h.hostid';
		}
// templateids
		if($options['templateids']){
			zbx_value2array($options['templateids']);
			$sql_parts['where'][] = DBcondition('h.hostid', $options['templateids']);
		}
// with_items
		if($options['with_items']){
			$sql_parts['where'][] = 'EXISTS (SELECT i.hostid FROM items i WHERE h.hostid=i.hostid )';
		}
// with_triggers
		if($options['with_triggers']){
			$sql_parts['where'][] = 'EXISTS( 
					SELECT i.itemid
					FROM items i, functions f, triggers t
					WHERE i.hostid=h.hostid 
						AND i.itemid=f.itemid 
						AND f.triggerid=t.triggerid)';
		}
// with_graphs
		if($options['with_graphs']){
			$sql_parts['where'][] = 'EXISTS( 
					SELECT DISTINCT i.itemid 
					FROM items i, graphs_items gi 
					WHERE i.hostid=h.hostid 
						AND i.itemid=gi.itemid)';
		}
// pattern
		if(!zbx_empty($options['pattern'])){
			$sql_parts['where'][] = ' UPPER(h.host) LIKE '.zbx_dbstr('%'.strtoupper($options['pattern']).'%');
		}
// order
		// restrict not allowed columns for sorting
		$options['order'] = in_array($options['order'], $sort_columns) ? $options['order'] : '';
		if(!zbx_empty($options['order'])){
			$sql_parts['order'][] = 'h.'.$options['order'];
		}
// limit
		if(zbx_ctype_digit($options['limit']) && $options['limit']){
			$sql_parts['limit'] = $options['limit'];
		}
		
		
		$sql_select = implode(',', $sql_parts['select']);
		$sql_from = implode(',', $sql_parts['from']);
		$sql_where = implode(' AND ', $sql_parts['where']);
		$sql_order = zbx_empty($options['order']) ? '' : ' ORDER BY '.implode(',',$sql_parts['order']);
		$sql_limit = $sql_parts['limit'];

		$sql = 'SELECT DISTINCT '.$sql_select.'
				FROM '.$sql_from.'
				WHERE '.DBin_node('h.hostid', $nodeids).
				($sql_where ? ' AND '.$sql_where : '').
				$sql_order;
		$res = DBselect($sql, $sql_limit);
		
		while($template = DBfetch($res)){
			if($options['count'])
				$result = $template;
			else
				$result[$template['hostid']] = $template;
		}
		
	return $result;
	}

	/**
	 * Gets all Template data from DB by Template ID
	 *
	 * {@source}
	 * @access public
	 * @static
	 * @since 1.8
	 * @version 1
	 *
	 * @static
	 * @param _array $template_data
	 * @param array $template_data['templateid']
	 * @return array|boolean template data as array or false if error
	 */
	public static function getById($template_data){
		$sql = 'SELECT * FROM hosts WHERE hostid='.$template_data['templateid'].' AND status=3';
		$template = DBfetch(DBselect($sql));

		$result = $template ? true : false;
		if($result)
			return $template;
		else{
			self::$error = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Internal zabbix error');
			return false;
		}
	}

	/**
	 * Get Template ID by Template name
	 *
	 * {@source}
	 * @access public
	 * @static
	 * @since 1.8
	 * @version 1
	 *
	 * @param array $template_data
	 * @param array $template_data['template']
	 * @return string templateid
	 */
	public static function getId($template_data){
		$sql = 'SELECT hostid FROM hosts '.
			' WHERE host='.zbx_dbstr($template_data['name']).
				' AND status=3 '.
				' AND '.DBin_node('hostid', get_current_nodeid(false));
		$templateid = DBfetch(DBselect($sql));

		$result = $templateid ? true : false;
		if($result)
			return $templateid['hostid'];
		else{
			self::$error = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Internal zabbix error');
			return false;
		}
	}

	/**
	 * Add Template
	 *
	 * {@source}
	 * @access public
	 * @static
	 * @since 1.8
	 * @version 1
	 *
	 * @param _array $templates multidimensional array with templates data
	 * @param string $templates['host']
	 * @param string $templates['port']
	 * @param string $templates['status']
	 * @param string $templates['useip']
	 * @param string $templates['dns']
	 * @param string $templates['ip']
	 * @param string $templates['proxy_hostid']
	 * @param string $templates['useipmi']
	 * @param string $templates['ipmi_ip']
	 * @param string $templates['ipmi_port']
	 * @param string $templates['ipmi_authtype']
	 * @param string $templates['ipmi_privilege']
	 * @param string $templates['ipmi_username']
	 * @param string $templates['ipmi_password']
	 * @return boolean
	 */
	public static function add($templates){
		$tpls = null;
		$newgroup = '';
		$status = 3;

		$templateids = array();

		$result = false;

		DBstart(false);
		foreach($templates as $template){
		
			if(empty($template['groupids'])){
				$result = false;
				$error = 'No groups for host [ '.$template['host'].' ]';
				break;
			}

			$host_db_fields = array(
				'host' => null,
				'port' => 0,
				'status' => 0,
				'useip' => 0,
				'dns' => '',
				'ip' => '0.0.0.0',
				'proxy_hostid' => 0,
				'useipmi' => 0,
				'ipmi_ip' => '',
				'ipmi_port' => 623,
				'ipmi_authtype' => 0,
				'ipmi_privilege' => 0,
				'ipmi_username' => '',
				'ipmi_password' => '',
			);

			if(!check_db_fields($host_db_fields, $template)){
				$result = false;
				break;
			}

			$result = add_host($template['host'], $template['port'], $status, $template['useip'], $template['dns'], $template['ip'],
				$template['proxy_hostid'], $tpls, $template['useipmi'], $template['ipmi_ip'], $template['ipmi_port'], $template['ipmi_authtype'],
				$template['ipmi_privilege'], $template['ipmi_username'], $template['ipmi_password'], $newgroup, $template['groupids']);
			if(!$result) break;
			$templateids[$result] = $result;
		}
		$result = DBend($result);

		if($result)
			return $templateids;
		else{
			self::$error = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Internal zabbix error');
			return $result;
		}
	}

	/**
	 * Update Template
	 *
	 * {@source}
	 * @access public
	 * @static
	 * @since 1.8
	 * @version 1
	 *
	 * @param array $templates multidimensional array with templates data
	 * @return boolean
	 */
	public static function update($templates){
		$tpls = null;
		$newgroup = '';
		$status = 3;

		$templateids = array();
		$result = false;

		DBstart(false);
		foreach($templates as $template){

			$sql = 'SELECT DISTINCT * '.
				' FROM hosts '.
				' WHERE hostid='.$template['hostid'];

			$host_db_fields = DBfetch(DBselect($sql));

			if(!isset($host_db_fields)) {
				$result = false;
				break;
			}

			if(!check_db_fields($host_db_fields, $template)){
				error('Incorrect arguments pasted to function [CTemplate::update]');
				$result = false;
				break;
			}
			
			$groups = get_groupids_by_host($template['hostid']);

			$result = update_host($template['hostid'], $template['host'], $template['port'], $status, $template['useip'], $template['dns'], $template['ip'],
				$template['proxy_hostid'], $tpls, $template['useipmi'], $template['ipmi_ip'], $template['ipmi_port'], $template['ipmi_authtype'],
				$template['ipmi_privilege'], $template['ipmi_username'], $template['ipmi_password'], $newgroup, $groups);
			if(!$result) break;
			$templateids[$result] = $result;
		}
		$result = DBend($result);

		if($result)
			return $templateids;
		else{
			self::$error = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Internal zabbix error');
			return false;
		}

	}

	/**
	 * Delete Template
	 *
	 * {@source}
	 * @access public
	 * @static
	 * @since 1.8
	 * @version 1
	 *
	 * @param array $templateids
	 * @return boolean
	 */
	public static function delete($templateids){
		$result = delete_host($templateids, false);
		if($result)
			return $templateids;
		else{
			self::$error = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Internal zabbix error');
			return false;
		}
	}


	/**
	 * Link Template to Hosts
	 *
	 * {@source}
	 * @access public
	 * @static
	 * @since 1.8
	 * @version 1
	 *
	 * @param array $data
	 * @param string $data['templateid']
	 * @param array $data['hostids']
	 * @return boolean
	 */
	public static function linkHosts($data){
		$result = false;
		$error = '';

		$templateid = $data['templateid'];
		$hostids = $data['hostids'];
		DBstart(false);

		foreach($hostids as $hostid){
			$hosttemplateid = get_dbid('hosts_templates', 'hosttemplateid');
			if(!$result = DBexecute('INSERT INTO hosts_templates VALUES ('.$hosttemplateid.','.$hostid.','.$templateid.')')){
				$error = 'DBexecute';
				break;
			}
		}

		if($result) {
			foreach($hostids as $hostid){
				$result = sync_host_with_templates($hostid, $templateid);
				if(!$result) {
					$error = 'sync_host_with_templates';
					break;
				}
			}
		}
		$result = DBend($result);

		if($result)
			return true;
		else{
			self::$error = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => $error);
			return false;
		}
	}

	/**
	 * Unlink Hosts from Templates
	 *
	 * {@source}
	 * @access public
	 * @static
	 * @since 1.8
	 * @version 1
	 *
	 * @param _array $data
	 * @param string $data['templateid']
	 * @param array $data['hostids']
	 * @param boolean $data['clean']
	 * @return boolean
	 */
	public static function unlinkHosts($data){
		$templateid = $data['templateid'];
		$hostids = $data['hostids'];
		$clean = isset($data['clean']);

		foreach($hostids as $hostid) {
			$result = delete_template_elements($hostid, array($templateid), $clean);
		}
		$result&= DBexecute('DELETE FROM hosts_templates WHERE templateid='.$templateid.' AND '.DBcondition('hostid',$hostids));

		if($result)
			return true;
		else{
			self::$error = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => $error);
			return false;
		}
	}

	/**
	 * Link Host to Templates
	 *
	 * {@source}
	 * @access public
	 * @static
	 * @since 1.8
	 * @version 1
	 *
	 * @param array $data
	 * @param string $data['hostid']
	 * @param array $data['templateids']
	 * @return boolean
	 */
	public static function linkTemplates($data){
		$result = false;

		$hostid = $data['hostid'];
		$templateids = $data['templateids'];

		DBstart(false);

		foreach($templateids as $templateid){
			$hosttemplateid = get_dbid('hosts_templates', 'hosttemplateid');
			if(!$result = DBexecute('INSERT INTO hosts_templates VALUES ('.$hosttemplateid.','.$hostid.','.$templateid.')'))
				break;
		}

		if($result) {
			foreach($templateids as $templateid){
				$result = sync_host_with_templates($hostid, $templateid);
				if(!$result) break;
			}
		}
		$result = DBend($result);

		if($result)
			return true;
		else{
			self::$error = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => $error);
			return false;
		}
	}

	/**
	 * Unlink Templates from Host
	 *
	 * {@source}
	 * @access public
	 * @static
	 * @since 1.8
	 * @version 1
	 *
	 * @param string $data
	 * @param string $data['templateid']
	 * @param array $data['hostids']
	 * @param boolean $data['clean'] whether to wipe all info from template elements.
	 * @return boolean
	 */
	public static function unlinkTemplates($data){
		$templateid = $data['templateid'];
		$hostids = $data['hostids'];
		$clean = isset($data['clean']);

		$result = delete_template_elements($hostid, $templateids, $clean);
		$result&= DBexecute('DELETE FROM hosts_templates WHERE hostid='.$hostid.' AND '.DBcondition('templateid',$templateids));

		if($result)
			return true;
		else{
			self::$error = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => $error);
			return false;
		}
	}

}
?>
