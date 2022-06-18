USE		[legcrm1]
GO

SET ANSI_NULLS ON
GO

SET QUOTED_IDENTIFIER ON
GO

DROP PROCEDURE IF EXISTS [dbo].[getMessagesToBeDeleted];
GO
-- Sets up for graphclient to move messages to deleted folder
CREATE PROCEDURE [dbo].[getMessagesToBeDeleted]
  @OFFICE smallint, 	
  @office_email nvarchar(200)
AS
BEGIN 
	SELECT extended_message_id 
		FROM inbox_image 
		WHERE office_email = @office_email AND OFFICE = @office 
			AND no_longer_in_server_folder = 0 and to_be_moved_on_server = 1
END
GO



