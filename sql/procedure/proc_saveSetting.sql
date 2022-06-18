USE [legcrm1]
GO
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
DROP PROCEDURE IF EXISTS [saveSetting]
GO
-- saves or updates a settings as appropriate
CREATE PROCEDURE [dbo].[saveSetting] 
  @OFFICE smallint, 	
  @setting_group varchar(50),
  @setting_name varchar(50), 
  @setting_value varchar(max)
AS
BEGIN 
	DECLARE @old_setting_value varchar(max) = '_not_found_setting_name_';

	SELECT @old_setting_value = setting_value FROM core_settings WHERE OFFICE = @OFFICE AND setting_name = @setting_name; -- note that setting name is unique regardless of setting group

	IF @old_setting_value = '_not_found_setting_name_'
		INSERT INTO core_settings (OFFICE, setting_group, setting_name, setting_value) VALUES ( @OFFICE, @setting_group, @setting_name, @setting_value );
	ELSE 
		IF ( @old_setting_value != @setting_value ) 
			UPDATE core_settings SET setting_value = @setting_value WHERE OFFICE = @OFFICE AND setting_name = @setting_name
	-- if no change in value do nothing
END

GO








