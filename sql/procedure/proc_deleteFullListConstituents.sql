USE [legcrm1]
GO

/****** Object:  StoredProcedure [dbo].[deleteFullListConstituents]    Script Date: 10/2/2020 4:28:19 AM ******/
SET ANSI_NULLS ON
GO

SET QUOTED_IDENTIFIER ON 
GO
DROP PROCEDURE IF EXISTS [dbo].[deleteFullListConstituents] 
GO
-- =============================================
-- Author:		WillBrownsberger
-- Create date: 9/20/2020
-- IMPLEMENT ALL DELETES FOR A LIST OF CONSTITUENT
-- =============================================
CREATE PROCEDURE [dbo].[deleteFullListConstituents] 
	@tempTable varchar(250) 
AS
	DECLARE @deleteQuery nvarchar(4000);
	set @deleteQuery = 
		N'BEGIN TRANSACTION;' +
		N'DELETE FROM constituent FROM constituent INNER JOIN ' + @tempTable + N' ON constituent.id = '		  +	@tempTable + N'.id;' +
		N'DELETE FROM activity FROM activity INNER JOIN ' +		@tempTable + N' ON  activity.constituent_id = ' + @tempTable + N'.id;' +
		N'DELETE FROM address  FROM address INNER JOIN ' +		@tempTable + N' ON  address.constituent_id = '  + @tempTable + N'.id;' +
		N'DELETE FROM email  FROM email INNER JOIN ' +			@tempTable + N' ON  email.constituent_id = '	  + @tempTable + N'.id;' +
		N'DELETE FROM phone  FROM phone INNER JOIN ' +			@tempTable + N' ON  phone.constituent_id =  '   + @tempTable + N'.id;' +
		N'COMMIT TRANSACTION;';
	EXECUTE	sp_executesql @deleteQuery
	print @deleteQuery;
GO


