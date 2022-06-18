use legcrm1
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
DROP FUNCTION IF EXISTS easternDate
GO
-- =============================================
-- Currently returns eastern standard time; will this be DST aware?
-- =============================================
CREATE FUNCTION easternDate() 
RETURNS datetime2
AS
BEGIN;
RETURN
(
	-- get date is always just utc and is not timezone aware; localize it and then shift
	(GETDATE() AT TIME ZONE 'UTC') AT TIME ZONE 'Eastern Standard Time'
);
END;