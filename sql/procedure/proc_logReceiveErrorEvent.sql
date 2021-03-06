USE [legcrm1]
GO
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
DROP PROCEDURE IF EXISTS [dbo].[logReceiveErrorEvent] 
GO
-- =============================================
CREATE PROCEDURE [dbo].[logReceiveErrorEvent] 
	@OFFICE smallint, 
	@errorCode nvarchar(100),
	@requestId nvarchar(100)
AS
BEGIN 
	SET NOCOUNT ON; -- SET NOCOUNT ON added to prevent extra result sets
	
	-- timing; all timeStamp variables as milliseconds from 2020-10-01 00:00:00
	DECLARE @nowStamp bigint; 
	SET @nowStamp = DATEDIFF_BIG(ms, '2020-10-01 00:00:00',SYSDATETIME());

	INSERT INTO [dbo].[mail_error_log]
				([event_type]
				,[error_code]
				,[OFFICE]
				,[outbox_message_id]
				,[event_attempt_time_stamp]
				,[event_date_time]
				,[message_subject]
				,[client_request_guid])
			VALUES(
				'receive'
				,@errorCode
				,@OFFICE
				,0
				,@nowStamp
				,[dbo].[easternDate]()
				,'' 
				,@requestId
				)
	-- on synch error, reset delta token
	IF (@errorCode = 'syncStateNotFound' OR @errorCode = 'resyncRequired' OR @errorCode = 'BadRequest' )
		BEGIN
			UPDATE office SET office_last_delta_token = '', office_last_delta_token_refresh_time = '1900-01-01' WHERE ID = @OFFICE
		END
END
