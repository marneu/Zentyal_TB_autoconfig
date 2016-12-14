<?php


/* Class for a utility to provision thunderbird clients connected to a Zentyal server.
 *   
 *  Author: Markus Neubauer @ std-service.com
 *  License: GPLv3
 *  Initial Release: Dec. 2016
 *
 *  Provide a setup for Thunderbird clients in an automated fashion. Uses the
 *  configuration of a Zentyal 4.2 server (at the time of writing).
 *
 *  Version: 0.8
*/

/*
	START helper classes
*/
class AdminCredentials
{

	/* You DEFINETIFLY should outsource your REAL INFORMATION in a modified way
		 READ the $help variable below!
	*/
	public		$credentials = ".zentyal-credentials";

	protected	$mail_hostname;
	protected	$ad_host;
	protected	$ad_base;
	protected	$ad_users;
	protected	$ad_groups;
	protected	$ad_bind;
	public		$use_groups = true;
	protected	$ad_pass;
	protected	$uuid;

	// something strange occured
	public function error( $errno, $errtext ) {

		echo "displayError(\"SERVER ERROR " .	$errno . ': ' . $errtext . ". Ask your local Administrator\");" . PHP_EOL;
		exit($errno);

	}

	// get the admin user privileges
	function get_Admin_Credentials() {

		if ( ! file_exists( $this->credentials ) ) {

			$help = <<<'EOT'

// ERROR: Unable to find the credentials file
// Admin vars for the LDAP/SQL connection are pulled of a credentials file
//	 Filename could be '.zentyal-credentials'

	 // start content
	 <?php
	 $this->mail_hostname	= 'pdc.zentyal.lan';
	 $this->ad_host		= '127.0.0.1';
	 $this->ad_base		= 'DC=zentyal,DC=lan';
	 $this->ad_users	= 'CN=Users,DC=zentyal,DC=lan';
	 $this->ad_groups	= 'CN=Groups,DC=zentyal,DC=lan';
	 $this->ad_bind		= 'cn=My Admin,CN=Users,DC=zentyal,DC=lan';
	 // switch group functions on/off ~ false
	 $this->use_groups	= true;
	 $this->ad_pass		= 'create using: zentyalconfig/createCredentialPass "YoYo_Dine"';
	 $this->uuid		= 'create using: uuidgen';
	 // end content

EOT;

			echo $help;
			return false;

		}

		require_once $this->credentials;
		return true;
	}
	
	function adminPass() {
		return str_rot13(trim(base64_decode($this->ad_pass)));
	}

// Obfuscate a little bit
//	We dont like clear text pwds in html area nor in backups
//	may not be the safest way but better than nothing
	public function create_CredentialPass($clear_text_pass) {
		echo "Add the following to your credentials file:" . PHP_EOL;
		echo "\$this->ad_pass = '" . trim(base64_encode(str_rot13($clear_text_pass))) . "';" . PHP_EOL;
	}

}

// do the ldap basic request for a user
class Zentyal_Mail_Ldap_Connector extends AdminCredentials
{

	protected $usr 	= array(
			'USER_NAME'	=> '',
			'GIVEN_NAME'	=> '',
			'SUR_NAME'	=> '',
			'REAL_NAME'	=> '',
			'DISPLAY_NAME'	=> '',
			'MAIL_ADDRESS'	=> '',
			'HOME_DIR'	=> '',
			'BASE_DN'	=> '',
			'BIND_DN'	=> '',
			'SMTP_EMAIL'	=> '',
			'MAIL_HOST'	=> '',
			'MAIL_DOMAIN'	=> ''
			);

	protected $ldapGroups 	= array();
	protected $mailGroups	= array();
	protected $grps		= array();
	
	private static $ad;

