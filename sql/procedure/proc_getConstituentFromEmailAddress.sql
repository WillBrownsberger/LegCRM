USE [legcrm1]
GO
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
DROP PROCEDURE IF EXISTS [dbo].[getConstituentFromEmailAddress] 
GO
-- =============================================
CREATE PROCEDURE [dbo].[getConstituentFromEmailAddress]
	@OFFICE smallint,
	@emailAddress varchar(200),
	@constituentId bigint OUTPUT
AS
BEGIN 
	SET @constituentId = 0;

	-- if email address already exists in office, just return constituent id
	SELECT TOP 1 @constituentId = constituent_id 
	FROM email
	WHERE OFFICE = @OFFICE AND email_address = @emailAddress;
	-- dropped logic that would have created new constituents 
	-- by importing other office data for email
END

