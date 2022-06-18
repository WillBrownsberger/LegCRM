USE [legcrm1]
GO
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
DROP PROCEDURE IF EXISTS [checkName]
GO
-- validating name as a proper name, not finding constituent
CREATE PROCEDURE [dbo].[checkName]
  @name varchar(200),
  @findResultParm smallint OUTPUT -- = 0 did not effectively initialize this output variable for operations in procedure
AS
BEGIN 
   SET @findResultParm = 0; -- have to initialize this here in proc body or is null in second branch and returns null here
   DECLARE @testfirst varchar(50) = '';
   SELECT TOP 1 @testfirst = first_name FROM voter_file WHERE first_name = @name;
   IF (@testfirst > '') SET @findResultParm = 1;	

   DECLARE @testlast varchar(50) = '';
   SELECT TOP 1 @testlast = last_name FROM voter_file WHERE last_name = @name;
   IF (@testlast > '') SET @findResultParm = @findResultParm + 2;
END


