USE [legcrm1]
GO

/****** Object:  StoredProcedure [dbo].[searchBox]    Script Date: 10/1/2020 6:41:05 AM ******/
SET ANSI_NULLS ON
GO

SET QUOTED_IDENTIFIER ON
GO
DROP PROCEDURE IF EXISTS [dbo].[fullDeleteConstituent] 
GO
-- =============================================
-- Author:		WillBrownsberger
-- Create date: 9/20/2020
-- IMPLEMENT ALL DELETES FOR A SINGLE CONSTITUENT
-- =============================================
CREATE PROCEDURE [dbo].[fullDeleteConstituent] 
	@ID bigint -- constituent.id
AS
BEGIN 
	DELETE FROM constituent WHERE ID = @ID;
	DELETE FROM activity WHERE constituent_id = @ID;
	DELETE FROM address WHERE constituent_id = @ID;
	DELETE FROM email WHERE constituent_id = @ID;
	DELETE FROM phone WHERE constituent_id = @ID;
END
GO


