<?php
/*************************************************************************************************
 * Copyright 2018 JPL TSolucio, S.L. -- This file is a part of TSOLUCIO coreBOS Customizations.
* Licensed under the vtiger CRM Public License Version 1.1 (the "License"); you may not use this
* file except in compliance with the License. You can redistribute it and/or modify it
* under the terms of the License. JPL TSolucio, S.L. reserves all rights not expressly
* granted by the License. coreBOS distributed by JPL TSolucio S.L. is distributed in
* the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
* warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. Unless required by
* applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT ANY WARRANTIES OR CONDITIONS OF ANY KIND,
* either express or implied. See the License for the specific language governing
* permissions and limitations under the License. You may obtain a copy of the License
* at <http://corebos.org/documentation/doku.php?id=en:devel:vpl11>
*************************************************************************************************/

class addRolePicklistToGlobalVariable extends cbupdaterWorker {

	public function applyChange() {
		global $adb;
		if ($this->hasError()) {
			$this->sendError();
		}
		if ($this->isApplied()) {
			$this->sendMsg('Changeset '.get_class($this).' already applied!');
		} else {
			$mod = Vtiger_Module::getInstance('GlobalVariable');
			$fld = Vtiger_Field::getInstance('rolegv', $mod);
			if ($fld) {
				$this->ExecuteQuery('update vtiger_field set presence=2 where fieldid=?', array($fld->id));
			} else {
				$block = Vtiger_Block::getInstance('LBL_GLOBAL_VARIABLE_INFORMATION', $mod);
				$rolefield = new Vtiger_Field();
				$rolefield->name = 'rolegv';
				$rolefield->label = 'Role';
				$rolefield->column = 'rolegv';
				$rolefield->columntype = 'varchar(215)';
				$rolefield->typeofdata = 'V~O';
				$rolefield->uitype = '1024';
				$rolefield->masseditable = '2';
				$block->addField($rolefield);
			}
			$this->sendMsg('Changeset '.get_class($this).' applied!');
			$this->markApplied(false);
		}
		$this->finishExecution();
	}
}
