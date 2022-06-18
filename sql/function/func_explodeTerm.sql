use legcrm1
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
DROP FUNCTION IF EXISTS explodeTerm
GO
-- =============================================
-- Author:		WillBrownsberger
-- Create date: 
-- Description:	Expects string; separates it by spaces returns indexed table
-- INLINE TABLE VALUED FUNCTION DOES NOT SPECIFY THE TABLE IN THE "RETURNS" STATEMENT
-- =============================================
CREATE FUNCTION explodeTerm 
(	
	@term varchar(600)

)
RETURNS TABLE
AS
-- explodes on spaces; trims exterior punctuation
RETURN
(
	SELECT 
		ROW_NUMBER() OVER ( ORDER BY PATINDEX(TRIM(' ''",;:./\\' FROM value), @term) ) as term_order, 
		TRIM( ' ''",;:./\\'  FROM value) AS value 
	FROM STRING_SPLIT ( @term, ' ') 
	WHERE TRIM( ' ''",;:./\\' FROM value) > ''
);