USE [legcrm1]
GO
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
DROP PROCEDURE IF EXISTS [dbo].[isConstituentFiltered] 
GO
-- =============================================
CREATE PROCEDURE [dbo].[isConstituentFiltered]
	@OFFICE smallint,
	@isMyConstituent varchar(1),
	@responseSubjectLine varchar(400) OUTPUT,
	@responseBody varchar(max) OUTPUT,
	@filtered bit OUTPUT
AS
BEGIN 
	-- initialize output variables (cannot be initialized in parameter declaration(?))
	SET @responseSubjectLine = '';
	SET @responseBody = '';
	SET @filtered = 0;

	-- now determine whether to invoke non-constituent auto responder
	DECLARE @use_non_constituent_responder varchar(1) = [dbo].[getSetting](@OFFICE,'use_non_constituent_responder');
	IF ('' = @use_non_constituent_responder ) SET @use_non_constituent_responder = '1'; -- default is no use
	IF ( 2 = @use_non_constituent_responder AND 'N' = @isMyConstituent ) OR
	   ( 3 = @use_non_constituent_responder AND 'Y' != @isMyConstituent )
		BEGIN
			SET @responseSubjectLine = [dbo].[getSetting](@OFFICE, 'non_constituent_response_subject_line');
			SET @responseBody = [dbo].[getSetting](@OFFICE, 'non_constituent_response_message');
			IF ( LEN(@responseSubjectLine) > 4 AND LEN(@responseBody) > 19 ) 
				-- don't to send near empty inadvertently; backing up javascript edit controls
				BEGIN
					SET @filtered = 1;
				END
		END
END

