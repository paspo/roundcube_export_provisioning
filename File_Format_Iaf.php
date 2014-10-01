<?php


/*
        
        This class is php port of Perl package : Win32-Outlook-IAF
        http://search.cpan.org/dist/Win32-Outlook-IAF


        All regards to Przemek Czerkas
        
        author: Kononov Ruslan, 2008
        ver: 1.0.2
        
*/

class File_Format_Iaf
{
        private $HEADER = "\x66\x4D\x41\x49\x00\x00\x05\x00\x01\x00\x00\x00";
        private $PASSWORD_SEED = "\x75\x18\x15\x14";
        private $PASSWORD_HEADER ="\x01\x01";
        private $MAX_FIELD_LENGTH = 4096;


        // @todo ðàçîáðàòüñÿ ñ êîäèðîâêàìè
        // public $_encoding_server;
        // public $_encoding_iaf;
        /*
                // iaf file encoding
                vista (rus) - utf-16le
                xp (rus) - cp1251
        */


        private $_fieldFormatCheck = array(
                'bool_re' => '/^[01]$/', // boolean
                'num_re' => '/^\d+$/',  // numeric
                'regkey_re' => '/^[0-9a-z\{\}\-\-\.]+$/i', // registry key
                'iaf_ct_re' => '/^[0123]$/', // ConnectionType
                'iaf_am_re' => '/^[0123]$/', // AuthMethod enums
                'iaf_pf_re' => '/^[012]$/' // NNTP PostingFormat
        );


        // current loaded file
        private $_file;
        
