<?php
/*
* 	class-wic-db-email-outgoing-object.php
*
*	packs the necessary information for a message on the outgoing queue
*	
*   8/23/2018: Add bypass to constituent issue lookups to handle standard reply without issue or constituent
*/

class WIC_DB_Email_Outgoing_Object {
	
	public $to_array			= array();
	public $cc_array			= array();	
	public $bcc_array			= array();
	public $subject				= '';
	public $html_body 			= '';
	public $text_body			= '';
	public $is_draft			= 0;
	public $is_reply_to 		= 0;
	public $include_attachments = false;
	public $issue 				= 0;
	public $pro_con				= '';
	public $search_type			= '';
	public $search_id			= 0;
	public $search_parm			= '';
	public $draft_id			= ''; // set only on draft creation
	
	public function __construct ( 
		$addresses, 
		$subject, 
		$html_body, 
		$text_body, 
		$is_draft = 0, 
		$is_reply_to = 0, 
		$include_attachments = false, 
		$issue = 0, 
		$pro_con = '', 
		$search_type = '', 
		$search_id = 0, 
		$search_parm = '' 
		) 
		{
		// set up basic properties
		$this->to_array				= isset( $addresses['to_array'] ) ? $addresses['to_array'] : array(); 
		$this->cc_array				= isset( $addresses['cc_array'] ) ? $addresses['cc_array'] : array();	
		$this->bcc_array			= isset( $addresses['bcc_array'] ) ? $addresses['bcc_array'] : array(); 
		/*
		*   format for each address array is:
		*	[0] name
		*	[1]	email_address
		*	[2] constituent_id (can be zero )
		*/
		$this->subject				= $subject;
		$this->html_body 			= $html_body;
		$this->text_body			= $text_body;  // text body can be or include html -- will be converted to text in send routine
		$this->is_draft				= $is_draft;
		$this->is_reply_to			= $is_reply_to;	
		$this->include_attachments 	= $include_attachments; // note that this only is used as a flag for outgoing automated replies -- otherwise, saved attachments at compose step
		$this->issue				= $issue;
		$this->pro_con				= $pro_con;	
		$this->search_type			= $search_type;
		$this->search_id			= $search_id;
		$this->search_parm			= $search_parm;
	}
	
} // class 