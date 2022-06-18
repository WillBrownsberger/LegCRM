use legcrm1
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
DROP FUNCTION IF EXISTS convertUTCStringToEasternString
GO
-- =============================================
-- Currently returns eastern standard time; could be rewritten to be daylight savings time sensitive
-- =============================================
CREATE FUNCTION convertUTCStringToEasternString(
	@inputString varchar(19)
) 
RETURNS  varchar(19)
AS
BEGIN;
DECLARE @dto datetimeoffset(0);
DECLARE @datetime2 datetime2(0);
SET @dto = cast(concat(@inputString,' +00:00') as datetimeoffset);
SET @datetime2 = cast(@dto AT TIME ZONE 'Eastern Standard Time' AS datetime2(0));
RETURN
(
	CONVERT(varchar(30), @datetime2, 120)
);
END;