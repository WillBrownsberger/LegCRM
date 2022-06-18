DROP FUNCTION IF EXISTS [dbo].[getSetting] 
GO
CREATE FUNCTION [dbo].[getSetting](@OFFICE bigint, @settingName varchar(50))
RETURNS varchar(max)
AS
BEGIN
DECLARE @settingValue varchar(max) = '';
SELECT @settingValue = setting_value FROM core_settings WHERE OFFICE = @office AND setting_name = @settingName;
RETURN @settingValue;
END
GO