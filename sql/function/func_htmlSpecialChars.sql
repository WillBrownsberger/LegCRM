-- ================================================
-- Template generated from Template Explorer using:
-- Create Scalar Function (New Menu).SQL
--
-- Use the Specify Values for Template Parameters 
-- command (Ctrl-Shift-M) to fill in the parameter 
-- values below.
--
-- This block of comments will not be included in
-- the definition of the function.
-- ================================================
drop function if exists htmlSpecialChars
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
-- =============================================
-- Author:		Brownsberger
-- Create date: 
-- Description:	mimic php htmlspecialchars ENT_QUOTE 
--	(without any UTF-8 screening); 
-- =============================================
CREATE FUNCTION htmlSpecialChars 
(
	-- Add the parameters for the function here
	@string varchar(600)
)
RETURNS varchar(800)
AS
BEGIN
	-- Declare the return variable here
	DECLARE @htmlSafeString varchar(600);

	SELECT @htmlSafeString =  
		replace(
		replace(
		replace(
		replace(
		replace( @string
			,'&',	'&amp;')
			,'"',	'&quot;')
			,'''',	'&apos;')
			,'<',	'&lt;' )
			,'>',	'&gt;');
	
	-- Return the result of the function
	RETURN @htmlSafeString

END
GO

/* BETTER PRACTICE WOULD BE CLR FUNCTION USING STANDARD FUNCTION
https://stackoverflow.com/questions/639393/html-encoding-in-t-sql
*/