	// connect to ldap and setup the user variables
	function __construct() {

		if (! parent::get_Admin_Credentials() )
			$this->error(510, "Unable to find '$this->credentials'");

		if ( !self::$ad ) {
			self::$ad = ldap_connect($this->ad_host);
			if ( !self::$ad )
				$this->error(520, "Unable to connect LDAP at '$this->ad_host'");
			ldap_set_option(self::$ad, LDAP_OPT_PROTOCOL_VERSION, 3);
			ldap_set_option(self::$ad, LDAP_OPT_REFERRALS, 0);
			if (!ldap_bind(self::$ad, $this->ad_bind, $this->adminPass()) )
				$this->error(530, "Unable to bind LDAP using '$this->ad_bind'. " . ldap_error(self::$ad));
		}

	}

	public function setZentyalUser($USERNAME) {

		if ( empty($USERNAME) or ' --NULL-- ' == $USERNAME )
			$this->error(500, "No USERNAME provided'");

		// do we have mail or user as parameter
		$mail = false;
		if ( 1 < strpos($USERNAME, '@') ) {
			$mail = true;
			$filter = 'mail=' . $USERNAME;
		} else $filter = 'SamAccountName=' . $USERNAME;

		$users = $this->ldap_lookup( $this->ad_users, $filter,
				array('dn','givenname','sn','displayName','homedirectory','name','mail','memberof','proxyaddresses'));

		if ( $users === false )
			$this->error(540, "Unable to find user '$USERNAME'");

		if ( !empty($users[1]) )
			$this->error(550, "More than one records found using search '$filter'" );

		// found any user data
		if ( $mail ) $this->usr['USER_NAME'] = substr($USERNAME, 0, strpos($USERNAME, '@') );
		else $this->usr['USER_NAME'] = $USERNAME;

		$this->usr['GIVEN_NAME']	= $users[0]['givenname'][0];
		$this->usr['SUR_NAME']		= $users[0]['sn'][0];
		$this->usr['REAL_NAME'] 	= $users[0]['name'][0];
		$this->usr['REALNAME'] 		= preg_replace("/[^a-z0-9.]+/i", "", $users[0]['name'][0]);
		$this->usr['DISPLAY_NAME'] 	= $users[0]['displayname'][0];
		$this->usr['MAIL_ADDRESS']	= $users[0]['mail'][0];
		$this->usr['HOME_DIR']		= $users[0]['homedirectory'][0];
		$this->usr['BASE_DN']		= $this->ad_users;
		$this->usr['BIND_DN']		= $users[0]['dn'];
		$this->usr['SMTP_EMAIL']	= substr($users[0]['proxyaddresses'][0], strpos($users[0]['proxyaddresses'][0],':' )+1);
		$this->usr['MAIL_HOST']		= $this->mail_hostname;
		$this->usr['MAIL_DOMAIN']	= substr($this->usr['MAIL_ADDRESS'], strpos($this->usr['MAIL_ADDRESS'],'@')+1);

		if ( ! $this->use_groups ) return;
		// add group memberships
		for ($i = 0; $i < $users[0]['memberof']['count']; $i++) {
			// not interested in user membership
			if ( strpos($users[0]['memberof'][$i],'CN=Users') > 1 ) continue;
			
			$this->ldapGroups[] = $users[0]['memberof'][$i];
		}
	}
	
