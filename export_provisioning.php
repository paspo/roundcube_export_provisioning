<?php

/**
 * Export_Provisioning
 *
 * Plugin that let the user download a settings-file for rapid email configuration.
 * It's used for Apple devices.
 *
 * @date 2014-09-30
 * @author Paolo Asperti
 * @url https://github.com/paspo/roundcube_export_provisioning
 * @licence GNU GPL
 */

include "File_Format_Iaf.php";

define('EXPORT_PROVISIONING_SMTPAUTH_NONE',0);
define('EXPORT_PROVISIONING_SMTPAUTH_PLAIN',1);




class export_provisioning extends rcube_plugin
{
	public $task = 'settings';
	private $rcmail;
	private static $data = array();

	public function init()
	{
    	$this->rcmail = rcmail::get_instance();
		$this->add_texts('localization/', true);
		$this->add_hook('storage_init', array($this,'storage_init_hook'));
		$this->add_hook('smtp_connect', array($this,'smtp_connect_hook'));
		$this->register_action('plugin.export_provisioning', array($this, 'register'));
		$this->register_action('plugin.export_provisioning-download-ios', array($this, 'download_ios'));
		$this->register_action('plugin.export_provisioning-download-iaf', array($this, 'download_iaf'));
	    $this->include_script('export_provisioning.js');
		$this->include_stylesheet('export_provisioning.css');
	}

	public function register()
	{
		$this->register_handler('plugin.body', array($this, 'page'));
		$this->rcmail->output->set_pagetitle($this->gettext('pagetitle'));
	    $this->rcmail->output->send('plugin');
	}

	public function page()
	{
		global $table;

		$table = new html_table(array('cols' => 2, 'cellpadding' => 0, 'cellspacing' => 0, 'class' => 'export_provisioning'));

		$table->add(array('class' => 'headerfirst'), $this->gettext('identity'));
		$table->add_row();

		$user = $this->rcmail->user;
		$identities = $user->list_identities();
		if (is_array($identities)) {
			if (count($identities)>1) {
				$select = new html_select(array('name' => '_identity', 'id' => '_identity', 'onchange' => 'export_provisioning_changelink(this)'));
				$default_identity = false;
				foreach ($identities as $identity) {
					if (!$default_identity && $identity['standard']==1) {
						$default_identity = $identity['identity_id'];
					}
					$display_name = trim($identity['name'] . ' ('.$identity['email'].')');
					$select->add($display_name, $identity['identity_id']);
				}
				$table->add('value', $select->show($default_identity));
			} elseif (count($identities) == 1) {
				$identity = array_shift($identities);
				$table->add('value', trim($identity['name'] . ' ('.$identity['email'].')'));
			} else {
				// should never happen: Roundcube always provides an identity
				$table->add('value', $this->gettext('no_identity_available'));
			}
		}
		$table->add_row();

		$table->add(array('class' => 'headerfirst'), $this->gettext('apple'));
		$table->add_row();

		$table->add('value', "<a id='export_provisioning_download_ios' href='./?_task=settings&_action=plugin.export_provisioning-download-ios&_identity=".$default_identity."'>".$this->gettext('download_ios')."</a>");
		$table->add_row();

		$table->add(array('class' => 'headerfirst'), $this->gettext('microsoft'));
		$table->add_row();

		$table->add('value', "<a id='export_provisioning_download_iaf' href='./?_task=settings&_action=plugin.export_provisioning-download-iaf&_identity=".$default_identity."'>".$this->gettext('download_iaf')."</a>");
		$table->add_row();

		$out = html::div(array('class' => 'settingsbox settingsbox-export_provisioning'),
		html::div(array('class' => 'boxtitle'), $this->gettext('boxtitle')) .
		html::div(array('class' => 'boxcontent'), $table->show()));

		return $out;
	}

