<?php
/**
 * ###############################################
 *
 * SWIFT Framework
 * _______________________________________________
 *
 * @author		Varun Shoor
 *
 * @package		SWIFT
 * @copyright	Copyright (c) 2001-2012, Kayako
 * @license		http://www.kayako.com/license
 * @link		http://www.kayako.com
 *
 * ###############################################
 */

/**
 * The Mime Parsing Class
 *
 * @author Varun Shoor
 */
class SWIFT_MailMIME extends SWIFT_Library
{
	private $_emailData = '';

	// Objects
	private $MimePolicy = false;
	private $MIME = false;
	private $RFC822 = false;
	private $Output = false;

	// Core Constants
	const RECIPIENT_LIMIT = 200;

	/**
	 * Constructor
	 *
	 * @author Varun Shoor
	 * @param string $_emailData The Raw Email Data
	 * @return bool "true" on Success, "false" otherwise
	 * @throws SWIFT_Mail_Exception If Invalid Data is Provided
	 */
	public function __construct($_emailData)
	{
		if (!$this->SetEmailData($_emailData))
		{
			throw new SWIFT_Mail_Exception(SWIFT_INVALIDDATA);

			return false;
		}

		parent::__construct();

		require_once ('./' . SWIFT_BASEDIRECTORY . '/' . SWIFT_THIRDPARTYDIRECTORY . '/MIME/mimeDecode.php');
		//require_once ('./' . SWIFT_BASEDIRECTORY . '/' . SWIFT_THIRDPARTYDIRECTORY . '/MIME/RFC822.php');
		
		require_once ('./' . SWIFT_BASEDIRECTORY . '/' . SWIFT_THIRDPARTYDIRECTORY . '/MIME/RFC822Extended.php'); 

		$this->Output = new stdClass;

		$this->MimePolicy = new SWIFT_MailMIMEDecodePolicy();
		$this->MIME = new Mail_mimeDecode($_emailData, $this->MimePolicy);
		//$this->RFC822 = new Mail_RFC822();
		$this->RFC822 = new Mail_RFC822Extended(); 
		

		return true;
	}

	/**
	 * Destructor
	 *
	 * @author Varun Shoor
	 * @return bool "true" on Success, "false" otherwise
	 */
	public function __destruct()
	{
		parent::__destruct();

		return true;
	}

	/**
	 * Set the Raw Email Data
	 *
	 * @author Varun Shoor
	 * @param string $_emailData The Raw Email Data
	 * @return bool "true" on Success, "false" otherwise
	 * @throws SWIFT_Mail_Exception If Invalid Data is Provided
	 */
	public function SetEmailData($_emailData)
	{
		if (empty($_emailData))
		{
			throw new SWIFT_Mail_Exception(SWIFT_INVALIDDATA);

			return false;
		}

		$this->_emailData = $_emailData;

		return true;
	}

	/**
	 * Retrieve the Raw Email Data
	 *
	 * @author Varun Shoor
	 * @return mixed "_emailData" (STRING) on Success, "false" otherwise
	 * @throws SWIFT_Mail_Exception If the Class is not Loaded
	 */
	public function GetEmailData()
	{
		if (!$this->GetIsClassLoaded())
		{
			throw new SWIFT_Mail_Exception(SWIFT_CLASSNOTLOADED);

			return false;
		}

		return $this->_emailData;
	}

