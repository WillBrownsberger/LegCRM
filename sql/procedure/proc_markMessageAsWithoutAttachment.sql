USE [legcrm1]
GO

SET ANSI_NULLS ON
GO

SET QUOTED_IDENTIFIER ON
GO
DROP PROCEDURE IF EXISTS [markMessageAsWithoutAttachment]
GO
-- mark a single message as without attachments
CREATE PROCEDURE [dbo].[markMessageAsWithoutAttachment] 
  @message_id bigint
AS
BEGIN 
   update inbox_image SET has_attachments = 0
		WHERE ID = @message_id;
END
GO


