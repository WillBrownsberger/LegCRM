USE [legcrm1]
GO
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
DROP PROCEDURE IF EXISTS [dbo].[queueOutgoing] 
GO
-- =============================================
CREATE PROCEDURE [dbo].[queueOutgoing] 
	@OFFICE smallint,
	@outgoingMessageJson nvarchar(max)
AS
BEGIN 
	INSERT INTO outbox(
		is_draft,
		is_reply_to,
		subject,
		queued_date_time,
		serialized_email_object,
		json_email_object,
		to_address_concat,
		office,
		sent_time_stamp,
		sent_ok, 
		held,
		sent_date_time
	) VALUES (
		JSON_VALUE(@outgoingMessageJson,'$.Is_draft'),
		JSON_VALUE(@outgoingMessageJson,'$.Is_reply_to'),
		LEFT(JSON_VALUE(@outgoingMessageJson,'$.Subject'),400),
		[dbo].[easternDate](),
		'',
		@outgoingMessageJson,
		CONCAT( JSON_VALUE(@outgoingMessageJson,'$.To_array[0].Name'), JSON_VALUE(@outgoingMessageJson,'$.To_array[0].Address')),
		@OFFICE,
		0,
		0,
		0,
		''
	)
END

