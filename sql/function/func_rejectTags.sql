USE LEGCRM1
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
DROP FUNCTION IF EXISTS rejectTags
GO
-- =============================================
-- Description:	Truncate all of a string after appearance of a tag
-- =============================================
CREATE FUNCTION rejectTags
(
	@term varchar(600)
)
RETURNS varchar(600)
AS
BEGIN
	DECLARE @cleanTerm varchar(600);
	SET @cleanTerm = 
		IIF ( 
			0 < PATINDEX('%[<>]%', @term ), 
			SUBSTRING( @term, 1, PATINDEX('%[<>]%', @term) - 1),
			@term
		);

	RETURN @cleanTerm;

END
GO

