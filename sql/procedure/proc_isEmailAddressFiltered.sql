USE [legcrm1]
GO
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
DROP PROCEDURE IF EXISTS [dbo].[isEmailAddressFiltered] 
GO
-- =============================================
CREATE PROCEDURE [dbo].[isEmailAddressFiltered]
	@OFFICE smallint,
	@emailAddress varchar(200)
AS
BEGIN 
	-- SET NOCOUNT ON to prevent extra result sets from
	-- interfering with final SELECT.
	SET NOCOUNT ON;
	DECLARE @box varchar(200);
	DECLARE @domain varchar(200);
	DECLARE @split smallint;
	
	SET @split = PATINDEX('%@%',@emailAddress);
	IF (@split > 0)
		SET @box = LEFT(@emailAddress, @split - 1);
	ELSE
		SET @box = '';
	SET @domain = RIGHT(@emailAddress, LEN(@emailAddress) - @split );

	DECLARE @filtered bit = 0;

	SELECT @filtered = 
		MAX( 
			IIF(from_email_box = @box OR block_whole_domain = 1, 1, 0) 
		)
	FROM inbox_incoming_filter
	WHERE from_email_domain = @domain AND OFFICE = @OFFICE
	GROUP BY from_email_domain

	SELECT @filtered AS blocked; -- not found possible on prior select
END