	/**
	 * Decodes the Mail into parsable MIME structure
	 *
	 * @author Varun Shoor
	 * @return mixed "Output" Object on Success, "false" otherwise
	 * @throws SWIFT_Mail_Exception If the Class is not Loaded
	 */
	public function Decode()
	{
		if (!$this->GetIsClassLoaded())
		{
			throw new SWIFT_Mail_Exception(SWIFT_CLASSNOTLOADED);

			return false;
		}

		// Set up the optional items for decoding
		$_decodeParams = array();
		$_decodeParams['input'] = $this->GetEmailData();
		$_decodeParams['include_bodies'] = true;
		$_decodeParams['decode_bodies']  = true;
		$_decodeParams['decode_headers'] = true;

		// Perform the actual decoding
		$_mailStructure = $this->MIME->decode($_decodeParams);

		// Traverse over the returned decoded structure (e-mail body, multipart chunks)
		// and perform special processing for each one, depending on the MIME type.
		$this->TraverseStructure($_mailStructure);

		// Sizes
		$this->Output->textSize = mb_strlen(@$this->Output->text);
		$this->Output->htmlSize = mb_strlen(@$this->Output->html);

		// Headers
		if (isset($_mailStructure->headers))
		{
			$this->Output->headers = $_mailStructure->headers;
		}

		$_toFix = array("\(", "\)", "\[", "\]", "\{", "\}");
		$_fixResult = array("(", ")", "[", "]", "{", "}");

		// From name/email
		if (!empty($this->Output->headers['from'])) {
			$this->Output->headers['from'] = str_replace(array(','), array(''), $this->Output->headers['from']);

			$_finalFrom = str_replace($_toFix, $_fixResult, $this->Output->headers['from']);

			/*
			 * BUG FIX - Varun Shoor
			 *
			 * SWIFT-2120 Help desk does not use Full Name as specified under user's account in Autoresponder emails, for the tickets created via email queue
			 *
			 * Converts =?Big5?Q?eBay_=B7=7C=AD=FB=3A_checkrates?= <test@test.com> to "=?Big5?Q?eBay_=B7=7C=AD=FB=3A_checkrates?=" <test@test.com>
			 */
			$_finalFrom = preg_replace('/^=\?(.*)(\s+|\t+)<((?!(?:(?:\x22?\x5C[\x00-\x7E]\x22?)|(?:\x22?[^\x5C\x22]\x22?)){255,})(?!(?:(?:\x22?\x5C[\x00-\x7E]\x22?)|(?:\x22?[^\x5C\x22]\x22?)){65,}@)(?:(?:[\x21\x23-\x27\x2A\x2B\x2D\x2F-\x39\x3D\x3F\x5E-\x7E]+)|(?:\x22(?:[\x01-\x08\x0B\x0C\x0E-\x1F\x21\x23-\x5B\x5D-\x7F]|(?:\x5C[\x00-\x7F]))*\x22))(?:\.(?:(?:[\x21\x23-\x27\x2A\x2B\x2D\x2F-\x39\x3D\x3F\x5E-\x7E]+)|(?:\x22(?:[\x01-\x08\x0B\x0C\x0E-\x1F\x21\x23-\x5B\x5D-\x7F]|(?:\x5C[\x00-\x7F]))*\x22)))*@(?:(?:(?!.*[^.]{64,})(?:(?:(?:xn--)?[a-z0-9]+(?:-[a-z0-9]+)*\.){1,126}){1,}(?:(?:[a-z][a-z0-9]*)|(?:(?:xn--)[a-z0-9]+))(?:-[a-z0-9]+)*)|(?:\[(?:(?:IPv6:(?:(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){7})|(?:(?!(?:.*[a-f0-9][:\]]){7,})(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,5})?::(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,5})?)))|(?:(?:IPv6:(?:(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){5}:)|(?:(?!(?:.*[a-f0-9]:){5,})(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,3})?::(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,3}:)?)))?(?:(?:25[0-5])|(?:2[0-4][0-9])|(?:1[0-9]{2})|(?:[1-9]?[0-9]))(?:\.(?:(?:25[0-5])|(?:2[0-4][0-9])|(?:1[0-9]{2})|(?:[1-9]?[0-9]))){3}))\])))>$/i', '"=?$1" <$3>', $_finalFrom);

			$_fromContainer = $this->RFC822->parseAddressList($_finalFrom, null, null, false);

			if (isset($_fromContainer[0]->mailbox) && isset($_fromContainer[0]->host)) {
				$this->Output->fromEmail = sprintf('%s@%s', $_fromContainer[0]->mailbox, $_fromContainer[0]->host);
			} else {
				$this->Output->fromEmail = '';
			}

			if (isset($_fromContainer[0]->personal)) {
				$this->Output->fromName = preg_replace('/^"(.*)"$/', '\1', $_fromContainer[0]->personal);
			} else {
				$this->Output->fromName = '';
			}
		}

		// Reply to
		if (!empty($this->Output->headers['reply-to'])) {
			$_replyToContainer = $this->RFC822->parseAddressList(str_replace($_toFix, $_fixResult, $this->Output->headers['reply-to']), null, null, false);

			if (isset($_replyToContainer[0]->mailbox) && isset($_replyToContainer[0]->host)) {
				$this->Output->replytoEmail = sprintf('%s@%s', $_replyToContainer[0]->mailbox, $_replyToContainer[0]->host);
			} else {
				$this->Output->replytoEmail = '';
			}
			if (isset($_replyToContainer[0]->personal)) {
				$this->Output->replytoName = preg_replace('/^"(.*)"$/', '\1', $_replyToContainer[0]->personal);
			} else {
				$this->Output->replytoName = '';
			}
		}

		// To name/email
		if (!empty($this->Output->headers['to'])) {
			$_toContainer = $this->RFC822->parseAddressList(str_replace($_toFix, $_fixResult, $this->Output->headers['to']), null, null, false);
			if (is_array($this->Output->headers['to'])) {
				$this->Output->toEmail = $this->Output->headers['to'][0];
			} else if (isset($_toContainer[0]->mailbox) && isset($_toContainer[0]->host)) {
				$this->Output->toEmail = sprintf('%s@%s', $_toContainer[0]->mailbox, $_toContainer[0]->host);
			} else {
				$this->Output->toEmail = '';
			}

			if (isset($_toContainer[0]->personal))
			{
				$this->Output->toName = preg_replace('/^"(.*)"$/', '\1', $_toContainer[0]->personal);
			} else {
				$this->Output->toName = '';
			}
		}

		// In-Reply-To
		$this->Output->inReplyTo = '';
		if (isset($this->Output->headers['in-reply-to']) && !empty($this->Output->headers['in-reply-to'])) {
			$_inReplyToContainer = $this->RFC822->parseAddressList(str_replace($_toFix, $_fixResult, $this->Output->headers['in-reply-to']), null, null, false);

			if (isset($_inReplyToContainer[0]->mailbox)) {
				$this->Output->inReplyTo = trim($_inReplyToContainer[0]->mailbox);
			}
		}

		// Return address
		// Priority: Reply-To -> Sender -> From -> Return-Path
		if (!empty($this->Output->headers['reply-to'])) {
			$this->Output->returnAddress = $this->Output->headers['reply-to'];
		} elseif (!empty($this->Output->headers['sender'])) {
			$this->Output->returnAddress = $this->Output->headers['sender'];
		} elseif (!empty($this->Output->headers['from'])) {
			$this->Output->returnAddress = $this->Output->headers['from'];
		} elseif (!empty($output->headers['return-path'])) {
			$this->Output->returnAddress = $this->Output->headers['return-path'];
		}

		if (!empty($this->Output->returnAddress)) {
			$_returnAddressContainer = $this->RFC822->parseAddressList(str_replace($_toFix, $_fixResult, $this->Output->returnAddress), null, null, false);

			if (isset($_returnAddressContainer[0]->mailbox) && isset($_returnAddressContainer[0]->host)) {
				$this->Output->returnAddressEmail = sprintf('%s@%s', $_returnAddressContainer[0]->mailbox, $_returnAddressContainer[0]->host);
			} else {
				$this->Output->returnAddressEmail = '';
			}
			if (isset($_returnAddressContainer[0]->personal)){
				$this->Output->returnAddressName = preg_replace('/^"(.*)"$/', '\1', $_returnAddressContainer[0]->personal);
			} else {
				$this->Output->returnAddressName = '';
			}
		}

		// Recipient addresses
		$_recipients = $_bccRecipients = array();
		foreach (array('to', 'cc', 'x-rcpt-to', 'bcc') as $header)
		{
			if (!empty($this->Output->headers[$header]) && !is_array($this->Output->headers[$header]))
			{
				$_toContainer = $this->RFC822->parseAddressList(str_replace($_toFix, $_fixResult, $this->Output->headers[$header]), null, null, false);
				$_loopCount = count($_toContainer);

				if ($_loopCount > self::RECIPIENT_LIMIT)
				{
					$_loopCount = self::RECIPIENT_LIMIT;
				}

				for ($_i=0; $_i < $_loopCount; $_i++)
				{
					if (isset($_toContainer[$_i]))
					{
						$_recipientEmail = sprintf('%s@%s', $_toContainer[$_i]->mailbox, $_toContainer[$_i]->host);

						if (IsEmailValid($_recipientEmail))
						{
							if ($header == 'bcc') {
								$_bccRecipients[] = $_recipientEmail;
							} else {
								$_recipients[] = $_recipientEmail;
							}
						}
					}
				}
			} else {
				// Most probably this email has multiple to's
				if (isset($this->Output->headers[$header][0]) && IsEmailValid($this->Output->headers[$header][0]))
				{
					$_recipients[] = $this->Output->headers[$header][0];
				}
			}
		}

		$this->Output->recipientAddresses = $_recipients;
		$this->Output->bccRecipientAddresses = $_bccRecipients;

		if (isset($this->Output->headers['subject']))
		{
			$this->Output->subject = $this->Output->headers['subject'];
		}

		// Mail_mimeDecode now automatically decodes MIME-encoded "words" (subject, from name, to name etc.)
		// to the native encoding of the help desk.  It is no longer necessary to do any further decoding here.
		//
		// Message bodies will still be subject to $_SWIFT["settings"]["pr_conversion"], however.
		// -- RML

		return $this->Output;
	}

