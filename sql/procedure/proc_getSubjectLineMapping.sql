USE [legcrm1]
GO
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
DROP PROCEDURE IF EXISTS [dbo].[getSubjectLineMapping] 
GO

-- =============================================
-- search sql -- return most recent learned association if there is one	not forgotten
--   note that if multiple matches due to wildcards, most recent taken
-- ==============================================
CREATE PROCEDURE [dbo].[getSubjectLineMapping]
	@OFFICE smallint,
	@subjectLine varchar(400)
AS
BEGIN 
	-- SET NOCOUNT ON to prevent extra result sets from
	-- interfering with final SELECT.
	SET NOCOUNT ON;

	-- first get forget date setting with default 60 if setting is missing
	DECLARE @forgetDateInterval smallint = 60;
	SELECT @forgetDateInterval = cast(setting_value as smallint)
	FROM core_settings
	WHERE setting_name = 'forget_date_interval' AND OFFICE = @office;

	-- compute forget date from interval
	DECLARE @forgetDate date;
	SET @forgetDate = DATEADD(d,-@forgetDateInterval, [dbo].easternDate());

	SELECT mapped_issue, mapped_pro_con FROM subject_issue_map 
	WHERE @subjectLine LIKE incoming_email_subject COLLATE LATIN1_GENERAL_100_CS_AS_SC_UTF8
	AND email_batch_time_stamp > @forgetDate AND OFFICE = @OFFICE
	ORDER BY email_batch_time_stamp DESC
	OFFSET 0 ROWS FETCH NEXT 1 ROWS ONLY

END

