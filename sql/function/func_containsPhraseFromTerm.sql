drop function if exists containsPhraseFromTerm
GO
-- =============================================
CREATE FUNCTION containsPhraseFromTerm
(	
	@term varchar(600)
)
RETURNS varchar(800)
AS
BEGIN;
	DECLARE 
		@termSplitArray [dbo].[termSplitArray],
		@countTerms int,
		@returnVal varchar(800),
		@nearSpan int = 10;
	INSERT @termSplitArray
	SELECT * FROM explodeTerm( @term );
	SELECT @countTerms = count(*) FROM @termSplitArray;
	IF ( @countTerms = 0 ) 
		SET @returnVal = '';
	IF ( @countTerms = 1 )
		BEGIN
			SELECT @returnVal = [value] FROM @termSplitArray;
		END
	-- else 
	IF ( @countTerms > 1 )
		BEGIN
			IF ( @countTerms > 5 ) SET @nearSpan = 2*@countTerms
			SELECT	@returnVal = STRING_AGG([value],',') FROM @termSplitArray;
			SET @returnVal = 'NEAR((' + @returnVal + '),' +  CAST(@nearSpan as VARCHAR(10)) + ')';
		END
	RETURN @returnVal;
END;

	