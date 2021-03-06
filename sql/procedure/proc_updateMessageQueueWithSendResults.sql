USE [legcrm1]
GO
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
DROP PROCEDURE IF EXISTS [dbo].[updateMessageQueueWithSendResults] 
GO
-- =============================================
CREATE PROCEDURE [dbo].[updateMessageQueueWithSendResults] 
	@outboxID bigint,
	@throttleInterval bigint,
	@requestId nvarchar(100), 
	@errorCode nvarchar(100), 
	@handleHow smallint
AS
BEGIN 
	SET NOCOUNT ON; -- SET NOCOUNT ON added to prevent extra result sets
	
	-- timing; all timeStamp variables as milliseconds from 2020-10-01 00:00:00
	DECLARE 
		@throttleUntil bigint,
		@nowStamp bigint; 
	SET @nowStamp = DATEDIFF_BIG(ms, '2020-10-01 00:00:00',SYSDATETIME());

	-- good send
	IF (@handleHow=0) 
		BEGIN
			UPDATE outbox 
			SET 
				sent_date_time = [dbo].easternDate(),
				sent_time_stamp = @nowStamp,
				sent_ok = 1
				WHERE outbox.ID = @outboxID;
		END
	-- some flavor of error (note that 1 is not an error, just no message to send)
	ELSE 
		BEGIN 
			-- message specific problem; hold message in outbox; user will have to delete and recreate
			IF (@handleHow=20)
				BEGIN
					UPDATE outbox 
					SET 
						held = 1
						WHERE outbox.ID = @outboxID;
				END
			-- user specific authorization problem -- put this office on hold
			-- super user must release through office management function
			IF (@handleHow=30)
				BEGIN
					UPDATE office SET office_send_mail_held = 1
						FROM office INNER JOIN outbox on office.ID = outbox.OFFICE 
						WHERE outbox.ID = @outboxID;
				END			
			-- user specific activity level problem -- delay this users activity out of send queue
			IF (@handleHow=31 OR @handleHow=50)
				BEGIN
					IF (@throttleInterval > 0 )
						BEGIN
							SET @throttleUntil = @nowStamp + @throttleInterval
						END
					ELSE
						BEGIN
							SET @throttleUntil = @nowStamp + 180000; 
							-- always backoff at least three minutes if failed message; 
							-- let other traffic through if it's a problem with the message
						END
					UPDATE office SET office_throttled_until = @throttleUntil 
						FROM office INNER JOIN outbox on office.ID = outbox.OFFICE 
						WHERE outbox.ID = @outboxID;
				END
			-- @handleHow=40, environmental or general authentication problem: no action to message or user; will wait in program loop 
			-- @handleHow=50, unknown problem: treat as 31 -- throttle generating user (if systemic error, all users will end up throttled -- perfect)
			-- in all error cases, log the result
			INSERT INTO [dbo].[mail_error_log]
					   ([event_type]
					   ,[error_code]
					   ,[OFFICE]
					   ,[outbox_message_id]
					   ,[event_attempt_time_stamp]
					   ,[event_date_time]
					   ,[message_subject]
					   ,[client_request_guid])
				 SELECT
						'send'
					   ,@errorCode
					   ,OFFICE
					   ,@outboxID
					   ,@nowStamp
					   ,[dbo].easternDate()
					   ,subject 
					   ,@requestId
					   FROM outbox WHERE ID = @outboxID
		END 
END
