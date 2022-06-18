USE		[legcrm1]
GO

SET ANSI_NULLS ON
GO

SET QUOTED_IDENTIFIER ON
GO

DROP PROCEDURE IF EXISTS [dbo].areOutlookCategoriesEnabled;
GO
-- Sets up for graphclient to move messages to deleted folder
CREATE PROCEDURE [dbo].areOutlookCategoriesEnabled
@OFFICE smallint,
@categoriesEnabled bit OUTPUT
AS
BEGIN 
	-- initialize out in case no record found
	SET @categoriesEnabled = 0;
	SELECT TOP 1 @categoriesEnabled = office_outlook_categories_enabled
		FROM office
		WHERE ID = @OFFICE
END
GO



