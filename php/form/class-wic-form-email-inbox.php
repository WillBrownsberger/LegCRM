<?php
/*
* class-wic-form-email-inbox.php
*
*
*/

class WIC_Form_Email_Inbox extends WIC_Form_Parent {

	// no header tabs
	

	// customized layout_form to support tabbed groups
	public static function layout_inbox ( &$data_array, $args ) {

		global $current_user;

		$requested_page = isset ( $args['id_requested'] ) && $args['id_requested'] ? $args['id_requested'] : 'inbox';

		// set up list of folders
		$options_array = WIC_Entity_Email_Inbox::get_inbox_options( 'dummy'); 
		$menu_list = '<ul class = "wic-page-option-list" >';
		foreach ( $options_array as $option ){
			// limit options 
			if  (
					( $option['value'] != 'inbox' && ! $current_user->current_user_authorized_to ( 'all_email' ) ) 
				){
				continue;
			}
			$starting_class = $requested_page == $option['value'] ? ' wic-active-inbox-page ' : '';
			
			$menu_list .= '
				<li class="wic-inbox-page-list-item ' . $starting_class .  '">
				<span class="wic-inbox-page-value">' . 
					$option['value'] . 
				'</span>
				<span class="wic-inbox-page-label">' . 
					$option['label'] . 
				'</span>'. 
				( 
					'inbox' == $option['value'] ?
					' <span id="all_inbox_messages_count"><span class = "wic-inbox-tab-count"></span></span>'
					: ''
				)
				.
			'</li>' .
			( ( 'sent' == $option['value'] || 'saved' == $option['value'] ) ? '<hr/>' : '' )
			;
		}
		$menu_list .= '</ul>';

		// button ids match tooltip divs and content divs (js relies on this);
		echo '<div id = "wic-email-inbox" >' .
			// note that these two header divs will be shown in alternative as mode shifts from inbox view to message list view
			'<div id="wic-email-inbox-header">' .
				'<button class="wic-form-button email-action-button email-compose-button" id="wic-email-compose-button" value="new,0,0" type="button" title= "New Email Message"><span class="dashicons dashicons-plus"></span></button>' .
				'<div class="wic-dashboard-title inbox-legend" id="wic-inbox-title-legend" >INBOX</div>' .
				'<div class="wic-dashboard-title inbox-legend" id="wic-inbox-title-alt-legend"></div>' .
				'<button class="wic-form-button page-link prev " type="button" value="prev" title = "Previous Page" ><span class="dashicons dashicons-arrow-left"></span></button>' .
				'<button class="wic-form-button page-link next " type="button" value="next" title = "Next Page" ><span class="dashicons dashicons-arrow-right"></span></button>' .
				'<button class="wic-form-button" type="button" id="wic-filter-assigned-button" value="filter_assigned" title = "Filter by Assigned" ><span class="dashicons dashicons-admin-users"></span></button>' .
				$data_array['subject']->form_control() .    	
				'<button class="wic-form-button email-action-button trigger-email-process-button" id="wic-inbox-sweep-button" value="sweep" type="button" title= "Sweep already mapped"><span class="dashicons dashicons-controls-forward"></span></button>' .
				'<button class="wic-form-button email-action-button inbox-mode-button" id="wic-inbox-ungroup-button" value="group" type="button"  title = "Group/Ungroup" >1</button>' .
				'<button class="wic-form-button email-action-button inbox-mode-button" id="wic-inbox-sort-button" value="sort" type="button" title = "Date Sort"><span class="dashicons dashicons-sort"></span></button>' .
				'<button class="wic-form-button email-action-button inbox-mode-button" id="wic-inbox-refresh-button" value="refresh" type="button" title = "Refresh"><span class="dashicons dashicons-update"></span></button>' .
				'<button class="wic-form-button email-action-button inbox-mode-button" id="wic-inbox-resize-button" value="resize" type="button" title = "Shrink/expand inbox"><span id="resize-inbox-button-content" class="dashicons dashicons-no"></span></button>' .
			'</div>' . 		
			// controls div
			'<div id = "wic-email-inbox-controls">' .
				$menu_list .  	
			'</div>' .
			// content divs -- swapped by js wic-email- xxxx -content
			'<div id = "wic-email-inbox-content" class="wic-email-inbox-content">' . self::setup_inbox( $data_array ) . '</div>' .
			'<div id = "wic-email-draft-content" class="wic-email-draft-content">' . self::setup_draft() . '</div>' .
			'<div id = "wic-email-outbox-content" class="wic-email-inbox-content">' . self::setup_outbox() . '</div>' .
			'<div id = "wic-email-manage-subjects-content" class="wic-email-inbox-content" >' . self::setup_manage_subjects() . '</div>' .
			'<div id = "wic-email-sent-content" class="wic-email-inbox-content" >' . self::setup_sent() . '</div>' .
			'<div id = "wic-email-done-content" class="wic-email-inbox-content" >' . self::setup_done() . '</div>' .
			'<div id = "wic-email-saved-content" class="wic-email-inbox-content" >' . self::setup_saved() . '</div>' .
			'<div id = "wic-email-manage-blocks-content" class="wic-email-inbox-content" >' . self::setup_manage_blocks() . '</div>' .
			'<div id = "wic-email-settings-content" class="wic-email-inbox-content"> ' . self::setup_settings_form() . '</div>' .
		'</div>' .
			// message review -- positioned absolute and made visible in transition to detail
		'<div id = "wic-inbox-message-review"> ' . self::setup_message_review( $data_array ) . '</div>';
		
	}

