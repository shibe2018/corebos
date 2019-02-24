<?php
/*+**********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 ************************************************************************************/
require_once 'data/CRMEntity.php';
require_once 'data/Tracker.php';

class MsgTemplate extends CRMEntity {
	public $db;
	public $log;

	public $table_name = 'vtiger_msgtemplate';
	public $table_index= 'msgtemplateid';
	public $column_fields = array();

	/** Indicator if this is a custom module or standard module */
	public $IsCustomModule = true;
	public $HasDirectImageField = false;
	/**
	 * Mandatory table for supporting custom fields.
	 */
	public $customFieldTable = array('vtiger_msgtemplatecf', 'msgtemplateid');

	/**
	 * Mandatory for Saving, Include tables related to this module.
	 */
	public $tab_name = array('vtiger_crmentity', 'vtiger_msgtemplate', 'vtiger_msgtemplatecf');

	/**
	 * Mandatory for Saving, Include tablename and tablekey columnname here.
	 */
	public $tab_name_index = array(
		'vtiger_crmentity' => 'crmid',
		'vtiger_msgtemplate'   => 'msgtemplateid',
		'vtiger_msgtemplatecf' => 'msgtemplateid',
	);

	/**
	 * Mandatory for Listing (Related listview)
	 */
	public $list_fields = array(
		/* Format: Field Label => array(tablename => columnname) */
		// tablename should not have prefix 'vtiger_'
		'Reference'=> array('msgtemplate' => 'reference'),
		'msgt_language' => array('msgtemplate' => 'msgt_language'),
		'msgt_status' => array('msgtemplate' => 'msgt_status'),
		'msgt_type' => array('msgtemplate' => 'msgt_type'),
		'Assigned To' => array('crmentity' => 'smownerid'),
		'tags' => array('msgtemplate' => 'tags'),
	);
	public $list_fields_name = array(
		/* Format: Field Label => fieldname */
		'Reference'=> 'reference',
		'msgt_language' => 'msgt_language',
		'msgt_status' => 'msgt_status',
		'msgt_type' => 'msgt_type',
		'Assigned To' => 'assigned_user_id',
		'tags' => 'tags',
	);

	// Make the field link to detail view from list view (Fieldname)
	public $list_link_field = 'reference';

	// For Popup listview and UI type support
	public $search_fields = array(
		/* Format: Field Label => array(tablename => columnname) */
		// tablename should not have prefix 'vtiger_'
		'Reference'=> array('msgtemplate' => 'reference'),
		'msgt_language' => array('msgtemplate' => 'msgt_language'),
		'msgt_status' => array('msgtemplate' => 'msgt_status'),
		'msgt_type' => array('msgtemplate' => 'msgt_type'),
		'Assigned To' => array('crmentity' => 'smownerid'),
		'tags' => array('msgtemplate' => 'tags'),
	);
	public $search_fields_name = array(
		/* Format: Field Label => fieldname */
		'Reference'=> 'reference',
		'msgt_language' => 'msgt_language',
		'msgt_status' => 'msgt_status',
		'msgt_type' => 'msgt_type',
		'Assigned To' => 'assigned_user_id',
		'tags' => 'tags',
	);

	// For Popup window record selection
	public $popup_fields = array('reference');

	// Placeholder for sort fields - All the fields will be initialized for Sorting through initSortFields
	public $sortby_fields = array();

	// For Alphabetical search
	public $def_basicsearch_col = 'reference';

	// Column value to use on detail view record text display
	public $def_detailview_recname = 'reference';

	// Required Information for enabling Import feature
	public $required_fields = array('reference'=>1);

	// Callback function list during Importing
	public $special_functions = array('set_import_assigned_user');

	public $default_order_by = 'reference';
	public $default_sort_order='ASC';
	// Used when enabling/disabling the mandatory fields for the module.
	// Refers to vtiger_field.fieldname values.
	public $mandatory_fields = array('createdtime', 'modifiedtime', 'reference');

	public function save_module($module) {
		if ($this->HasDirectImageField) {
			$this->insertIntoAttachment($this->id, $module);
		}
	}

