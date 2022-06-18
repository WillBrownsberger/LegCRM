USE		[legcrm1]
GO

SET ANSI_NULLS ON
GO

SET QUOTED_IDENTIFIER ON
GO

DROP PROCEDURE IF EXISTS [dbo].[getMessagesWithAttachments];
GO
-- gets messages that need attachment retrieval (has_attachments, but none retrieved yet)
-- replaces logic within synch that coupled retrieval of messages necessarily in same loop
-- this version will run in same loop, but can pick up messages that need attachments picked from prior failed loops
-- 		was experiencing some loss of attachments in conditions not fully understood
-- this routine could fail to pick up all attachments for a message with more than one if there was a failure in the attachment retrieval itself
CREATE PROCEDURE [dbo].[getMessagesWithAttachments]
	@office smallint
AS
BEGIN 
	SELECT extended_message_id, ii.ID 
	FROM inbox_image ii
	LEFT JOIN inbox_image_attachments_xref iiax on iiax.message_id = ii.ID
	WHERE 
	OFFICE = @office AND -- scope to a single office (reflecting office by office message retrieval)
	no_longer_in_server_folder = 0 AND  -- don't look for messages deleted from inbox
	to_be_moved_on_server = 0 AND		-- don't bother looking for messages instantly archived
	has_attachments = 1 AND				-- set on insert of new message based on c# logic (json_value function doesn't handle message body well so can't do w/i sql)
	iiax.message_id is null				-- compare to xref (will not catch messages where only some attachments loaded)
	-- no need to group by id since only capturing those without xrf links
END
GO


