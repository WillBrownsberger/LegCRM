USE [legcrm1]
GO
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
DROP PROCEDURE IF EXISTS [dbo].[getNowStamp] 
GO
-- =============================================
CREATE PROCEDURE [dbo].[getNowStamp] 
	@nowStamp bigint OUTPUT
AS
BEGIN 
	SET @nowStamp = DATEDIFF_BIG(ms, '2020-10-01 00:00:00',SYSDATETIME());
END