	public function get_config_values()
	{
		$user = $this->rcmail->user;
		$identities = $user->list_identities();
		if (is_array($identities) && count($identities)>0) {
			$identity = $identities[0];  // default: first identity
			if ($req_identity = $_GET['_identity']) {
				foreach ($identities as $i) {
					if ($i['identity_id']==$req_identity) {
						$identity = $i;
					}
				}
			}
			$this->data['email'] = $identity['email'];
			$this->data['organization'] = $identity['organization'];
			$this->data['name'] = $identity['name'];
		}

		if (trim($this->data['name'])=='') {
			$this->data['name'] = $identity['email'];
		}

		if (!is_object($this->rcmail->smtp)) {
            $this->rcmail->smtp_init(true);
        }
        $smtp = $this->rcmail->smtp;

        // bisogna triggerare lo storage per recuperare i dati di login
		if (!is_object($this->rcmail->storage)) {
            $this->rcmail->storage_init(true);
        }
		$storage = $this->rcmail->storage;
	}

	public function storage_init_hook($args)
	{
		$this->data['IncomingMailServerHostName'] = $args['host'];
	    $this->data['IncomingMailServerPortNumber'] = $args['port'];
	    $this->data['IncomingMailServerUseSSL'] = (true == $args['ssl']);
	    $this->data['IncomingMailServerUsername'] = $args['user'];
	    $this->data['IncomingPassword'] = $args['password'];
	}

	public function smtp_connect_hook($args)
	{
		$host = $args['smtp_server'];
		$host = str_replace('%h', $this->rcmail->user->data['mail_host'], $host);
		$host = str_replace('%s', $_SERVER['SERVER_NAME'], $host);
		$smtp_user = $this->rcmail->config->get('smtp_user');
		$smtp_user = str_replace('%u', $this->rcmail->get_user_name(), $smtp_user);
		$smtp_pass = $this->rcmail->config->get('smtp_pass');
        $smtp_pass = str_replace('%p', $this->rcmail->get_user_password(), $smtp_pass);
		$ssl = false;

		if (strlen($host)>6) {
			$prefix = substr($host,0,6);
			if (($prefix == 'tls://') || ($prefix == 'ssl://') ){
				$host = substr($host,6);
				$ssl = true;
			}
		}

		$this->data['OutgoingMailServerHostName'] = $host;
		$this->data['OutgoingMailServerPortNumber'] = $args['smtp_port'];
		$this->data['OutgoingMailServerUseSSL'] = $ssl;
		$this->data['OutgoingMailServerUsername'] = $smtp_user;
		$this->data['OutgoingPassword'] = $smtp_pass;

		$this->data['OutgoingMailServerAuthentication'] = EXPORT_PROVISIONING_SMTPAUTH_NONE;

		 if (is_null($args['smtp_auth_type']) || trim($args['smtp_auth_type'])=='') {
		 	$user = $this->rcmail->config->get('smtp_user');
		 	if (is_string($user) && strlen(trim($user))>0) {
		 		$this->data['OutgoingMailServerAuthentication'] = EXPORT_PROVISIONING_SMTPAUTH_PLAIN;
		 	}
		 } else {
		 	switch (strtoupper($args['smtp_auth_type'])) {
        		case 'PLAIN':
        			$this->data['OutgoingMailServerAuthentication'] = EXPORT_PROVISIONING_SMTPAUTH_PLAIN;
            	break;
    		}
		 }
	}