	/**
	 * Invoked when special actions are performed on the module.
	 * @param String Module name
	 * @param String Event Type (module.postinstall, module.disabled, module.enabled, module.preuninstall)
	 */
	public function vtlib_handler($modulename, $event_type) {
		if ($event_type == 'module.postinstall') {
			// TODO Handle post installation actions
			$this->setModuleSeqNumber('configure', $modulename, 'MSGT-', '0000001');
			global $adb, $default_charset, $current_user;
			$rsAccs = $adb->query("show TABLES like 'vtiger_emailtemplates'");
			if ($adb->num_rows($rsAccs)>0) {
				$rsAccs = $adb->query('SELECT * FROM vtiger_emailtemplates WHERE deleted = 0');
				while ($acc = $adb->fetch_array($rsAccs)) {
					$focus = new MsgTemplate();
					$focus->id = '';
					$focus->mode = '';
					$focus->column_fields['reference'] = html_entity_decode($acc['templatename'], ENT_QUOTES, $default_charset);
					$focus->column_fields['msgt_type'] = 'Email';
					$focus->column_fields['msgt_status'] = 'Active';
					$focus->column_fields['msgt_language'] = 'en';
					$focus->column_fields['subject'] = html_entity_decode($acc['subject'], ENT_QUOTES, $default_charset);
					$focus->column_fields['template'] = html_entity_decode($acc['body'], ENT_QUOTES, $default_charset);
					$focus->column_fields['templateonlytext'] = html_entity_decode(strip_tags(html_entity_decode($acc['body'], ENT_QUOTES, $default_charset)), ENT_QUOTES, $default_charset);
					$focus->column_fields['tags'] = '';
					$focus->column_fields['description'] = html_entity_decode($acc['description'], ENT_QUOTES, $default_charset);
					$focus->column_fields['assigned_user_id'] = $current_user->id;
					$_REQUEST['assigntype'] = 'U';
					$focus->save('MsgTemplate');
				}
				$adb->query("delete from vtiger_settings_field where name='EMAILTEMPLATES'");
			}
			$rsAccs = $adb->query("show TABLES like 'vtiger_actions'");
			if ($adb->num_rows($rsAccs)>0) {
				$rsAccs = $adb->query('SELECT * FROM vtiger_actions INNER join vtiger_crmentity ON crmid=actionsid WHERE deleted=0');
				while ($acc = $adb->fetch_array($rsAccs)) {
					$focus = new MsgTemplate();
					$focus->id = '';
					$focus->mode = '';
					$focus->column_fields['reference'] = html_entity_decode($acc['reference'], ENT_QUOTES, $default_charset);
					$focus->column_fields['msgt_type'] = $acc['action_type'];
					$focus->column_fields['msgt_status'] = $acc['action_status'];
					$focus->column_fields['msgt_language'] = $acc['action_language'];
					$focus->column_fields['subject'] = html_entity_decode($acc['subject'], ENT_QUOTES, $default_charset);
					$focus->column_fields['template'] = html_entity_decode($acc['template'], ENT_QUOTES, $default_charset);
					$focus->column_fields['templateonlytext'] = html_entity_decode(strip_tags(html_entity_decode($acc['templateonlytext'], ENT_QUOTES, $default_charset)), ENT_QUOTES, $default_charset);
					$focus->column_fields['tags'] = $acc['tags'];
					$focus->column_fields['description'] = html_entity_decode($acc['description'], ENT_QUOTES, $default_charset);
					$focus->column_fields['assigned_user_id'] = $acc['smownerid'];
					$_REQUEST['assigntype'] = 'U';
					$focus->save('MsgTemplate');
				}
			}
			include_once 'modules/cbMap/cbMap.php';
			$focusnew = new cbMap();
			$focusnew->column_fields['assigned_user_id'] = Users::getActiveAdminID();
			$focusnew->column_fields['mapname'] = 'MsgTemplate_FieldInfo';
			$focusnew->column_fields['maptype'] = 'FieldInfo';
			$focusnew->column_fields['targetname'] = 'MsgTemplate';
			$focusnew->column_fields['content'] = '<map>
<originmodule>
<originname>MsgTemplate</originname>
</originmodule>
<fields>
<field>
  <fieldname>template</fieldname>
  <features>
	<feature>
	  <name>RTE</name>
	  <value>1</value>
	</feature>
  </features>
</field>
</fields>
</map>';
			$focusnew->save('cbMap');
			$focusnew = new cbMap();
			$focusnew->column_fields['assigned_user_id'] = Users::getActiveAdminID();
			$focusnew->column_fields['mapname'] = 'MsgTemplate_FieldDependency';
			$focusnew->column_fields['maptype'] = 'FieldDependency';
			$focusnew->column_fields['targetname'] = 'MsgTemplate';
			$focusnew->column_fields['content'] = '<map>
<originmodule>
<originname>MsgTemplate</originname>
</originmodule>
<dependencies>
<dependency>
	<field>msgt_module</field>
	<actions>
		<function>
			<field>msgt_module</field>
			<name>msgtFillInModuleFields</name>
		</function>
	</actions>
</dependency>
<dependency>
	<field>msgt_fields</field>
	<actions>
		<function>
			<field>msgt_fields</field>
			<name>msgtInsertIntoMsg</name>
		</function>
	</actions>
</dependency>
</dependencies>
</map>';
			$focusnew->save('cbMap');
		} elseif ($event_type == 'module.disabled') {
			// TODO Handle actions when this module is disabled.
		} elseif ($event_type == 'module.enabled') {
			// TODO Handle actions when this module is enabled.
		} elseif ($event_type == 'module.preuninstall') {
			// TODO Handle actions when this module is about to be deleted.
		} elseif ($event_type == 'module.preupdate') {
			// TODO Handle actions before this module is updated.
		} elseif ($event_type == 'module.postupdate') {
			// TODO Handle actions after this module is updated.
		}
	}

	/**
	 * Handle saving related module information.
	 * NOTE: This function has been added to CRMEntity (base class).
	 * You can override the behavior by re-defining it here.
	 */
	// public function save_related_module($module, $crmid, $with_module, $with_crmid) { }

	/**
	 * Handle deleting related module information.
	 * NOTE: This function has been added to CRMEntity (base class).
	 * You can override the behavior by re-defining it here.
	 */
	//public function delete_related_module($module, $crmid, $with_module, $with_crmid) { }

	/**
	 * Handle getting related list information.
	 * NOTE: This function has been added to CRMEntity (base class).
	 * You can override the behavior by re-defining it here.
	 */
	//public function get_related_list($id, $cur_tab_id, $rel_tab_id, $actions=false) { }

	/**
	 * Handle getting dependents list information.
	 * NOTE: This function has been added to CRMEntity (base class).
	 * You can override the behavior by re-defining it here.
	 */
	//public function get_dependents_list($id, $cur_tab_id, $rel_tab_id, $actions=false) { }
}
?>