        // IAF fields
        private $_fieldsTable = array(
                
                // added from Vista
                'DeletedItems' => array('313721840', 'nullstr_fmt', '', ''),
                'JunkEmail' => array('313787376', 'nullstr_fmt', '', ''),
                '323552245' => array('323552245', 'nullstr_fmt', '', ''),
                
                // Win32-Outlook-IAF
                // fix
                'AccountID' => array('305988592', 'nullstr_fmt', '', ''),
        
                // original
                'AccountName' => array('305464304', 'nullstr_fmt', '', ''),
                'TemporaryAccount' => array('305595369', 'ulong_le_fmt', 'bool_re', ''),
                'ConnectionType' => array('305726441', 'ulong_le_fmt', 'iaf_ct_re', ''),
                'ConnectionName' => array('305791984', 'nullstr_fmt', '', ''),
                'ConnectionFlags' => array('305857513', 'ulong_le_fmt', 'num_re', ''),
                'BackupConnectionName' => array('306054128', 'nullstr_fmt', '', ''),
                'MakeAvailableOffline' => array('306185193', 'ulong_le_fmt', 'bool_re', ''),
                'ServerReadOnly' => array('306316277', 'ulong_le_fmt', 'bool_re', ''),
                'IMAPServer' => array('311952368', 'nullstr_fmt', '', ''),
                'IMAPUserName' => array('312017904', 'nullstr_fmt', '', ''),
                'IMAPPassword' => array('312083446', 'nullstr_fmt', '', 'iaf_password'),
                'IMAPAuthUseSPA' => array('312214517', 'ulong_le_fmt', 'bool_re', ''),
                'IMAPPort' => array('312280041', 'ulong_le_fmt', 'num_re', ''),
                'IMAPSecureConnection' => array('312345589', 'ulong_le_fmt', 'bool_re', 'iaf_bool'),
                'IMAPTimeout' => array('312411113', 'ulong_le_fmt', 'num_re', ''),
                'IMAPRootFolder' => array('312476656', 'nullstr_fmt', '', ''),
                'IMAPUseLSUB' => array('312673269', 'ulong_le_fmt', 'bool_re', 'iaf_bool'),
                'IMAPPolling' => array('312738805', 'ulong_le_fmt', 'bool_re', 'iaf_bool'),
                'IMAPFullList' => array('312804341', 'ulong_le_fmt', 'bool_re', 'iaf_bool'),
                'IMAPStoreSpecialFolders' => array('313000949', 'ulong_le_fmt', 'bool_re', 'iaf_bool'),
                'IMAPSentItemsFolder' => array('313066480', 'nullstr_fmt', '', ''),
                'IMAPDraftsFolder' => array('313197552', 'nullstr_fmt', '', ''),
                'IMAPPasswordPrompt' => array('313525237', 'ulong_le_fmt', 'bool_re', 'iaf_bool'),
                'IMAPDirty' => array('313590761', 'ulong_le_fmt', 'bool_re', 'iaf_bool'),
                'IMAPPollAllFolders' => array('313656309', 'ulong_le_fmt', 'bool_re', 'iaf_bool'),
                'HTTPServer' => array('321782768', 'nullstr_fmt', '', ''),
                'HTTPUserName' => array('321848304', 'nullstr_fmt', '', ''),
                'HTTPPassword' => array('321913846', 'nullstr_fmt', '', 'iaf_password'),
                'HTTPPasswordPrompt' => array('321979381', 'ulong_le_fmt', 'bool_re', 'iaf_bool'),
                'HTTPAuthUseSPA' => array('322044905', 'ulong_le_fmt', 'bool_re', ''),
                'HTTPFriendlyName' => array('322110448', 'nullstr_fmt', '', ''),
                'DomainIsMSN' => array('322175989', 'ulong_le_fmt', 'bool_re', 'iaf_bool'),
                'HTTPPolling' => array('322241525', 'ulong_le_fmt', 'bool_re', 'iaf_bool'),
                'AdBarURL' => array('322307056', 'nullstr_fmt', '', ''),
                'ShowAdBar' => array('322372597', 'ulong_le_fmt', 'bool_re', 'iaf_bool'),
                'MinPollingInterval' => array('322438135', 'ulong_le_fmt', 'num_re', ''),
                'GotPollingInterval' => array('322503669', 'ulong_le_fmt', 'bool_re', 'iaf_bool'),
                'LastPolledTime' => array('322569207', 'ulong_le_fmt', 'num_re', ''),
                'NNTPServer' => array('325059568', 'nullstr_fmt', '', ''),
                'NNTPUserName' => array('325125104', 'nullstr_fmt', '', ''),
                'NNTPPassword' => array('325190646', 'nullstr_fmt', '', 'iaf_password'),
                'NNTPAuthMethod' => array('325321717', 'ulong_le_fmt', 'iaf_am_re', ''),
                'NNTPPort' => array('325387241', 'ulong_le_fmt', 'num_re', ''),
                'NNTPSecureConnection' => array('325452789', 'ulong_le_fmt', 'bool_re', 'iaf_bool'),
                'NNTPTimeout' => array('325518313', 'ulong_le_fmt', 'num_re', ''),
                'NNTPDisplayName' => array('325583856', 'nullstr_fmt', '', ''),
                'NNTPOrganizationName' => array('325649392', 'nullstr_fmt', '', ''),
                'NNTPEmailAddress' => array('325714928', 'nullstr_fmt', '', ''),
                'NNTPReplyToEmailAddress' => array('325780464', 'nullstr_fmt', '', ''),
                'NNTPSplitMessages' => array('325846005', 'ulong_le_fmt', 'bool_re', 'iaf_bool'),
                'NNTPSplitMessageSize' => array('325911529', 'ulong_le_fmt', 'num_re', ''),
                'NNTPUseGroupDescriptions' => array('325977077', 'ulong_le_fmt', 'bool_re', 'iaf_bool'),
                'NNTPPolling' => array('326108149', 'ulong_le_fmt', 'bool_re', 'iaf_bool'),
                'NNTPPostingFormat' => array('326173673', 'ulong_le_fmt', 'iaf_pf_re', ''),
                'NNTPSignature' => array('326239216', 'nullstr_fmt', 'regkey_re', ''),
                'NNTPPasswordPrompt' => array('326304757', 'ulong_le_fmt', 'bool_re', 'iaf_bool'),
                'POP3Server' => array('331613168', 'nullstr_fmt', '', ''),
                'POP3UserName' => array('331678704', 'nullstr_fmt', '', ''),
                'POP3Password' => array('331744246', 'nullstr_fmt', '', 'iaf_password'),
                'POP3AuthUseSPA' => array('331875317', 'ulong_le_fmt', 'bool_re', ''),
                'POP3Port' => array('331940841', 'ulong_le_fmt', 'num_re', ''),
                'POP3SecureConnection' => array('332006389', 'ulong_le_fmt', 'bool_re', 'iaf_bool'),
                'POP3Timeout' => array('332071913', 'ulong_le_fmt', 'num_re', ''),
                'POP3LeaveMailOnServer' => array('332137461', 'ulong_le_fmt', 'bool_re', 'iaf_bool'),
                'POP3RemoveWhenDeleted' => array('332202997', 'ulong_le_fmt', 'bool_re', 'iaf_bool'),
                'POP3RemoveWhenExpired' => array('332268533', 'ulong_le_fmt', 'bool_re', 'iaf_bool'),
                'POP3ExpireDays' => array('332334057', 'ulong_le_fmt', 'num_re', ''),
                'POP3SkipAccount' => array('332399605', 'ulong_le_fmt', 'bool_re', 'iaf_bool'),
                'POP3PasswordPrompt' => array('332530677', 'ulong_le_fmt', 'bool_re', 'iaf_bool'),
                'SMTPServer' => array('338166768', 'nullstr_fmt', '', ''),
                'SMTPUserName' => array('338232304', 'nullstr_fmt', '', ''),
                'SMTPPassword' => array('338297846', 'nullstr_fmt', '', 'iaf_password'),
                'SMTPAuthMethod' => array('338428905', 'ulong_le_fmt', 'iaf_am_re', ''),
                'SMTPPort' => array('338494441', 'ulong_le_fmt', 'num_re', ''),
                'SMTPSecureConnection' => array('338559989', 'ulong_le_fmt', 'bool_re', 'iaf_bool'),
                'SMTPTimeout' => array('338625513', 'ulong_le_fmt', 'num_re', ''),
                'SMTPDisplayName' => array('338691056', 'nullstr_fmt', '', ''),
                'SMTPOrganizationName' => array('338756592', 'nullstr_fmt', '', ''),
                'SMTPEmailAddress' => array('338822128', 'nullstr_fmt', '', ''),
                'SMTPReplyToEmailAddress' => array('338887664', 'nullstr_fmt', '', ''),
                'SMTPSplitMessages' => array('338953205', 'ulong_le_fmt', 'bool_re', 'iaf_bool'),
                'SMTPSplitMessageSize' => array('339018729', 'ulong_le_fmt', 'num_re', ''),
                'SMTPSignature' => array('339149808', 'nullstr_fmt', 'regkey_re', ''),
                'SMTPPasswordPrompt' => array('339215349', 'ulong_le_fmt', 'bool_re', 'iaf_bool')
        );
        // current fields
        private $_fields;


