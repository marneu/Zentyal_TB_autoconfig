<?php

/* Provisioning light for Mail Clients
 * just check that the home directory exists
 * No php ldap needed
 * License: GPLv3
 * Author: Markus Neubauer @ std-service.com
 * Version: Dec. 2016
*/

// test interactive
if ( php_sapi_name() == 'cli' ) {
  parse_str(implode('&', array_slice($argv, 1)), $_GET);
} 

$MANUAL = '(Manuell bearbeiten)';
$EMAIL = trim($_GET['emailaddress']);
$EMAIL_ID = substr( $EMAIL, 0, strpos($EMAIL, '@' ) );

if ( !is_dir('/home/' . $EMAIL_ID ) or empty($EMAIL) ) {
  $EMAIL = $MANUAL;
} 
else {
  $EMAIL = $EMAIL_ID . '@zentyal.lan';
}

header ("Content-Type:text/xml");
echo '<?xml version="1.0" encoding="UTF-8"?>

<clientConfig version="1.1">
  <emailProvider id="zentyal.lan">
    <domain>zentyal.lan</domain>
    <displayName>YoYo Dine Corp.</displayName>
    <displayShortName>YoYoDine</displayShortName>
    <incomingServer type="imap">
      <hostname>pdc.zentyal.lan</hostname>
      <port>143</port>
      <socketType>STARTTLS</socketType>
      <authentication>password-cleartext</authentication>
      <username>' . $EMAIL . '</username>
    </incomingServer>
    <outgoingServer type="smtp">
      <hostname>pdc.zentyal.lan</hostname>
      <port>25</port>
      <socketType>STARTTLS</socketType>
      <authentication>password-cleartext</authentication>
      <username>' . $EMAIL . '</username>
    </outgoingServer>
    <documentation url="https://pdc.zentyal.lan/SOGo/">
      <descr lang="de">Webmail Konfiguration</descr>
      <descr lang="en">Webmail setup</descr>
    </documentation>
  </emailProvider>
</clientConfig>
';
?>
