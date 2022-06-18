-- https://stackoverflow.com/questions/1426487/how-to-format-a-numeric-column-as-phone-number-in-sql
-- no better way?
DROP FUNCTION IF EXISTS [dbo].[formatPhoneNumber] 
GO
CREATE FUNCTION [dbo].[formatPhoneNumber](@PhoneNo VARCHAR(20))
RETURNS VARCHAR(25)
AS
BEGIN
DECLARE @Formatted VARCHAR(25)

IF (LEN(@PhoneNo) <> 10)
    SET @Formatted = @PhoneNo
ELSE
    SET @Formatted = '(' + LEFT(@PhoneNo, 3) + ')' + SUBSTRING(@PhoneNo, 4, 3) + '-' + SUBSTRING(@PhoneNo, 7, 4)

RETURN @Formatted
END
GO