USE [legcrm1]
GO
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
DROP PROCEDURE IF EXISTS [saveNewGraphMessage]
GO
-- add new message; 
-- if doing full sync, mark message as extant at time of that run
CREATE PROCEDURE [dbo].[saveNewGraphMessage] 
  @OFFICE smallint, 	
  @office_email nvarchar(200),
  @message nvarchar(max),
  @message_id nvarchar(400), -- message id never reused in folder https://stackoverflow.com/questions/43568178/message-ids-are-known-to-change-on-move-copy-etc-but-are-they-ever-repeated
  @startup bit,
  @now_sync_stamp bigint, -- tracker for full sync case -- used to identify records existing in both server and image for full sync run
  @has_attachments bit,
  @insertedMessageID bigint OUTPUT  -- only used as diagnostic
AS
BEGIN 
	-- has the message already been permanently stored?
   DECLARE @countexisting smallint;

   SELECT @countexisting = count(ID)
		FROM inbox_image 
		WHERE extended_message_id = @message_id AND office_email = @office_email AND OFFICE = @office;
		-- note that message_id is probably unique across office_email folders, but know that should be unique within office_email and folder; 
		-- adding OFFICE to the where condition is basically redundant, but would allow a newly created office with a previously used email to repopulate with all traffic to that email
		-- note that ******message_id IS CASE SENSITIVE AND USES CASE SENSITIVE COLLATION******.
   IF ( @countexisting > 0 )
   -- IF EXISTING 
		BEGIN
			SET @insertedMessageID  = -1;
		-- IF EXISTING ON A FULL RESYNC, RESTAMP AS EXTANT ON THIS RUN
			IF ( @startup = 1 )
				BEGIN
					UPDATE inbox_image 
					SET now_stamp_of_last_sync = @now_sync_stamp
					WHERE extended_message_id = @message_id AND office_email = @office_email AND OFFICE = @office;
 				END
		END
   -- IF NOT, STORE THE MESSAGE
   ELSE
		BEGIN
			INSERT INTO inbox_image ( 
					OFFICE
					, office_email
					, folder_uid
					, message_json
					, extended_message_id
					, now_stamp_of_last_sync
					, has_attachments 
					) 
				VALUES  (
					  @OFFICE
					, @office_email
					, 0
					, @message  -- relying on implict type conversions to varchar https://docs.microsoft.com/en-us/sql/t-sql/functions/cast-and-convert-transact-sql?view=sql-server-ver15
					, @message_id
					, @now_sync_stamp
					, @has_attachments
					);
			SELECT @insertedMessageID = CAST(scope_identity() AS int)
		END
END
GO