        // last error
        private $_error;
        
        public function load($file) {


                //iconv_set_encoding('internal_encoding', $this->_internal_encoding);


        $fieldsUnknown = array();
                $this->_file = $file;
        $this->_fields = array();
        $pos = 0;


                if (!is_readable($this->_file)) {
                        
                        $this->_error = "File '{$this->_file}' is not readable";
                        return false;
                }


                $contentIAF = file_get_contents($this->_file);


                $len = strlen($contentIAF);
                $pos += strlen($this->HEADER);


                while ($pos<$len) {




                        if ($pos+4 > $len) {


                                $this->_error = 'Premature end of data while reading field_id';
                                return false;
                        }


                        $field_id = current(unpack('V', substr($contentIAF, $pos, 4)));
                        $pos += 4;


                        if ($pos+4 > $len) {


                                $this->_error = 'Premature end of data while reading field_len';
                                return false;
                        }


                        $field_len = current(unpack('V', substr($contentIAF, $pos, 4)));
                        $pos += 4;




                        if (($pos+$field_len) > $len) {


                                $this->_error = 'Premature end of data while reading field_len';
                                return false;
                        }


                        if ($field_len > $this->MAX_FIELD_LENGTH) {


                                $this->_error = 'Excessive field length: ' . $field_len;
                                return false;
                        }


                        $field = substr($contentIAF, $pos, $field_len);
                        $pos += $field_len;


                        $key = -1;


            $field_format = null;
            $field_regex = null;
            $field_callback = null;
            
            if ($fieldParams = $this->getFieldById($field_id)) {
                
                list($field_name, , $field_format, $field_regex ,$field_callback) = $fieldParams; 
                
            } else {
                
                $fieldsUnknown[] = $field_id;
                continue;
            }




                        // call callback() as 'read packed'
                        if (!empty($field_callback)) $field = $this->$field_callback($field, 'read', 'packed');
                        
                        // apply binary format
                        switch($field_format) {


                                case 'ulong_le_fmt' :
                                        
                                        $field = current(unpack('V', $field));
                                        break;


                                case 'nullstr_fmt' :


                                        // @todo ðàçîáðàòüñÿ ñ êîäèðîâêàìè
                                        //file_put_contents("debug/{$field_name}_.txt", $field);
                                        //if ($this->_iaf_encoding) $field = iconv($this->_iaf_encoding, $this->_server_encoding, $field);
                                        $field = substr($field, 0, strrpos($field, "\0"));
                                        $field = current(unpack("a*", $field));


                                        //file_put_contents("debug/{$field_name}.txt", $field);
                                        
                                        break;


                                default:
                                
                                        $this->_error = "Unknown field format: {$field_format} for field id '{$field_id}'";
                    return false;
                        }


                        // call callback() as 'read unpacked'
                        if (!empty($field_callback)) $field = $this->$field_callback($field, 'read', 'unpacked');
                        // @todo ðàçîáðàòüñÿ ñ êîäèðîâêàìè
                        //else $field = mb_convert_encoding($field, 'windows-1251', 'UCS-4');
                        
                                                
                        if ($this->check_field($field, $field_regex)) $this->_fields[$field_name] = $field;




                        $this->_fields[$field_name] = $field;
                }
        
        if (count($fieldsUnknown) > 0) {
            
            $this->_error = 'Unknown fields : ' . implode(', ', $fieldsUnknown);
            return false;
        }
        
                return true;
        }
        
