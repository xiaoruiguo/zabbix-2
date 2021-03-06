<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


$actionWidget = new CWidget();

// create new action button
$createForm = new CForm('get');
$createForm->cleanItems();
$createForm->addVar('eventsource', $this->data['eventsource']);
$createForm->addItem(new CSubmit('form', _('Create action')));
$actionWidget->addPageHeader(_('CONFIGURATION OF ACTIONS'), $createForm);

// create widget header
$sourceComboBox = new CComboBox('eventsource', $this->data['eventsource'], 'submit()');
$sourceComboBox->addItem(EVENT_SOURCE_TRIGGERS, _('Triggers'));
$sourceComboBox->addItem(EVENT_SOURCE_DISCOVERY, _('Discovery'));
$sourceComboBox->addItem(EVENT_SOURCE_AUTO_REGISTRATION, _('Auto registration'));
$sourceComboBox->addItem(EVENT_SOURCE_INTERNAL, _x('Internal', 'event source'));
$filterForm = new CForm('get');
$filterForm->addItem(array(_('Event source'), SPACE, $sourceComboBox));

$actionWidget->addHeader(_('Actions'), $filterForm);
$actionWidget->addHeaderRowNumber();

// create form
$actionForm = new CForm();
$actionForm->setName('actionForm');

// create table
$actionTable = new CTableInfo(_('No actions found.'));
$actionTable->setHeader(array(
	new CCheckBox('all_items', null, "checkAll('".$actionForm->getName()."', 'all_items', 'g_actionid');"),
	make_sorting_header(_('Name'), 'name', $this->data['sort'], $this->data['sortorder']),
	_('Conditions'),
	_('Operations'),
	make_sorting_header(_('Status'), 'status', $this->data['sort'], $this->data['sortorder'])
));

if ($this->data['actions']) {
	$actionConditionStringValues = actionConditionValueToString($this->data['actions'], $this->data['config']);
	$actionOperationDescriptions = getActionOperationDescriptions($this->data['actions']);

	foreach ($this->data['actions'] as $aIdx => $action) {
		$conditions = array();
		$operations = array();

		order_result($action['filter']['conditions'], 'conditiontype', ZBX_SORT_DOWN);

		foreach ($action['filter']['conditions'] as $cIdx => $condition) {
			$conditions[] = getConditionDescription($condition['conditiontype'], $condition['operator'],
				$actionConditionStringValues[$aIdx][$cIdx]
			);
			$conditions[] = BR();
		}

		sortOperations($this->data['eventsource'], $action['operations']);

		foreach ($action['operations'] as $oIdx => $operation) {
			$operations[] = $actionOperationDescriptions[$aIdx][$oIdx];
		}

		if ($action['status'] == ACTION_STATUS_DISABLED) {
			$status = new CLink(_('Disabled'),
				'actionconf.php?action=action.massenable&g_actionid[]='.$action['actionid'].url_param('eventsource'),
				'disabled'
			);
		}
		else {
			$status = new CLink(_('Enabled'),
				'actionconf.php?action=action.massdisable&g_actionid[]='.$action['actionid'].url_param('eventsource'),
				'enabled'
			);
		}

		$actionTable->addRow(array(
			new CCheckBox('g_actionid['.$action['actionid'].']', null, null, $action['actionid']),
			new CLink($action['name'], 'actionconf.php?form=update&actionid='.$action['actionid']),
			$conditions,
			new CCol($operations, 'wraptext'),
			$status
		));
	}
}

// append table to form
$actionForm->addItem(array(
	$this->data['paging'],
	$actionTable,
	$this->data['paging'],
	get_table_header(new CActionButtonList('action', 'g_actionid', array(
		'action.massenable' => array('name' => _('Enable'), 'confirm' => _('Enable selected actions?')),
		'action.massdisable' => array('name' => _('Disable'), 'confirm' => _('Disable selected actions?')),
		'action.massdelete' => array('name' => _('Delete'), 'confirm' => _('Delete selected actions?'))
	)))
));

// append form to widget
$actionWidget->addItem($actionForm);

return $actionWidget;