	protected static function setup_inbox( &$data_array ) {
		global $current_user;

		// limit tabs array to assigned read unless user can view_edit_unassigned
		$tabs_array = $current_user->current_user_authorized_to ('all_email' ) ? WIC_Entity_Email_Inbox::get_tabs() : array ( 'Assigned', 'Ready');
		
		// set up tab list
		$tab_list = '';
		foreach ( $tabs_array  as $tab ) {
			$tab_list .= 
				'<li class="wic-inbox-tab ui-state-default ui-corner-top '.  ( 'Primary' == $tab ? 'ui-tabs-active' : '' ) .'"' .
					 'id="' . ('CATEGORY_' . strtoupper( $tab ) ) . 
					 '">' .
				'<a href="tab" class="ui-tabs-anchor inbox-tab-dummy-link">' . 
					$tab . ' <span class="wic-inbox-tab-count"></span>' .
				'</a>
			</li>';
		}
		// ajax spinner and empty div
		$html = 
			'<div id = "hidden-staff-assignment-control">' .
				$data_array['case_assigned']->form_control() .
			'</div>' .
			// checkbox and buttons
			'<div id = "inbox-top-line-with-controls-and-tabs">' .
				'<div id = "bulk-block-delete-controls">' . 
					'<div class="inbox-master-checkbox-wrapper"><input id="inbox-master-checkbox"  type="checkbox" value="1"/></div>' .	
					'<button class="wic-form-button email-bulk-action-button " id="wic-email-bulk-delete-button" type="button" value="delete" title="Archive" ><span class="dashicons dashicons-archive"></span></button>' .
					'<button class="wic-form-button email-bulk-action-button " id="wic-email-bulk-block-button" type="button" value="block" title = "Archive and block sender" ><span class="dashicons dashicons-warning"></span></button>' .	
				'</div>' .		
			( 	$tab_list ?
				'<div id="wic-inbox-form-tabbed" class = "ui-tabs ui-widget ui-widget-content ui-corner-all">' .
					'<ul id ="wic-inbox-tabs" class="ui-tabs-nav ui-helper-reset ui-helper-clearfix ui-widget-header ui-corner-all">' . $tab_list . '</ul>' .
				'</div>' 
				: 
				'' 
			) .
			'</div>' .
			'<div id = "wic-load-inbox-wrapper" class = "inbox-wrapper ">
				<div id = "inbox-ajax-loader">' .
					'<em>Loading . . .  </em>
					<img src="' .WIC_Admin_Setup::root_url() . 'img/ajax-loader.gif' . '">' . 
				'</div>' .
				'<div id = "wic-load-inbox-inner-wrapper" class="wic-email-area-inner-wrapper" ></div>' .
			'</div>' 
			;
		return ( $html ); 	
	}
	
