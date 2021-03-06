USE [legcrm1]
GO
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
DROP PROCEDURE IF EXISTS [dbo].[getConstituentFromEmailAddressOBSOLETE] 
GO
-- =============================================
-- OBSOLETE -- decided against using create constituent logic in this proc
-- =============================================
CREATE PROCEDURE [dbo].[getConstituentFromEmailAddressOBSOLETE]
	@OFFICE smallint,
	@emailAddress varchar(200),
	@constituentId bigint OUTPUT
AS
BEGIN 
	SET @constituentId = 0;

	-- if email address already exists in office, just return constituent id
	SELECT TOP 1 @constituentId = constituent_id 
	FROM email
	WHERE OFFICE = @OFFICE AND email_address = @emailAddress;
	IF (@constituentId > 0) GOTO done;
	
	-- if email address exists somewhere else as a registered constituent create constituent with email and registration data only
	DECLARE @otherOfficeConstituentID bigint = 0;
	DECLARE @countMatches int = 0;
	
	-- first make sure that there is only one matched reg_id; don't want generic spam from's to propagate
	--   as valid constituent emails
	SELECT @countMatches = count(registration_id) 
	FROM 
		(SELECT registration_id 
		FROM email 
		INNER JOIN constituent on email.constituent_id = constituent.ID
		WHERE email_address = @emailAddress AND registration_id > ''
		GROUP BY registration_id) matching_reg_ids ;
	IF (@countMatches > 1) GOTO done; -- no good match found, constituent_id = 0;

	-- now take that match (could be same match in multiple offices) and use it to create new owned constituent
	SELECT TOP 1 @otherOfficeConstituentID = constituent_id
	FROM email 
	INNER JOIN constituent on email.constituent_id = constituent.ID
	WHERE email_address = @emailAddress AND registration_id > '';

	IF( @otherOfficeConstituentID > 0 )
		BEGIN
			-- get own senate district
			DECLARE @district varchar(5);
			SELECT @district = office_secretary_of_state_code 
			FROM office 
			WHERE ID = @office;
			-- add constituent record
			INSERT INTO [dbo].[constituent]
			   ([OFFICE]
			   ,[last_name]
			   ,[first_name]
			   ,[middle_name]
			   ,[date_of_birth]
			   ,[year_of_birth]
			   ,[is_my_constituent]
			   ,[gender]
			   ,[registration_id]
			   ,[registration_date]
			   ,[registration_status]
			   ,[party]
			   ,[ward]
			   ,[precinct]
			   ,[state_rep_district]
			   ,[state_senate_district]
			   ,[congressional_district]
			   ,[county]
			   ,[last_updated_time]
			   ,[last_updated_by]
			)
			SELECT
			   @OFFICE
			   ,last_name
			   ,first_name
			   ,middle_name
			   ,date_of_birth
			   ,year_of_birth
			   ,IIF(state_senate_district = @district,'Y','N')
			   ,gender
			   ,registration_id
			   ,registration_date
			   ,registration_status
			   ,party
			   ,ward
			   ,precinct
			   ,state_rep_district
			   ,state_senate_district
			   ,congressional_district
			   ,county
			   ,GETDATE()
			   ,1
			FROM constituent WHERE ID = @otherOfficeConstituentID
			SELECT @constituentId = CAST(scope_identity() AS int)
			-- add address record
			INSERT INTO [dbo].[address]
				([OFFICE]
				,[constituent_id]
				,[address_type]
				,[address_line]
				,[city]
				,[state]
				,[zip]
				,[last_updated_time]
				,[last_updated_by]
				,[lat]
				,[lon])
			 SELECT TOP 1
				@OFFICE
				,@constituentId
				,address_type
				,address_line
				,city
				,state
				,zip
				,GETDATE()
				,1
				,0
				,0
			 FROM address WHERE constituent_id = @otherOfficeConstituentID AND address_type = 'wic_reserved_4'
			-- add email record
			INSERT INTO [dbo].[email]
				([OFFICE]
				,[constituent_id]
				,[email_type]
				,[email_address]
				,[last_updated_time]
				,[last_updated_by])
			VALUES(
           		@OFFICE
				,@constituentId
				,'incoming_email_parsed'
				,@emailAddress
				,GETDATE()
				,1
			)
		END
done: 	
-- at this point, @constituent_id is 0 or a found constituent or a newly created constituent for the office
END

