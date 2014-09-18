<?php
/*************************************************************************************************
 * Copyright 2014 JPL TSolucio, S.L. -- This file is a part of TSOLUCIO coreBOS Customizations.
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

namespace tsolucio\ComposerInstall;
use ComposerScriptEvent;

$Vtiger_Utils_Log = false;

class ComposerInstall
{
	public static function postPackageInstall($event) {
		$installedPackage = $event->getComposer()->getPackage();
		$io = $event->getIO();
		$installs = $installedPackage->getRequires();
		foreach ($installs as $pkg=>$info) {
			$target = $info->getTarget();
			$dir = ComposerInstall::getRealDirName($target);
			if (file_exists($dir.'/manifest.xml')) {
				$manifest = ComposerInstall::getModuleInfo($dir);
				$module = (string)$manifest->name;
				$type = (string)$manifest->type;
				ComposerInstall::moveModuleFiles($dir,$io);
				ComposerInstall::installModule($module,$type);
			}
		}
	}

	public static function postPackageUpdate($event) {
		ComposerInstall::postPackageInstall($event);
	}
	
	public static function getRealDirName($target) {
		list($vendor,$package) = explode('/', $target);
		$localdir = 'vendor/' . $vendor;
		$dirs = array_filter(glob($localdir.'/*'), 'is_dir');
		$found = false;
		foreach ($dirs as $dir) {
			$found = (strtolower($dir) == 'vendor/'.$target);
			if ($found) break;
		}
		return $dir;
	}
	
	public static function moveModuleFiles($moduledir,$io) {
		$manifest = ComposerInstall::getModuleInfo($moduledir);
		$type = (string)$manifest->type;
		$name = (string)$manifest->name;
		$label = (string)$manifest->label;
		$io->write('Copy into place: '.$label);
		if ($type=='language') {
			$prefix = (string)$manifest->prefix;
			@rename($moduledir.'/manifest.xml','include/language/'.$prefix.'.manifest.xml');
			@rename($moduledir.'/jscalendar/lang','jscalendar/lang');
			foreach (glob($moduledir.'/{modules,include}/*/language/'.$prefix.'.lang.{php,js}',GLOB_BRACE) as $langfile) {
				$fname = substr($langfile,strlen($moduledir)+1);
				@rename($langfile,$fname);
			}
		} else {  // module or extension
			@rename($moduledir.'/modules/'.$name,'modules/'.$name);
			@rename($moduledir.'/templates','Smarty/templates/modules/'.$name);
			@rename($moduledir.'/cron','cron/modules/'.$name);
			@unlink($moduledir.'/pack.sh');
			@unlink($moduledir.'/manifest.xml');
			@unlink($moduledir.'/composer.json');
			@rmdir($moduledir.'/modules');
		}
	}
	
	public static function getModuleInfo($moduledir) {
		$manifest = simplexml_load_file($moduledir.'/manifest.xml');
		return $manifest;
	}
	
	public static function installModule($module,$type) {
		@copy('build/HelperScripts/composerinstallmodule.php', '.');
		@system("php composerinstallmodule.php $module $type");
		@unlink('composerinstallmodule.php');
		
		/*
		@error_reporting(0);
		@ini_set('display_errors', 'off');
		@set_time_limit(0);
		@ini_set('memory_limit','1024M');
		$io->write('start-1: ');
		require_once('vtlib/Vtiger/Module.php');
		require_once('vtlib/Vtiger/Package.php');
		$io->write('start0: ');
		global $current_user,$adb, $Vtiger_Utils_Log;
		$current_user = new Users();
		$io->write('start1: ');
		$current_user->retrieveCurrentUserInfoFromFile(1); // admin
		$io->write('start2: ');
		$package = new Vtiger_Package();
		$io->write('start3: ');
		$manifest = ComposerInstall::getModuleInfo($moduledir);
		$module = (string)$manifest->name;
		$label = (string)$manifest->label;
		$tabrs = $adb->pquery('select count(*) from vtiger_tab where name=?',array($module));
		if ($tabrs and $adb->query_result($tabrs, 0,0)==1) {
			vtlib_toggleModuleAccess($module,true);
			$io->write('Module activated: '.$label);
		} else {
			$rdo = $package->importManifest('modules/'.$module.'/manifest.xml');
			$io->write('Module installed: '.$label);
		}
		*/
	}
}