        public function render()
        {


                $data = $this->HEADER;
                
                foreach ($this->_fields as $field_name => $field) {
                        
            list($field_id, $field_format, ,$field_callback) = $this->_fieldsTable[$field_name];
            
                        // call callback() as 'write unpacked'
                        if (!empty($field_callback)) $field = $this->$field_callback($field, 'write', 'unpacked');
                        
                        // apply binary format
                        switch($field_format) {


                                case 'ulong_le_fmt' :
                                        
                                        $field = pack('V', $field);
                                        break;


                                case 'nullstr_fmt' :
                                        
                                        $field = pack("a*", $field);
                                        // @todo ðàçîáðàòüñÿ ñ êîäèðîâêàìè
                                        // if ($this->_iaf_encoding) $field = iconv($this->_server_encoding, $this->_iaf_encoding,  $field);
                                        $field .= "\0";
                                        break;


                                default :
                                
                                        echo "Unknown format: [{$field_format}]";
                                        continue;
                        }
                        
                        // call callback() as 'write packed'
                        if (!empty($field_callback)) $field = $this->$field_callback($field, 'write', 'packed');
                        $field_len = pack('V', strlen($field));
                        $field_id = pack('V', $field_id);
                        
                        $data .= $field_id . $field_len . $field;
                }
                
                return $data;
        }
        
        public function save($file = null) {
                
                $file = $file === null ? $this->_file : $file;
                
                if (empty($file)) {
                        $this->_error = 'Output file not defined';
                        return false;
                }
                
                $data = $this->render();
                
                if (file_put_contents($file, $data)) return true;
                
                return false;
        }
        
        public function __set($field, $value) {
                // @todo current        
                if (!array_key_exists($field, $this->_fieldsTable)) return;
                
                $regex = $this->_fieldsTable[$field][2];
                if (!$this->check_field($value, $regex)) return;


                $this->_fields[$field] = $value;


        }


        public function __get($field) {


                if (array_key_exists($field, $this->_fields)) return $this->_fields[$field]; 
                return false;
                
        }
        
        private function iaf_bool($value, $operation, $phase) {


                if (!($operation == 'get' || $operation == 'set')) return $value;
                return $value ? 1 : 0;


        }


        private function iaf_password($password, $operation, $phase) {


                if ($operation == 'text') return '********';
                if (!($operation == 'read' || $operation == 'write')) return $password;
                if (!($phase == 'packed')) return $password;


                $seed = $this->PASSWORD_SEED;
                $ret = '';
                $pos = 0;
                $len = strlen($password);


                if ($operation == 'read') {


                        if ($pos+strlen($this->PASSWORD_HEADER) > $len)  throw new Exception('Premature end of data while reading password header');


                        $pos += strlen($this->PASSWORD_HEADER);
                        if ($pos+4 > $len) throw new Exception('Premature end of data while reading password_len');


                        $password_len = current(unpack('V', substr($password, $pos, 4)));
                        $pos += 4;
                        if ($pos + $password_len != $len) throw new Exception('Malformed password record');


                } else {


                        $ret = $this->PASSWORD_HEADER;
                        $ret .= pack('V', $len);
                }


                while ($pos < $len) {


                        $fill = $pos+4 > $len ? $pos+4-$len : 0;
                        $seed=current(unpack('V', str_repeat("\x00", $fill) . substr($seed,$fill)));


                        $d = current(unpack('V', str_repeat("\x00", $fill) . substr($password, $pos, 4-$fill)));
                        $pos += 4 - $fill;


                        $ret .= substr(pack('V', $d^$seed), $fill);


                        $seed = pack('V', $operation == 'read' ? $d^$seed : $d);
                }


                return $ret;
        }


        private function check_field($field, $field_format_check) {


                if ($field_format_check) $regex = $this->_fieldFormatCheck[$field_format_check];
                else return true;


                if ($regex) {


                        if (preg_match($regex, $field)) return true;
                        else echo "invalid field value = '{$field}' fieldType = '{$field_format_check}'";
                        
                        return false;
                }
        }


        public function getFieldById($id)
        {
                foreach ($this->_fieldsTable as $field_name => $field) {
                        
                        if ($field[0] == $id) {
                
                array_unshift($field, $field_name);
                return $field;
            }
                }
                
        return false;
        }


        public function getFields()
        {
                return $this->_fields;
        }


        public function error()
        {
                return $this->_error;
        }
}