	private static function setup_outbox () {
	
		$button_args_main = array(
			'type'						=> 'button',
			'id'						=> 'purge-queue-email-button',			
			'name'						=> 'purge-queue-email-button',		
			'button_label'				=> 'Purge Outbox',
			'title'						=> 'Delete pending messages.',
		);	
		$buttons = WIC_Form_Parent::create_wic_form_button ( $button_args_main );
	
		// ajax spinner and empty div
		$html = $buttons .
				'<div id = "wic-load-outbox-wrapper" class="inbox-wrapper">' .
					'<span id = "outbox-ajax-loader">' .
						'<img src="' .WIC_Admin_Setup::root_url() . 'img/ajax-loader.gif' .
					'"></span>' .
					'<div id = "wic-load-outbox-inner-wrapper"  class="wic-email-area-inner-wrapper"></div>' .
				'</div>';
		return $html ; 					
	}
	
	protected static function setup_sent () {
		// ajax spinner and empty div
		$html = '<div id = "wic-load-sent-wrapper" class="inbox-wrapper">
					<span id = "sent-ajax-loader">' .
						'<img src="' .WIC_Admin_Setup::root_url() . 'img/ajax-loader.gif' .
					'"></span>' .
				'<div id = "wic-load-sent-inner-wrapper"  class="wic-email-area-inner-wrapper"></div>' .
			'</div>'; 
		return ( $html ); 					
	}
		
	protected static function setup_draft () {
		// ajax spinner and empty div
		$html = '<div id = "wic-load-draft-wrapper" class="inbox-wrapper">
					<span id = "draft-ajax-loader">' .
						'<img src="' .WIC_Admin_Setup::root_url() . 'img/ajax-loader.gif' .
					'"></span>' .
				'<div id = "wic-load-draft-inner-wrapper"  class="wic-email-area-inner-wrapper"></div>' .
			'</div>'; 
		return ( $html ); 					
	}

	protected static function setup_done () {
		// ajax spinner and empty div
		$html = '<div id = "wic-load-done-wrapper" class="inbox-wrapper">
					<span id = "done-ajax-loader">' .
						'<img src="' .WIC_Admin_Setup::root_url() . 'img/ajax-loader.gif' .
					'"></span>' .
				'<div id = "wic-load-done-inner-wrapper"  class="wic-email-area-inner-wrapper"></div>' .
			'</div>'; 
		return ( $html ); 					
	}

	protected static function setup_saved () {
		// ajax spinner and empty div
		$html = '<div id = "wic-load-saved-wrapper" class="inbox-wrapper">
					<span id = "saved-ajax-loader">' .
						'<img src="' .WIC_Admin_Setup::root_url() . 'img/ajax-loader.gif' .
					'"></span>' .
				'<div id = "wic-load-saved-inner-wrapper"  class="wic-email-area-inner-wrapper"></div>' .
			'</div>'; 
		return ( $html ); 					
	}

