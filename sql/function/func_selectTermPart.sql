USE LEGCRM1
drop function if exists selectTermPart
go
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
-- =============================================
-- -- Description: select a term from a term array
-- =============================================
CREATE FUNCTION selectTermPart
(
	@part int, 
	@array dbo.termSplitArray READONLY
)
RETURNS varchar(200) -- term is varchar 600 in explodeTerm
AS
BEGIN
	-- Declare the return variable 
	DECLARE @termPart varchar(200)

	-- get the indexed value
	SELECT @termPart = value from @array WHERE term_order = @part;

	-- Return the result of the function
	RETURN @termPart;

END
GO

