USE [legcrm1]
GO

SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
DROP FUNCTION IF EXISTS getUserFromEmail
GO
-- =============================================
CREATE FUNCTION getUserFromEmail
(	
	@user_email varchar(200)
)
RETURNS TABLE 
AS
RETURN 
(
	SELECT office_id as user_office_number, user_max_capability from [user] where user_email = @user_email and [user_enabled] = 1
)
GO