	protected static function setup_manage_blocks () {
		// ajax spinner and empty div
		$html = '<div id = "wic-load-blocks-wrapper">
					<span id = "blocks-ajax-loader">' .
						'<img src="' .WIC_Admin_Setup::root_url() . 'img/ajax-loader.gif' .
					'"></span>' .
				'<div id = "wic-load-blocks-inner-wrapper"  class="wic-email-area-inner-wrapper"></div>' .
			'</div>'; 
		return ( $html ); 					
	}
	private static function setup_manage_subjects () {
	
		// show an add subject button
		$button_args_main = array(
			'button_label'				=> 'Add Subject',
			'type'						=> 'button',
			'id'						=> 'add-new-subject-button',			
			'name'						=> 'add-new-subject-button'			

		);	
		
		$html = WIC_Form_Parent::create_wic_form_button ( $button_args_main );

		$control = WIC_Control_Factory::make_a_control( 'text' );
		$control->initialize_default_values ( 'email_inbox', 'search_subjects_phrase', '' );
		$html.= $control->form_control();
		
		$html .= 
			'<div id = "wic_subject_editor_legend">
				<p>You can map a new email subject line to an issue here.  You can also overwrite an old mapping by just entering a new one for the same subject.  The more routine way to add/update mappings is to check <code>Train</code> when handling emails from the Inbox. </p>
				<p>Adding a subject here allows to you use the wildcard symbols <code>%</code> and <code>_</code> in your subject definition. <code>%</code> matches any number of characters (including zero characters) and <code>_</code> matches exactly one character.  </p>
				<p>Unless you use the wildcards, only emails with subjects that exactly match trained subjects will be processed in sweeps. Take extra care when using the wildcards in conjunction with automatic processing -- you do not want to inadvertently process unrelated subjects.</p>
				<p>Here are some wildcard examples:</p>
				<table class="wp-issues-crm-stats">
					<colgroup>
						<col style="width:50%">
						<col style="width:50%">
					</colgroup>
					<tbody>
					<tr><th class = "wic-statistic-text">Trained Phrase</th><th class = "wic-statistic-text">Matching Email Subjects</th></tr>
						<tr><td class = "wic-statistic-text" ><code>Good News about Uber from %</code></td><td class = "wic-statistic-text" >"Good News about Uber from Will"</code></td></tr>
						<tr><td class = "wic-statistic-text" ><code>% has Good News about %</code></td><td class = "wic-statistic-text" >"Will has Good News about Strawberries"</code></td></tr>
						<tr><td class = "wic-statistic-text" ><code>% has Good News about %</code></td><td class = "wic-statistic-text" >"RE: Will has Good News about Strawberries"</code></td></tr>
						<tr><td class = "wic-statistic-text" ><code>%1846%</code></td><td class = "wic-statistic-text" >Please pass Senate Bill 1846 -- it is long past due</td></tr>
						<tr><td class = "wic-statistic-text" ><code>%1846%</code></td><td class = "wic-statistic-text" >FWD: Meet at 1846 Mass Ave tomorrow.</td></tr>
						<tr><td class = "wic-statistic-text" ><code>1846</code></td><td class = "wic-statistic-text" >1846</td></tr>
						<tr><td class = "wic-statistic-text" ><code>1846_</code></td><td class = "wic-statistic-text" >1846B (no match to 1846Rear)</td></tr>
						<tr><td class = "wic-statistic-text" ><code>%</code></td><td class = "wic-statistic-text" >any subject line</td></tr>
					</tbody>
				</table>
				<p>For more examples and explanation of wildcard processing, see <a target = "_blank" href="https://docs.microsoft.com/en-us/sql/t-sql/language-elements/like-transact-sql?view=sql-server-ver15">T-SQL documentation on standard pattern matching.</a>
				You can do some interesting additional things with positive and negative character classes. Matching for
				subject lines is configured to be case sensitive and to include all characters.  For example, to match 
				any subject containing "sports bet" regardless of initial capitalization you would use "%[Ss]ports bet%".</p>
				
			</div>';
		// $this_group_output .= $this->the_controls ( $group_fields, $doa );
		$html .= '<div id = "wic-subject-list-wrapper">' .
					'<span id = "subject-list-ajax-loader"  class="wic-email-area-inner-wrapper">' .
						'<img src="' .WIC_Admin_Setup::root_url() . 'img/ajax-loader.gif' .
					'"></span>' .
					'<div id = "subject-list-inner-wrapper"></div>' .
				'</div>' .
				'<p id = "mail-subject-legend" class = "wic-inbox-legend">Note that subjects last trained before the Forget Date configured in Email Controls are not shown and do not affect any processing.' .
					' The list shows at most 500 entries, newest first.
				</p>'; 
	
		return ( $html );
	}

	// load settings form when enter the settings tab
	protected static function setup_settings_form() {
		// ajax spinner and empty div
		$html = '<div id = "wic-load-settings-wrapper">
					<span id = "settings-ajax-loader">' .
						'<img src="' .WIC_Admin_Setup::root_url() . 'img/ajax-loader.gif' .
					'"></span>' .
				'<div id = "wic-load-settings-inner-wrapper"  class="wic-email-area-inner-wrapper"></div>' .
			'</div>'; 
		return ( $html ); 					
	}