	/**
	 * Traverses the decoded structure and adds the various parts to the private variables
	 *
	 * @author Varun Shoor
	 * @param object $_mailStructure The Mail Structure
	 * @param bool $_hasMultipart Whether the message is added as a multipart
	 * @param bool $_hasRFC822Part Whether it has the RFC822 Part
	 * @return bool "true" on Success, "false" otherwise
	 * @throws SWIFT_Mail_Exception If the Class is not Loaded
	 */
	private function TraverseStructure($_mailStructure, $_hasMultipart = false, $_hasRFC822Part = false)
	{
		if (!$this->GetIsClassLoaded())
		{
			throw new SWIFT_Mail_Exception(SWIFT_CLASSNOTLOADED);

			return false;
		}

		$_contentType = strtolower(@$_mailStructure->ctype_primary) . '/' . strtolower(@$_mailStructure->ctype_secondary);
		if (empty($_contentType) || $_contentType == '/') {
			$_contentType = 'text/plain';
		}

		switch ($_contentType) {
			case 'message/feedback-report':
			case 'text/html':
			case 'text/plain': {
					if (!empty($_mailStructure->disposition) AND $_mailStructure->disposition == 'attachment') {
						// Attachment
						$this->AddAttachment($_mailStructure);
					} else {
						// Ignore text/html bodies of embedded message/rfc822 parts. The text bodies of those parts
						// are saved as part of the attachment.
						if (!$_hasRFC822Part) {
							// Textual body
							$_encoding = '';
							$this->DecodeBody($_mailStructure, $_mailStructure->body, $_encoding);
							$var = $_contentType == 'text/html' ? 'html' : 'text';
							$_encodingVar = $var . 'Charset';
							@$this->Output->$var .= $_mailStructure->body;
							$this->Output->$_encodingVar = $_encoding;
						}
					}

					break;
				}
			case 'message/rfc822': {
					// For message/rfc822 (original message forwarded as an attachment):
					// 1. Check if the part is actually an MHT; this has special handling needs.
					// 2. Save the entire original message/rfc822 as an attachment.
					// 3. Recursively parse the structure looking for attachments and add them when found.

					$this->ParseRFC822Message($_mailStructure); // Adds the entire structure of the RFC822 message as an attachment.

					if (isset($_mailStructure->ctype_parameters['name']) && strtolower(self::ExtensionFromFileName($_mailStructure->ctype_parameters['name'])) == 'mht') {
						// Don't recursively parse MHT files.
						break;
					}

					$_hasRFC822Part = true;

					// Let execution fall through to the default handler, which recursively parses the sub-parts of the message.
				}

	  /*
					$this->DecodeBody($_mailStructure, $_mailStructure->body);
					$var = $_contentType == 'text/html' ? 'html' : 'text';
					@$this->Output->$var .= $_mailStructure->body;
				}

				break;
			case 'message/rfc822':
				if ($_hasMultipart)
				{
					// When a message/rfc822 is added as a multipart, we treat it as an attachment.
					// Otherwise, if it goes through the normal parsing channels, the body will be stripped out,
					// and depending on the content type, may be completely ignored by the mail parser.
					// This way, the entire message is retained as an attachment.
					$_time = localtime(time(), true);
					$_fileName = sprintf("message-%02d/%02d/%04d-%02d:%02d:%02d.eml",
										$_time['tm_mday'],
										$_time['tm_mon'] + 1, // January = 0
										$_time['tm_year'] + 1900, // Years since 1900
										$_time['tm_hour'],
										$_time['tm_min'],
										$_time['tm_sec']);

					$this->Output->attachments[] = $this->GenerateTextAttachment($_mailStructure->raw, $_fileName, "message/rfc822");

					break;
				}
	   */
			case 'multipart/report':
			case 'multipart/signed':
			case 'multipart/mixed':
			case 'multipart/alternative':
			case 'multipart/related':
			case 'multipart/digest':
			case 'multipart/parallel':
			case 'multipart/voice-message':
			case 'message/disposition-notification':
				if (!empty($_mailStructure->parts)) {
					for ($_i=0; $_i < count($_mailStructure->parts); $_i++) {
						$this->TraverseStructure($_mailStructure->parts[$_i], true, $_hasRFC822Part);
					}
				}

				break;

			default:
				$this->AddAttachment($_mailStructure);
		}

		return true;
	}

