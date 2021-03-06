USE [legcrm1]
GO
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
DROP PROCEDURE IF EXISTS [dbo].[saveParsedMessageObject] 
GO
-- =============================================
CREATE PROCEDURE [dbo].saveParsedMessageObject 
	@messageJson nvarchar(max)
AS
BEGIN 
	UPDATE inbox_image SET
		folder_uid = JSON_VALUE(@messageJson,'$.inbox_image_id'),
		from_personal = JSON_VALUE(@messageJson,'$.from_personal'),
		from_email = JSON_VALUE(@messageJson,'$.from_email'),
		from_domain = JSON_VALUE(@messageJson,'$.from_domain'),
		-- converted to eastern time zone; should be DST aware
		email_date_time = [dbo].[convertUTCStringToEasternString](JSON_VALUE(@messageJson,'$.email_date_time')), 
		subject = LEFT( JSON_VALUE(@messageJson,'$.subject'), 400),
		category = JSON_VALUE(@messageJson,'$.category'),
		snippet = LEFT( JSON_VALUE(@messageJson,'$.snippet'),990),
		account_thread_id = JSON_VALUE(@messageJson,'$.account_thread_id'),	
		-- converted to eastern time zone; should be DST aware;l T-SQL substring is indexed from 1
		activity_date = substring([dbo].[convertUTCStringToEasternString](JSON_VALUE(@messageJson,'$.email_date_time')),1,10),
		mapped_issue = JSON_VALUE(@messageJson,'$.Batch_mapped_issue'),
		mapped_pro_con = JSON_VALUE(@messageJson,'$.Batch_mapped_pro_con'),
		parsed_message_json = @messageJson,
		assigned_constituent = JSON_VALUE(@messageJson,'$.Batch_found_constituent_id'),
		to_be_moved_on_server = IIF(JSON_VALUE(@messageJson,'$.Batch_filtered') = 'true',1,0),
		is_my_constituent_guess = JSON_VALUE(@messageJson,'$.Batch_is_my_constituent')
	WHERE ID = JSON_VALUE(@messageJson,'$.inbox_image_id')
END