	public function ios()
	{
		$this->get_config_values();
		$text = '<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
</plist>';
		$xml = new SimpleXMLElement($text);
		$dict = $xml->addChild('dict');
		$dict->addChild('key','PayloadContent');
		$dict->addChild('array');
		$content = $dict->array->addChild('dict');
		$content->addChild('key','EmailAccountDescription');
		$content->addChild('string',$this->data['email']);
		$content->addChild('key','EmailAccountName');
		$content->addChild('string',$this->data['name']);
		$content->addChild('key','EmailAccountType');
		$content->addChild('string','EmailTypeIMAP');
		$content->addChild('key','EmailAddress');
		$content->addChild('string',$this->data['email']);
		$content->addChild('key','IncomingMailServerAuthentication');
		$content->addChild('string','EmailAuthPassword');
		$content->addChild('key','IncomingMailServerHostName');
		$content->addChild('string',$this->data['IncomingMailServerHostName']);
		$content->addChild('key','IncomingMailServerPortNumber');
		$content->addChild('integer',$this->data['IncomingMailServerPortNumber']);
		$content->addChild('key','IncomingMailServerUseSSL');
		$content->addChild($this->data['IncomingMailServerUseSSL'] ? 'true' : 'false');
		$content->addChild('key','IncomingMailServerUsername');
		$content->addChild('string',$this->data['IncomingMailServerUsername']);
		$content->addChild('key','IncomingPassword');
		$content->addChild('string',$this->data['IncomingPassword']);
		$content->addChild('key','OutgoingMailServerAuthentication');
		switch($this->data['OutgoingMailServerAuthentication']) {
        	case EXPORT_PROVISIONING_SMTPAUTH_PLAIN:
				$content->addChild('string','EmailAuthPassword');
            break;
        	case EXPORT_PROVISIONING_SMTPAUTH_NONE:
				$content->addChild('string','EmailAuthNone');
            break;
    	}
		$content->addChild('key','OutgoingMailServerHostName');
		$content->addChild('string',$this->data['OutgoingMailServerHostName']);
		$content->addChild('key','OutgoingMailServerPortNumber');
		$content->addChild('integer',$this->data['OutgoingMailServerPortNumber']);
		$content->addChild('key','OutgoingMailServerUseSSL');
		$content->addChild($this->data['OutgoingMailServerUseSSL'] ? 'true' : 'false');
		$content->addChild('key','OutgoingMailServerUsername');
		$content->addChild('string',$this->data['OutgoingMailServerUsername']);
		$content->addChild('key','OutgoingPassword');
		$content->addChild('string',$this->data['OutgoingPassword']);
		$content->addChild('key','PayloadDescription');
		$content->addChild('string',$this->gettext('PayloadDescription').': '.$this->data['email']);
		$content->addChild('key','PayloadDisplayName');
		$content->addChild('string','IMAP Account ('.$this->data['email'].')');
		$content->addChild('key','PayloadIdentifier');
		$content->addChild('string','profile.'.$this->data['email'].'.e-mail');
		if ($this->data['organization'] != '') {
			$content->addChild('key','PayloadOrganization');
			$content->addChild('string',$this->data['organization']);
		}
		$content->addChild('key','PayloadType');
		$content->addChild('string','com.apple.mail.managed');
		$content->addChild('key','PayloadUUID');
		$content->addChild('string',$this->_randomUUID());
		$content->addChild('key','PayloadVersion');
		$content->addChild('integer','1');
		$content->addChild('key','PreventAppSheet');
		$content->addChild('false');
		$content->addChild('key','PreventMove');
		$content->addChild('false');
		$content->addChild('key','SMIMEEnabled');
		$content->addChild('false');
		$dict->addChild('key','PayloadDescription');
		$dict->addChild('string',$this->gettext('PayloadDescription').': '.$this->data['email']);
		$dict->addChild('key','PayloadDisplayName');
		$dict->addChild('string','email ' . $this->data['email']);
		$dict->addChild('key','PayloadIdentifier');
		$dict->addChild('string','profile.'.$this->data['email']);
		if ($this->data['organization'] != '') {
			$dict->addChild('key','PayloadOrganization');
			$dict->addChild('string',$this->data['organization']);
		}
		$dict->addChild('key','PayloadRemovalDisallowed');
		$dict->addChild('false');
		$dict->addChild('key','PayloadType');
		$dict->addChild('string','Configuration');
		$dict->addChild('key','PayloadUUID');
		$dict->addChild('string',$this->_randomUUID());
		$dict->addChild('key','PayloadVersion');
		$dict->addChild('integer','1');

		$dom = new DOMDocument("1.0");
		$dom->preserveWhiteSpace = false;
		$dom->formatOutput = true;
		$dom->loadXML($xml->asXML());
		return $dom->saveXML();
	}

