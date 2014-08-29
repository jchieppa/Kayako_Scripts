<?php
/**
* ###############################################
*
* SWIFT Framework
* _______________________________________________
*
* @author Varun Shoor
*
* @package SWIFT
* @copyright Copyright (c) 2001-2012, Kayako
* @license http://www.kayako.com/license
* @link http://www.kayako.com
*
* ###############################################
*/

/**
* RFC 822 Email address list (extended) validation Utility
*
* This class extends the RFC822 for added functionality and custom
* modifications.
*
* What is it?
*
* This class will take an address string, and parse it into it's consituent
* parts, be that either addresses, groups, or combinations. Nested groups
* are not supported. The structure it returns is pretty straight forward,
* and is similar to that provided by the imap_rfc822_parse_adrlist(). Use
* print_r() to view the structure.
*
* How do I use it?
*
* $address_string = 'My Group: "Richard Heyes" <richard@localhost> (A comment), ted@example.com (Ted Bloggs), Barney;';
* $structure = $class_rfc822->parseAddressList($address_string, 'example.com', TRUE)
* print_r($structure);
*
* @author Utsav Handa <utsav.handa@kayako.com>
*
* @changes
* - Overrided "validateMailbox" method to consume and support RFC822 weakly-compliant
* emails addresses such as - A.ABCXYZ(A.ABCXYZ)/M4/Finance & IT.IT/(주)만도 <something2005@topleveldomain.com >
*/

require_once('RFC822.php');
class Mail_RFC822Extended extends Mail_RFC822
{
        /**
         * Function to validate a mailbox, which is:
         * mailbox = addr-spec ; simple address
         * / phrase route-addr ; name and route-addr
         *
         * @override
         * @access public
         * @param string &$mailbox The string to check.
         * @return boolean Success or failure.
         */
        public function validateMailbox(&$mailbox)
        {
                // A couple of defaults.
                $phrase = '';

                // Catch any RFC822 comments and store them separately
                $_mailbox = str_replace(" ()", "", $mailbox);
                while (strlen(trim($_mailbox)) > 0) {
                        $parts = explode('(', $_mailbox);
                        $before_comment = $this->_splitCheck($parts, '(');
                        if ($before_comment != $_mailbox) {
                                // First char should be a (
                                $comment = substr(str_replace($before_comment, '', $_mailbox), 1);
                                $parts = explode(')', $comment);
                                // Retrieve closing bracket comment for match
                                if ($end_bracket_comment = $this->_splitCheck($parts, ')')) {
                                        $comment = $end_bracket_comment;
                                }
                                $comments[] = $comment;
                                // +1 is for the trailing )
                                $_mailbox = substr($_mailbox, strpos($_mailbox, $comment)+strlen($comment)+1);
                        } else {
                                break;
                        }
                }

                for($i=0; $i<count(@$comments); $i++){
                        $mailbox = str_replace('('.$comments[$i].')', '', $mailbox);
                }
                $mailbox = trim($mailbox);

                // Check for name + route-addr
                if (substr($mailbox, -1) == '>' && substr($mailbox, 0, 1) != '<') {
                        $parts = explode('<', $mailbox);
                        $name = $this->_splitCheck($parts, '<');

                        $phrase = trim($name);
                        $route_addr = trim(substr($mailbox, strlen($name.'<'), -1));

                        if ($this->_validatePhrase($phrase) === false || ($route_addr = $this->_validateRouteAddr($route_addr)) === false)
                                return false;

                        // Only got addr-spec
                } else {
                        // First snip angle brackets if present.
                        if (substr($mailbox,0,1) == '<' && substr($mailbox,-1) == '>')
                                $addr_spec = substr($mailbox,1,-1);
                        else
                                $addr_spec = $mailbox;

                        if (($addr_spec = $this->_validateAddrSpec($addr_spec)) === false)
                                return false;
                }

                // Construct the object that will be returned.
                $mbox = new stdClass();

                // Add the phrase (even if empty) and comments
                $mbox->personal = $phrase;
                $mbox->comment = isset($comments) ? $comments : array();

                if (isset($route_addr)) {
                        $mbox->mailbox = $route_addr['local_part'];
                        $mbox->host = $route_addr['domain'];
                        $route_addr['adl'] !== '' ? $mbox->adl = $route_addr['adl'] : '';
                } else {
                        $mbox->mailbox = $addr_spec['local_part'];
                        $mbox->host = $addr_spec['domain'];
                }

                $mailbox = $mbox;
                return true;
        }
}
?>