	// return an array of groups for the user
	public function getGroups() {
		if ( ! $this->use_groups ) return false;

		if ( empty($this->mailGroups) ) {
			foreach ($this->ldapGroups as $grp_dn) {

				// going only for mail type membership
				if ( ! strpos($grp_dn,'_MAIL,CN=Groups') ) continue;
				$GroupArr  = ldap_explode_dn($grp_dn, 1);
				// just to be shure
				if ( empty($GroupArr) or ! $GroupArr[1] == 'Groups' ) continue;
				
				$ldapUser  = substr( $GroupArr[0], 0, strrpos($GroupArr[0],'_MAIL') );
				$filter	   = 'SamAccountName=' . $ldapUser;
				$ldapGroup = $this->ldap_lookup( $this->ad_users, $filter,
								array('dn','description','displayName','name','mail','memberof','proxyaddresses'));
				if ( $ldapGroup === false) continue;

				$this->mailGroups[] = $GroupArr[0];
				$this->grps[$GroupArr[0]] = array(
							'GROUP_USER'	=> $ldapUser,
							'DESCRIPTION'	=> $ldapGroup[0]['description'][0],
							'REAL_NAME' 	=> $ldapGroup[0]['name'][0],
							'USER_REAL_NAME'=> $this->usr['REAL_NAME'],
							'REALNAME'	=> preg_replace("/[^a-z0-9.]+/i", "", $ldapGroup[0]['name'][0]),
							'DISPLAY_NAME' 	=> $ldapGroup[0]['displayname'][0],
							'GROUP_MAILID'	=> $ldapGroup[0]['mail'][0],
							'BASE_DN'	=> $this->ad_users,
							'BIND_DN'	=> $ldapGroup[0]['dn'],
							'GIVEN_NAME'	=> $this->usr['GIVEN_NAME'],
							'SUR_NAME'	=> $this->usr['SUR_NAME'],
							'SMTP_EMAIL'	=> $this->usr['SMTP_EMAIL'],
							'MAIL_HOST'	=> $this->usr['MAIL_HOST'],
							'MAIL_DOMAIN'	=> substr($ldapGroup[0]['mail'][0], strpos($ldapGroup[0]['mail'][0],'@')+1)
							);
			}
		}
		return $this->mailGroups;
	}

	// perform ldap lookups
	private function ldap_lookup( $cn, $filter, $attributes=array() ) {

		$result = ldap_search(self::$ad, $cn, $filter, $attributes);
		if(!$result) return false;
		return ldap_get_entries(self::$ad, $result);

	}

	// close ldap conn if such
	function __destruct() {
		if (!empty(self::$ad) && is_resource(self::$ad)) ldap_close(self::$ad);
	}

}

// combine ldap and mysql requests
class Mail_Template_Connector extends Zentyal_Mail_Ldap_Connector
{
	protected $template	= '';
	protected $templates	= array();
		
	private   $counter = array(	'IDENTITY_ID'	=> 0,
					'SERVER_ID' 	=> 0,
					'SMTP_ID'	=> 0,
					'ACCOUNT_ID' 	=> 0,
					'ABOOK_ID'	=> 0
					);
	private   $tags    = array(
				'IDENTITY_ID'	=> 'id',
				'SERVER_ID' 	=> 'server',
				'SMTP_ID'	=> 'smtp',
				'ACCOUNT_ID' 	=> 'account',
				'ABOOK_ID'	=> 'abook-'
				);
	private   $allAccounts	= 'account1';

	/*  TAKEN FROM:  https://gist.github.com/dahnielson/508447
	  * FURTHER REF: "I found it in the online PHP Manual where 
	  *	      Andrew Moore, the author of the code, had 
	  *	      posted it as a comment." */
	// get the v3 uuid for calendar
	public function get_UUID_v3($namespace, $name) {
		if (! preg_match('/^\{?[0-9a-f]{8}\-?[0-9a-f]{4}\-?[0-9a-f]{4}\-?' .
					      '[0-9a-f]{4}\-?[0-9a-f]{12}\}?$/i', $namespace) === 1 )
			return false;

		// Get hexadecimal components of namespace
		$nhex = str_replace(array('-','{','}'), '', $namespace);

		// Binary Value
		$nstr = '';

		// Convert Namespace UUID to bits
		for($i = 0; $i < strlen($nhex); $i+=2) 
		{
			$nstr .= chr(hexdec($nhex[$i].$nhex[$i+1]));
		}

		// Calculate hash value
		$hash = md5($nstr . $name);

		return sprintf('%08s-%04s-%04x-%04x-%12s',
			// 32 bits for "time_low"
			substr($hash, 0, 8),
			// 16 bits for "time_mid"
			substr($hash, 8, 4),
			// 16 bits for "time_hi_and_version",
			// four most significant bits holds version number 3
			(hexdec(substr($hash, 12, 4)) & 0x0fff) | 0x3000,
			// 16 bits, 8 bits for "clk_seq_hi_res",
			// 8 bits for "clk_seq_low",
			// two most significant bits holds zero and one for variant DCE1.1
			(hexdec(substr($hash, 16, 4)) & 0x3fff) | 0x8000,
			// 48 bits for "node"
			substr($hash, 20, 12)
		);								

	}
	