	protected static function setup_message_review ( &$data_array ) {

		$admin_url_link_base = WIC_Admin_Setup::root_url() .'/?page=wp-issues-crm-main&entity=issue&action='; //id_search&id_requested=	

		return
			'<div id = "wic-email-subject-header"> '.
				'<div id = "wic-message-subject"></div>' .
					'<button class="wic-form-button email-action-button trigger-email-process-button" id="wic-email-approve-button" type="button" value="approve" title="Reply ready" ><span class="dashicons dashicons-thumbs-down"></span></button>' .
					'<button class="wic-form-button email-action-button trigger-email-process-button" id="wic-email-delete-button" type="button" value="delete" title="Archive" ><span class="dashicons dashicons-archive"></span></button>' .
					'<button class="wic-form-button email-action-button trigger-email-process-button" id="wic-email-block-button" type="button" value="block" title = "Archive and block sender" ><span class="dashicons dashicons-warning"></span></button>' .
					'<button class="wic-form-button email-action-button subject-line-move-button" type="button" value="left" title="Previous Line"><span class="dashicons dashicons-arrow-left" ></span></button>' .
					'<button class="wic-form-button email-action-button subject-line-move-button" type="button" value="right" title="Next Line"><span class="dashicons dashicons-arrow-right" ></span></button>' .
					'<button class="wic-form-button email-action-button trigger-email-process-button" id="wic-email-record-reply-button" type="button" value="reply" title="Record incoming and send outgoing" >Send</button>' .
					'<button class="wic-form-button email-action-button trigger-email-process-button" id="wic-email-record-button" type="button" value="record" title="Record incoming" ><span class="dashicons dashicons-yes" ></span></button>' .
					'<button class="wic-form-button email-action-button" id="wic-email-close-button" type="button" title="Close"><span class="dashicons dashicons-no"></span></button>' .
				'</div>' .
			'<div id = "wic-inbox-detail-wrapper" >' .
				'<div class="wic-dashboard-title inbox-legend"  id = "wic-message-sender-header">' .
					'<div id = "wic-message-sender">' .
						'<div id="wic-message-sender-name">' . 
						'</div>' .
						'<div id="wic-message-scroll-tracker"> (' .
							'<span id="wic-message-scroll-position"></span> of ' .
							'<span id="wic-message-scroll-total"></span>)' .
						'</div>' .
					'</div>' . // wic-message-sender
					'<button class="wic-form-button email-action-button" id="parse-popup-button" title="Show message parse results"></button>' .
					'<div id = "wic-message-sender-constituent">' .
						$data_array['constituent_id']->form_control() . 
					'</div>' . // wic-message-sender-constituent
					'<button tabindex="-1" class="wic-form-button email-action-button" id="assigned-case-popup-button"><span class="dashicons dashicons-admin-users"></span></button>' .
					'<button class="wic-form-button scroll-button" id="left-message-scroll" value="-1"><span class="dashicons dashicons-arrow-left-alt2" title = "Previous message in subject line"></span></button>' .
					'<button class="wic-form-button scroll-button" id="right-message-scroll" value="1"><span class="dashicons dashicons-arrow-right-alt2" title="Next message in subject line"></span></button>' .
				'</div>' .	// wic-message-sender-header
				'<div id = "scrollable-incoming-message-content">' .
					'<div id="recipients-display-line">' .
					'</div>' .
					'<div id="attachments-display-line">' .
					'</div>' .
					'<div id="inbox_message_text">' .
					'</div>' .
				'</div>' .
			'</div>' . // wic-inbox-detail-wrapper
			'<div id = "wic-inbox-work-area">' .
				'<div id="issue-definition-wrapper" class="wic-dashboard-title inbox-legend">'.
					$data_array['issue']->form_control() .
					$data_array['pro_con']->form_control() .
					'<button tabindex="-1" class="wic-form-button email-action-button" id="view-issue-button" type="button" title="View issue"><span class="dashicons dashicons-media-text"></span></button>' .
					'<div id = "issue_view_area">' .
						'<div id="inbox_issue_text"> . . . <em>no issue assigned</em> . . . </div>' .
						'<a tabindex = "-1" id="edit_mapped_issue_link" href="' . $admin_url_link_base . 'id_search&id_requested=" target="_blank">Edit</a>' .
					'</div>' .
					'<button tabindex="-1" class="wic-form-button email-action-button" id="new_issue_button" type="button" title="New issue from incoming message" ><span class="dashicons dashicons-plus"></span></button>' .
				'</div>'.
				'<div id = "envelope-edit-wrapper" class = "envelope-edit-wrapper" >' .
					'<table><col width="60">' .
					 	'<tr><td>Subject: </td><td>' . $data_array['message_subject']->form_control() . '</td></tr>' .
					 	'<tr><td>To: </td><td>' . $data_array['message_to']->form_control() . '</td></tr>' .
					 	'<tr><td>Cc: </td><td>' . $data_array['message_cc']->form_control() . '</td></tr>' .
					 	'<tr><td>Bcc: </td><td>' . $data_array['message_bcc']->form_control() . '</td></tr>' .
					 	'<tr><td>' . $data_array['add_cc']->form_control() . '</td><td>Cc all -- add addresses from incoming message to cc list</td></tr>' .
					 	'<tr><td>' . $data_array['include_attachments']->form_control() . '</td><td>Include attachments and all images</td></tr>' .
				 	'</table>' .
					'<p><span class="multi-line-warning-legend">Note that original message is always appended to replies on send.  Use the <span class="dashicons dashicons-visibility"></span> button to check how it will look.</span></p>' .
					'<p><span id="multi-line-warning-legend">Cannot directly edit subject or addressees for grouped messages.</span></p>' .
				'</div>' .
				'<div id = "reply-template-wrapper">'.
					$data_array['working_template']->form_control() .	
				'</div>'.
			'</div>' .
			/* sidebar for tinymce */
			'<div id="reply-help-sidebar" class="wic-mce-sidebar">
				<div class="wic-mce-sidebar-section" >
					<p><span class="dashicons dashicons-thumbs-up"></span><p>
					<p><em>Approve this email draft -- move from Assigned tab to Ready tab.</em></p>
				</div>
				<div class="wic-mce-sidebar-section" >
					<p><span class="dashicons dashicons-archive"></span><p>
					<p><em>Delete the emails with this subject line (one time).</em></p>
					<p>ShortCut key: Ctrl-Delete or Ctrl-Backspace.</p>
				</div>
				<div class="wic-mce-sidebar-section" >
					<p><span class="dashicons dashicons-warning"></span><p>
					<p><em>Delete a single email and block (silently filter and delete) all future emails from the sender.</em></p> 
					<p>You can access the Blocked address list from the Inbox.</p>
				</div>
				<div  class="wic-mce-sidebar-section">
					<p><span class="dashicons dashicons-yes" ></span></p>
					<p>Do the following for all the messages with selected subject line:</p>
					<ol>
						<li><em>Create a new constituent record if needed.</em></li>
						<li><em>Log incoming email activities under the assigned Issue.</em></li>
						<li><em>Delete the messages.</em></li>
					</ol>
				</div>
				<div  class="wic-mce-sidebar-section">
					<p><strong>Send</strong></p>				
					<p>Record and also do the following for all the messages with selected subject line:</p>
					<ol>
						<li><em>Send the replies.</em></li>
						<li><em>Log outgoing email activities.</em></li>
					</ol>
				</div>
				<div class="wic-mce-sidebar-section" >
					<p><span class="dashicons dashicons-plus"></span><p>
					<p><em>Assign the incoming email to a new issue created from its subject and content.</em></p>
				</div>
				<div  class="wic-mce-sidebar-section">
					<p><strong><i class="mce-ico mce-i-save"></i></p>
					<p><em></i>Save reply text as standard reply for issue/pro-con combination.</em></p>
				</div>
				<div  class="wic-mce-sidebar-section">
					<p><strong><i class="mce-ico mce-i-restoredraft"></i></p>
					<p><em></i>Load standard reply for issue/pro-con combination.</em></p>
				</div>
			</div>'
			;
	
	}
	
	public static $form_groups = array(
		'inbox_options'=> array(
		   'group_label' => '',
		   'group_legend' => '',
		   'initial_open' => '1',
		   'sidebar_location' => '1',
		   'fields' => array('search_subjects_phrase','constituent_id','case_assigned','issue','pro_con','subject','message_subject','message_to','message_cc','message_bcc','include_attachments','add_cc','working_template')),
   );

	// hooks not implemented
	protected function supplemental_attributes() {}
	protected function pre_button_messaging ( &$data_array ){}
	protected function post_form_hook ( &$data_array ){}
	protected function format_message (&$data_array, $message){}
}

