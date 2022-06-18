LINENO 0 -- for debugging
USE legcrm1;
DROP FUNCTION  IF EXISTS [dbo].[getConstituentFullTable]
GO
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO


CREATE FUNCTION [dbo].[getConstituentFullTable]
(
	@id_string varchar(max) -- space separated
	-- parses the id string and builds a table by calling getConstituentAllTableRow once for each ID
	--	"All" refers to collecting all information; getConstituentAllTableRow returns only one row
)
RETURNS @constituentTable TABLE -- [dbo].[autocompleteTable] -- can't seem to just use the definition here
(
	[label] [varchar](200) NULL,
	[value] [varchar](200) NULL,
	[entity_type] [varchar](20) NULL,
	[email_name] [varchar](200) NULL,
	[latest_email_address] [varchar](200) NULL
)
AS
BEGIN

DECLARE @idArray  [dbo].[termSplitArray],
		@idArrayPointer int = 0,
		@lastIDRow int = 0;


		INSERT INTO @idArray 
		SELECT * FROM [dbo].[explodeTerm] ( @id_string )

		-- count the terms
		SELECT @lastIDRow = count(*) FROM @idArray;

		WHILE ( @idArrayPointer < @lastIDRow )
			BEGIN
			SET @idArrayPointer = @idArrayPointer + 1;
			INSERT INTO @constituentTable
			SELECT * FROM [dbo].[getConstituentAllTableRow]([dbo].[selectTermPart](@idArrayPointer, @idArray));
			END

	RETURN 
END
GO