	/**
	 * Private function used by the traverse function (above)
	 * to add an attachment to the output object.
	 *
	 * @author Varun Shoor
	 * @param string $_mailStructure The Mail Structure Object
	 * @return bool "true" on Success, "false" otherwise
	 * @throws SWIFT_Mail_Exception If the Class is not Loaded
	 */
	private function AddAttachment($_mailStructure)
	{
		if (!$this->GetIsClassLoaded())
		{
			throw new SWIFT_Mail_Exception(SWIFT_CLASSNOTLOADED);

			return false;
		}
		// Determine filename and extension
		$_fileName = '';
		$_extension = '';
		if (!empty($_mailStructure->d_parameters['filename'])) {
			$_fileName = $_mailStructure->d_parameters['filename'];
			$_extension = self::ExtensionFromFileName($_fileName);
		} else if (!empty($_mailStructure->ctype_parameters["name"])) {
			$_fileName = $_mailStructure->ctype_parameters["name"];
			$_extension = self::ExtensionFromFileName($_fileName);
		}

		$_contentType = strtolower(@$_mailStructure->ctype_primary) . '/' . strtolower(@$_mailStructure->ctype_secondary);

		// Attachment or embedded?
		$var = 'attachments';
		$this->Output->{$var}[] = array('data' => @$_mailStructure->body, 'size' => @mb_strlen($_mailStructure->body), 'filename' => $_fileName,
			'extension' => $_extension, 'contenttype' => $_contentType);

		return true;
	}

