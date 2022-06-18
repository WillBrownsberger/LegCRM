USE [legcrm1]
GO

SET ANSI_NULLS ON
GO

SET QUOTED_IDENTIFIER ON
GO
DROP PROCEDURE IF EXISTS [markSingleNoLongerOnServer]
GO
-- mark a single message as no longer on server -- NOT used by full synch
CREATE PROCEDURE [dbo].[markSingleNoLongerOnServer] 
  @OFFICE smallint, 	
  @office_email nvarchar(200),
  @message_id nvarchar(400)

AS
BEGIN 
   update inbox_image SET no_longer_in_server_folder = 1
		WHERE extended_message_id = @message_id AND office_email = @office_email AND OFFICE = @office AND no_longer_in_server_folder = 0;
END
GO


