USE [legcrm1]
GO

SET ANSI_NULLS ON
GO

SET QUOTED_IDENTIFIER ON
GO
DROP PROCEDURE IF EXISTS [stashDeltaToken]
GO
-- mark a single message as no longer on server -- NOT used by full synch
CREATE PROCEDURE [dbo].[stashDeltaToken] 
  @OFFICE smallint, 	
  @deltaToken nvarchar(4000)

AS
BEGIN 
   update office SET office_last_delta_token = @deltaToken,office_last_delta_token_refresh_time = [dbo].easternDate()
		WHERE ID = @office; -- ON this file, ID is the true office number; OFFICE is of user who set up record
END
GO