	/**
	 * Generates an attachment structure from textual data (used to convert RFC822 messages to attachments)
	 *
	 * @author Varun Shoor
	 * @param string $_data The Text Data
	 * @param string $_fileName The File Name
	 * @param string $_contentType The Content Type
	 * @return array array(data, size, filename, extension, contenttype) on success
	 * @throws SWIFT_Mail_Exception If the Class is not Loaded
	 */
	private function GenerateTextAttachment($_data, $_fileName, $_contentType)
	{
		if (!$this->GetIsClassLoaded())
		{
			throw new SWIFT_Mail_Exception(SWIFT_CLASSNOTLOADED);

			return false;
		}

		$_posLastDot = mb_strrpos($_fileName, ".");

		$_extension = '';
		if ($_posLastDot === false)
		{
			$_extension = "EML";
		} else {
			$_extension = mb_substr($_fileName, $_posLastDot+ 1, mb_strlen($_fileName) - ($_posLastDot+ 1) );
		}

		return array('data' => $_data, 'size' => mb_strlen($_data), 'filename' => $_fileName, 'extension' => $_extension,
			'contenttype' => $_contentType);
	}

	/**
	 * Decodes an email message body (or multipart chunk) from whatever encoding it is in to the native encoding
	 * of the help desk (if the setting is enabled) in the following fashion:
	 *
	 * 1. If a content-type charset is present, it is used.
	 * 2. If a content-type is not present, but an x-notascii is, it is used.
	 * 3. If neither are present, an attempt is made to deduce the encoding based on characters found in the message body.
	 * 4. The decision to transfer encoding is then passed to MIMEDecodePolicy.
	 *
	 * @author Ryan M. Lederman
	 * @param string $_headers The Mail Headers
	 * @param string $_body The Mail Body
	 * @param string $_charset the Character Set
	 * @return bool "true" on Success, "false" otherwise
	 * @throws SWIFT_Mail_Exception If the Class is not Loaded
	 */
	private function DecodeBody($_headers, &$_body, &$_charset)
	{
		if (!$this->GetIsClassLoaded())
		{
			throw new SWIFT_Mail_Exception(SWIFT_CLASSNOTLOADED);

			return false;
		}

		$_nativeEncoding = $this->Language->Get('charset');

		// Get the source encoding from the headers/body
		$_fromEncoding = $this->GetEncoding($_headers, $_body);

		// Convert to the native encoding
		$_MIMEPolicy = new SWIFT_MailMIMEDecodePolicy();
		$_body = $_MIMEPolicy->ConvertEncoding($_body, $_fromEncoding);

		$_charset = $_fromEncoding;

		return true;
	}

