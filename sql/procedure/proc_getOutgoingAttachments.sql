USE [legcrm1]
GO
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
DROP PROCEDURE IF EXISTS [dbo].[getOutgoingAttachments] 
GO
-- =============================================
CREATE PROCEDURE [dbo].[getOutgoingAttachments] 
	@outboxID bigint
AS
BEGIN 
	SELECT 
		message_attachment_cid as cid, 
		message_attachment_filename as filename, 
		message_attachment_disposition as disposition, 
		attachment_size as size, 
		attachment_type as type, 
		attachment as body
	FROM inbox_image_attachments_xref x INNER JOIN inbox_image_attachments a ON x.attachment_id = a.ID 
			WHERE x.message_id = @outboxID AND x.message_in_outbox = 1
END

