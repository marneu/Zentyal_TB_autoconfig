<?php

/* A utility to provision thunderbird clients connected to a Zentyal server.
 *   
 *  Author: Markus Neubauer @ std-service.com
 *  License: GPLv3
 *  Initial Release: Dec. 2016
 *
 *  Provides a setup for Thunderbird clients in an automated fashion. Uses the
 *  configuration of a Zentyal 4.2 server (at the time of writing).
 *
 *  Version: 0.91
*/

// autoconfiguration base dir (i.e. where the templates live)
$baseDir = 'TB';

/* only for interactive test purpose
	use: php $0 user=USERNAME	*/
if ( php_sapi_name() == 'cli' ) {
	parse_str(implode('&', array_slice($argv, 1)), $_GET);
}
else {
	header('Content-Type: application/x-javascript; charset=utf-8');
}

// start parameter check
// I NEED AT LEAST ONE PARAMETER like: http://your.domain.tld/mail/mozilla/zentyalconfig?user='username'

if ( !empty($_GET["user"]) ) {
	$USERNAME = htmlspecialchars($_GET["user"]);
}
else 	$USERNAME = ' --NULL-- ';

// use the given os, if none given assume we have a win environment out there
if ( !empty($_GET["OS"]) ) {
	$OS = htmlspecialchars($_GET["OS"]);
}
else	$OS = 'win';
// end parameters

echo "// Compiled for $USERNAME on " . date('Y-m-d H:i:s') . PHP_EOL;
echo "// using: $OS-*.template(s)" . PHP_EOL;
if (! is_dir( $baseDir) ) {
	echo "// Unable to find a template dir '" . $baseDir . "'" . PHP_EOL;
	exit;
} else {
	$baseDir .= '/';
}

// we need a class to continue
try {
	if (! @include_once( "zentyalconfig.class.php" ) )
		throw new Exception ("Unable to find class 'zentyalconfig.class.php'.");
	
} catch (Exception $e) {
	echo "displayError(\"SERVER ERROR: " . $e ."\");" . PHP_EOL;
	exit;
}

// contruct our search file filter
function getTemplateFileNames($baseDir, $OS, $request = '') {
	if (!empty( $request ) ) {
		if (! is_dir($baseDir . $request) )
			return false;
		$request .= '/';
	}
	$fileFilter = $baseDir . $request . $OS . '-' . "*.template";
	if ( $FileArr = glob( $fileFilter ) ) {
		return $FileArr;
	} else {
		return false;
	}
}

/***********************************
* HERE WE START THE MAIN LINE CODE *
***********************************/

$t = new Mail_Template_Connector;

// get local user zentyal setup
$t->setZentyalUser($USERNAME);

// get template files for OS defaults
if ( $templateFiles = getTemplateFileNames($baseDir, $OS) ) {
	foreach ( $templateFiles as $filename ) {
		$t->setTemplate($filename);
	}
}

// do user specific files (if such)
$searchDir = $USERNAME . '-templates';
if ( $templateFiles = getTemplateFileNames($baseDir, $OS, $searchDir) ) {
	foreach ( $templateFiles as $filename ) {
		$t->setTemplate($filename);
	}
}

// add SOGo authorized objects (Calendar/Contacts)
$searchDir = 'SOGO-SYSTEM-TEMPLATES';
if ( $t->getSOGoACL($USERNAME) )
	$t->addSOGOtemplates($baseDir . $searchDir . '/' . $OS . '-');

// do the following block only if groups are enabled at all
if ( $t->use_groups ) {
	// going into users groups
	$UserGroups = $t->getGroups();
	foreach ( $UserGroups as $templateGroup ) {
		// get group template files for OS defaults
		$searchDir = $templateGroup . '-templates';
		if ( $templateFiles = getTemplateFileNames($baseDir, $OS, $searchDir) ) {
			foreach ( $templateFiles as $filename ) {
				$t->setGroupTemplate($templateGroup, $filename);
			}
		}
		else {
			// add common group setup for all groups
			$searchDir = 'COMMON_GROUP-templates';
			if ( $templateFiles = getTemplateFileNames($baseDir, $OS, $searchDir) ) {
				foreach ( $templateFiles as $filename ) {
					$t->setGroupTemplate($templateGroup, $filename);
				}
			}
		}
	}

}

// setup the final account counter
$searchDir = 'FINAL';
if ( $templateFiles = getTemplateFileNames($baseDir, $OS, $searchDir) ) {
	foreach ( $templateFiles as $filename ) {
		$t->setTemplate($filename);
	}
}



// print the template
if (! print($t->getAutoconfig()) )
	$t->error(999, "Unable to print output");

?>