	/**
	 * Parse the RFC822 Message
	 *
	 * @author Ryan M. Lederman
	 * @param object $_mailStructure The Mail Structure Object
	 * @return bool "true" on Success, "false" otherwise
	 * @throws SWIFT_Mail_Exception If the Class is not Loaded
	 */
	protected function ParseRFC822Message($_mailStructure)
	{
		if (!$this->GetIsClassLoaded())
		{
			throw new SWIFT_Mail_Exception(SWIFT_CLASSNOTLOADED);

			return false;
		}

		/*
		* BUG FIX: Parminder Singh
		*
		* SWIFT-1779: Images attach in the body section does not parse by the parser
		*
		* Comments: None
		*/
	   if (!isset($_mailStructure->ctype_parameters['name']))
	   {
		   $_mailStructure->ctype_parameters['name'] = '';
	   }

		// Saves a message/rfc822 MIME part as an attachment to a ticket
		$this->Output->attachments[] = array('data' => $_mailStructure->raw,
											  'size' => mb_strlen($_mailStructure->raw),
											  'filename' => $_mailStructure->ctype_parameters['name'],
											  'extension' => self::ExtensionFromFileName($_mailStructure->ctype_parameters['name']),
											  'contenttype'=> 'message/rfc822');

		return true;
	}

	/**
	 * Parse the RFC822 Message
	 *
	 * @author Ryan M. Lederman
	 * @param string $_fileName The File Name
	 * @return string Extension on Success, "" otherwise
	 */
	static protected function ExtensionFromFileName($_fileName)
	{
		$_matchesContainer = array();

		if (preg_match('/^.+\.(\w+)$/', $_fileName, $_matchesContainer))
		{
			return $_matchesContainer[1];
		}

		return '';
	}

	/**
	 * Retrieve the Encoding of the Email
	 *
	 * @author Ryan M. Lederman
	 * @param string $_headers The Mail Headers
	 * @param string $_body The Mail Body
	 * @return string The Email Encoding
	 * @throws SWIFT_Mail_Exception If the Class is not Loaded
	 */
	private function GetEncoding($_headers, $_body)
	{
		if (!$this->GetIsClassLoaded())
		{
			throw new SWIFT_Mail_Exception(SWIFT_CLASSNOTLOADED);

			return false;
		}

		$_fromCharset = 'US-ASCII'; // Default to ASCII

		// Do we have content-type?
		if (!empty($_headers->ctype_parameters['charset']))
		{
			$_fromCharset = $_headers->ctype_parameters['charset'];

		} else if (!empty($_headers->headers['x-notascii'])) {
			// Format of x-notascii: "x-notascii: charset=foo"
			$_matches = array();
			if (preg_match('/^[^=]*=\s*([-a-z0-9]+)$/i', $_headers->headers['x-notascii'], $_matches))
			{
				$_fromCharset = $_matches[1];
			}
		} else {
			// Couldn't find a usable charset anywhere in the headers; let's try to
			// deduce it directly from the message body.
			$_fromCharset = mb_detect_encoding($_body);
		}

		return $_fromCharset;
	}
}
?>