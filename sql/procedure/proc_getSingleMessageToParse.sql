USE		[legcrm1]
GO

SET ANSI_NULLS ON
GO

SET QUOTED_IDENTIFIER ON
GO

DROP PROCEDURE IF EXISTS [dbo].getSingleMessageToParse;
GO
-- Sets up for graphclient to move messages to deleted folder
CREATE PROCEDURE [dbo].getSingleMessageToParse
@IDpointer bigint,
@OFFICE smallint OUTPUT,
@office_email varchar(200) OUTPUT,
@ID bigint OUTPUT,
@extended_message_id varchar(512) OUTPUT,
@message_json varchar(max) OUTPUT
AS
BEGIN 
	-- initialize out in case no record found
	SET @OFFICE = 0;
	SET @office_email = '';
	SET @ID = 0;
	SET @extended_message_id = '';
	SET @message_json = '';
	
	SELECT TOP 1 
		  @OFFICE=OFFICE
		, @office_email = office_email
		, @ID = ID
		, @extended_message_id = extended_message_id
		, @message_json = message_json
		FROM inbox_image
		WHERE 
			folder_uid = 0 AND --parsed_message_json = '' AND *** folder_uid is 0 iff parsed_message_json is '' (add efficient index with these four numeric selectors)
			no_longer_in_server_folder = 0 AND
			to_be_moved_on_server = 0 AND
			ID > @IDpointer
	 ORDER BY ID
			; 
			-- if parsed_message_json = '', should always be true that to_be_moved_on_server = 0
			-- belt and suspenders
END
GO



