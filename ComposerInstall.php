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
				$type = (string)$manifest->type;
				if (empty($type)) $type='module';
				if ($type=='language') {
					$module = (string)$manifest->prefix;
				} else {
					$module = (string)$manifest->name;
				}
				ComposerInstall::moveModuleFiles($dir,$io);
				ComposerInstall::installModule($module,$type,$io);
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
		if (empty($type)) $type='module';
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
			ComposerInstall::dirmv($moduledir.'/modules/'.$name,'modules/'.$name,true,NULL,$io);
			ComposerInstall::dirmv($moduledir.'/templates','Smarty/templates/modules/'.$name,true,NULL,$io);
			ComposerInstall::dirmv($moduledir.'/cron','cron/modules/'.$name,true,NULL,$io);
			@unlink($moduledir.'/pack.sh');
			@unlink($moduledir.'/manifest.xml');
			@unlink($moduledir.'/composer.json');
			@rmdir($moduledir.'/modules');
		}
	}
	
	// move a directory and all subdirectories and files (recursive)
	// param str 'source directory'
	// param str 'destination directory'
	// param bool 'overwrite existing files'
	// param str 'location within the directory (for recurse)'
	// returns void
	public static function dirmv($source, $dest, $overwrite = false, $funcloc = NULL, $io = NULL) {
		if (is_null($funcloc)) {
			$dest .= '/' . strrev(substr(strrev($source), 0, strpos(strrev($source), '/')));
			$funcloc = '/';
		}
		if (!is_dir($dest . $funcloc)) {
			$io->write('mkdir '.$dest . $funcloc);
			mkdir($dest . $funcloc); // make subdirectory before subdirectory is copied
		}
		if ($handle = opendir($source . $funcloc)) { // if the folder exploration is sucsessful, continue
			while (false !== ($file = readdir($handle))) { // as long as storing the next file to $file is successful, continue
				if ($file != '.' && $file != '..'  && $file != '.git') {
					$path  = $source . $funcloc . $file;
					$path2 = $dest . $funcloc . $file;
					if (is_file($path)) {
						if(!is_file($path2)) {
							if(!@rename($path, $path2)) {
								$io->write('<font color="red">File ('.$path.') could not be moved, likely a permissions problem.</font>');
							}
						} elseif($overwrite) {
							if (!@unlink($path2)) {
								$io->write('Unable to overwrite file ("'.$path2.'"), likely to be a permissions problem.');
							} elseif (!@rename($path, $path2)) {
								$io->write('<font color="red">File ('.$path.') could not be moved while overwritting, likely a permissions problem.</font>');
							}
						}
					} elseif(is_dir($path)) {
						ComposerInstall::dirmv($source, $dest, $overwrite, $funcloc . $file . '/', $io); //recurse!
						rmdir($path);
					}
				}
			}
			closedir($handle);
		}
	} // end of dirmv()

	public static function getModuleInfo($moduledir) {
		$manifest = simplexml_load_file($moduledir.'/manifest.xml');
		return $manifest;
	}
	
	public static function installModule($module,$type,$io) {
		@copy('build/HelperScripts/composerinstallmodule.php', 'composerinstallmodule.php');
		// we have to do this externally because composer is very strict with PHP errors and coreBOS has too many...
		@system("php composerinstallmodule.php $module $type");
		@unlink('composerinstallmodule.php');
	}
}
