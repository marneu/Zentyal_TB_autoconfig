# Zentyal_TB_autoconfig

A utility to deliver a Zentyal configuration to Thunderbird Clients (i.e. autoconfig).

At least you need
```
apt-get install php5 php5-ldap
```
for the utility to work.

You will need to
```
apt-get install php5-cli
```
for interactive testing on command line.

For a Test put the ./mail/mozilla in your webserver dir like /var/www/html/.well-known/autoconfig/mail/mozilla and add the apache stanza to your local /etc/apache2/sites-available/000-default.conf

* setup the `.zentyal-credentials` file (example is given using the manual testing call)
* use the script zentyalconfig/createCredentialPass to get your password in the right fashion for the config file
* use the output from `uuidgen` for the local base of your calendars.
* test, test, test...
* fit the templates to your needs.

Manually testing can be done on the command line using:
> php zentylaconfig.php user=yoyo.dine 2>&1 | less

(Replace yoyo.dine with a real configured user within Zentyal).
For testing further do a softlink `ln -s /var/www/html/.well-known/autoconfig/mail /var/www/html/mail`

Next test is within the browser, goto:
> `http://pdc.zentyal.lan/mail/mozilla/zentyalconfig/win/yoyo.dine`

You should see the prepared configuration for your TB.

On the user (Win) side
* Clear the %APPDATA%\Thunderbird\* directory and call thunderbird with params:
You will need to deploy the stuff from the win directory here to the thunderbird program directory
* Your Thunderbird dir on the windows side could be something like:

> cd C:\Program Files (x86)\Mozilla Thunderbird\

(keep the structure below /win/, these dirs should all exist already.)
And then recreate a basic Thunderbird user dir within %APPDATA%

> thunderbird -CreateProfile "%USERNAME% %APPDIR%"

Execute Thunderbird from the desktop and acknowledge all certificates, enter your identification and so on.

For now further documentation for german readers can be found at http://www.std-soft.com/hm-service/26-thunderbird-provisionieren-mit-zentyal