	public function download_ios()
	{
		$ios_data = $this->ios();
		$temp_dir  = $this->rcmail->config->get('temp_dir');
		$tmpfname  = tempnam($temp_dir, 'iosprovdownload');
		$filename = $this->data['email'].'.mobileconfig';
		@file_put_contents($tmpfname, $ios_data);
	    $this->_send_file($tmpfname, $filename);
	    @unlink($tmpfname);
        exit;
	}

	public function iaf($filename) {
		$this->get_config_values();
		$iaf = new File_Format_Iaf();
		$iaf->__set('AccountName',$this->data['email']);
		$iaf->__set('IMAPServer',$this->data['IncomingMailServerHostName']);
		$iaf->__set('IMAPPort',$this->data['IncomingMailServerPortNumber']);
		$iaf->__set('IMAPSecureConnection',$this->data['IncomingMailServerUseSSL'] ? '1' : '0');
		$iaf->__set('IMAPUserName',$this->data['IncomingMailServerUsername']);
		$iaf->__set('IMAPPassword',$this->data['IncomingPassword']);
		$iaf->__set('IMAPSentItemsFolder','Sent');
		$iaf->__set('IMAPDraftsFolder','Drafts');
		$iaf->__set('DeletedItems','Trash');
		$iaf->__set('JunkEmail','Junk');
		if ($this->data['name'] != '') {
			$iaf->__set('SMTPDisplayName',$this->data['name']);
		} else {
			$iaf->__set('SMTPDisplayName',$this->data['email']);
		}
		$iaf->__set('SMTPEmailAddress',$this->data['email']);
		$iaf->__set('SMTPServer',$this->data['OutgoingMailServerHostName']);
		$iaf->__set('SMTPPort',$this->data['OutgoingMailServerPortNumber']);
		$iaf->__set('SMTPSecureConnection',$this->data['OutgoingMailServerUseSSL'] ? '1' : '0');
		switch ($this->data['OutgoingMailServerAuthentication']) {
        	case EXPORT_PROVISIONING_SMTPAUTH_PLAIN:
				$iaf->__set('SMTPAuthMethod',3);
            break;
        	case EXPORT_PROVISIONING_SMTPAUTH_NONE:
				$iaf->__set('SMTPAuthMethod',0);
            break;
    	}
		if ($this->data['organization'] != '') {
	        $iaf->__set('SMTPOrganizationName',$this->data['organization']);
		}
		$iaf->__set('SMTPUserName',$this->data['OutgoingMailServerUsername']);
		$iaf->__set('SMTPPassword',$this->data['OutgoingPassword']);
		$iaf->__set('MakeAvailableOffline','0');
		$iaf->save($filename);
	}

	public function download_iaf()
	{
		$temp_dir  = $this->rcmail->config->get('temp_dir');
		$tmpfname  = tempnam($temp_dir, 'iafprovdownload');
		$this->iaf($tmpfname);
		$filename = $this->data['email'].'.iaf';
	    $this->_send_file($tmpfname, $filename);
	    @unlink($tmpfname);
        exit;
	}

	private function _send_file($tmpfname, $filename)
    {
        $browser = new rcube_browser;

        $this->rcmail->output->nocacheing_headers();

        if ($browser->ie && $browser->ver < 7)
            $filename = rawurlencode(abbreviate_string($filename, 55));
        else if ($browser->ie)
            $filename = rawurlencode($filename);
        else
            $filename = addcslashes($filename, '"');

        // send download headers
        header("Content-Type: application/octet-stream");
        if ($browser->ie) {
            header("Content-Type: application/force-download");
        }

        // don't kill the connection if download takes more than 30 sec.
        @set_time_limit(0);
        header("Content-Disposition: attachment; filename=\"". $filename ."\"");
        header("Content-length: " . filesize($tmpfname));
        readfile($tmpfname);
    }


    private function _randomUUID()
    {
    	$fortychars = sha1(md5(rand()));
    	$fortychars[8]='-';
    	$fortychars[13]='-';
		$fortychars[18]='-';
		$fortychars[23]='-';
    	return strtoupper(substr($fortychars,0,36));
    }


}
