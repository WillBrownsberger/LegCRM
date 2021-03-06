USE [legcrm1]
GO
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
DROP PROCEDURE IF EXISTS [dbo].[getNextQueuedOutgoing] 
GO
-- =============================================
CREATE PROCEDURE [dbo].[getNextQueuedOutgoing] 
	@maxMessagesPerMinute smallint,
	@maxRecipientsPerDay smallint
AS
BEGIN 
	SET NOCOUNT ON; -- SET NOCOUNT ON added to prevent extra result sets
	
	-- timing; all timeStamp variables as milliseconds from 2020-10-01 00:00:00
	DECLARE 
		@nowStamp bigint, 
		@minSendGap smallint,
		@sendGapAgoStamp bigint, 
		@oneDayAgoStamp bigint; 

	SET @nowStamp		= DATEDIFF_BIG(ms, '2020-10-01 00:00:00',SYSDATETIME());
	SET @minSendGap		= 60000/@maxMessagesPerMinute; -- rounds down
	SET @sendGapAgoStamp =  @nowStamp - @minSendGap; 
	SET @oneDayAgoStamp	= @nowStamp - 86400000;
	
	-- outer loop selects oldest queued message from the lowest volume office among those not maxxed out
	SELECT TOP 1 outbox.ID as outboxID, office_email, office_label, outbox.json_email_object as outboxMessage
		FROM
		(
			-- second apply rate limits and prioritize office with least activity in 24 hours
			SELECT TOP 1 offices_with_work_to_do.ID AS ID, office_email, office_label
			FROM 
				(
				-- first (through innermost query), select offices that have messages to send
				-- here also impose the throttle_until limit; office shut down because bounced message
				-- here also limit to office enabled and not held
				SELECT outbox.OFFICE AS ID, office_email, office_label
				FROM outbox
				INNER JOIN office ON office.ID = outbox.OFFICE
				WHERE 
					sent_time_stamp = 0 AND 
					sent_OK = 0 AND -- maintain this for outbox vs draft logic; could factor out
					held = 0 AND 
					is_draft = 0 AND
					office_throttled_until < @nowstamp AND
					office_enabled = 1 AND
					office_send_mail_held = 0
				GROUP BY outbox.OFFICE, office_email, office_label
				) offices_with_work_to_do 
			LEFT JOIN outbox on offices_with_work_to_do.ID = outbox.OFFICE -- left join because do not want to exclude office with no prior recent sends
			WHERE sent_time_stamp > @oneDayAgoStamp OR sent_time_stamp IS NULL OR sent_time_stamp = 0 -- need to include the null/0 in the where option for same reason
			GROUP BY offices_with_work_to_do.ID, office_email, office_label
			HAVING 
				SUM(IIF(sent_time_stamp > @oneDayAgoStamp,1,0)) < @maxRecipientsPerDay AND -- apply day limit
				SUM(IIF(sent_time_stamp > @sendGapAgoStamp,1,0)) = 0 -- apply minute limit; expression does yield 0 if sent_date_time is null 
			ORDER BY SUM(IIF(sent_time_stamp > @oneDayAgoStamp,1,0)) ASC -- prioritize lower volume sender over prior 24 hours; 
					-- higher volume sender can still get in between 2 second eligibility delays if low volume is now sending to list
		) top_priority_office
	INNER JOIN outbox on 
	top_priority_office.ID = outbox.OFFICE
		WHERE 
			sent_time_stamp = 0 AND 
			sent_OK = 0 AND -- maintain this for outbox vs draft logic; could factor out
			held = 0 AND 
			is_draft = 0 
	ORDER BY queued_date_time ASC -- send oldest message in prioritized office
END