	// we want to have a v3 uuid based on calendar name with userid and client ip.
	public function getCalendarId($CAL_NAME, $CAL_TYPE = 'personal') {
		if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} elseif (!empty($_SERVER['REMOTE_ADDR'])) {
			$ip = $_SERVER['REMOTE_ADDR'];
		} else {
			$ip = '127.0.0.1';
		}
		return $this->get_UUID_v3($this->uuid, $CAL_NAME . $CAL_TYPE . $ip);
	}

	private function startTemplateBlock($filename) {
		return <<<EOT
// try $filename
try {

EOT;
	}

	private function endTemplateBlock($filename) {
		return <<<EOT
} catch(e) {
	displayError("contact admin: $filename", e);
}

EOT;
	}
	
	private function setCounterTags( $filename, $userName ) {

		// just in case
		if ( !file_exists($filename) ) return false;

		// do not do twice
		if ( array_search($filename, $this->templates) ) return false;
		$this->templates[] = $filename;
		
		$intermediate = file_get_contents($filename);

		foreach ( $this->tags as $tag => $value) {
			if ( strpos( $intermediate, '[' . $tag .']' ) ) {
				$this->counter[$tag] += 1;
				$intermediate = str_replace('[' . $tag .']', $value . $this->counter[$tag], $intermediate);
			}
		}

		$tag = 'CAL_ID';
		if ( strpos( $intermediate, '[' . $tag .']' ) ) {
			if (! $CAL_UUID = $this->getCalendarId($userName) )
				return;
			$intermediate = str_replace('[' . $tag .']', $CAL_UUID, $intermediate);
		}

		return $intermediate;
	}

	// fetch a template and replace values if needed
	public function setTemplate( $filename ) {

		if ( $intermediate = $this->setCounterTags($filename, $this->usr['USER_NAME']) ) {
			// push on stock
			$this->template .= $this->startTemplateBlock($filename);
			$this->template .= $intermediate;
			$this->template .= $this->endTemplateBlock($filename);
		}
	}

	// fetch a group template and replace values if needed
	public function setGroupTemplate($group, $filename) {

		$grp	= $this->grps[$group];
		if ( $intermediate = $this->setCounterTags($filename, $grp['GROUP_USER']) ) {

			$match	= array();
			$replace= array();
			foreach ( $grp as $key => $value) {
				$match[] = '[' . $key . ']';
				$replace[] = &$grp[$key];
			}

			// push on stock
			$this->template .= $this->startTemplateBlock($filename);
			$this->template .= str_replace($match, $replace, $intermediate);
			$this->template .= $this->endTemplateBlock($filename);
		}
	}

	// get the final template here
	public function getAutoconfig() {

		$match = array();
		$replace = array();
		foreach ( $this->usr as $key => $value) {
			$match[] = '[' . $key . ']';
			$replace[] = &$this->usr[$key];
		}
		for ( $i = 2; $i <= $this->counter['ACCOUNT_ID']; $i++ ) {
			$this->allAccounts .= ',account' . $i;
		}
		$match[] = '[ALL_ACCOUNTS]';
		$replace[] = $this->allAccounts;
		return str_replace($match, $replace, $this->template);
	}

	// close ldap/sql conns if such, as we are done now
	function __destruct() {
		parent::__destruct();
	}

}
//	END helper classes

?>
