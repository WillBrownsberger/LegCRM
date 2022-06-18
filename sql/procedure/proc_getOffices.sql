USE		[legcrm1]
GO

SET ANSI_NULLS ON
GO

SET QUOTED_IDENTIFIER ON
GO

DROP PROCEDURE IF EXISTS [dbo].[getOffices];
GO
CREATE PROCEDURE [dbo].[getOffices]
AS
BEGIN
	SELECT ID as OFFICE, office_email, office_last_delta_token 
	FROM [office]
	WHERE 1 = office_enabled
	
END
GO


