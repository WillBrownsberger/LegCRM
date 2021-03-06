USE [legcrm1]
GO
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
DROP PROCEDURE IF EXISTS [dbo].[saveConstituentActivity] 
GO
/*
* saves activity record -- adding constituent if needed
*/
-- =============================================
CREATE PROCEDURE [dbo].[saveConstituentActivity]
	-- identity parameters 
	@OFFICE smallint,
	@emailAddress varchar(200),
	@firstName varchar(50), -- omitting middle name, unreliable
	@lastName varchar(50),
	@addressLine varchar(100),
	@city varchar(50),-- not in lookup
	@state varchar(50), -- not in lookup
	@zip varchar(10),
	@phone bigint,
	-- variable for preset constituent id
	@constituentId bigint,
	-- email direction parameter
	@in_or_out bit, 
		-- 0 is incoming (email-process); 
		-- 1 is outgoing (email-send)
		-- determines activity type
	-- activity parameters
	@pro_con varchar(1),
	@issue int,
	@activity_date datetime2(0), 
	@activity_note varchar(max),
	-- additional parameters
	@is_my_constituent varchar(1),
	@related_inbox_image_record int, 
	@related_outbox_record int,
	-- unsanitized is false for both incoming and outgoing
	@current_user_id int
AS
BEGIN 

	-- SET NOCOUNT ON to prevent extra result sets from
	SET NOCOUNT ON;
	-- HANDLE TIME ZONE CONVERSION FOR ACTIVITY DATE ON INCOMING MAIL
	IF (@in_or_out = 0) SET @activity_date = [dbo].convertUTCStringToEasternString(@activity_date);
	-- SET UP ADDITIONAL SAVE VARIABLES	
	DECLARE @parsedType varchar(21) = 'incoming_email_parsed';
	DECLARE @activityType varchar(21) = 
		IIF(@in_or_out = 0,
			'wic_reserved_00000000',
			'wic_reserved_99999999'
		);
	DECLARE @email_batch_originated_constituent bit = 0;
	-- TO BE SET LATER
	DECLARE @activity_id int = 0;

	-- validate constituentID before attempting to use it
	-- constituent assigned by parse process could be deleted by user
	-- if @constituentID not valid, set @constituentID to zero
	-- note that doesConstituentExist will NOT recreate an invalid constituentID from orphan email
	DECLARE @testConstituentID bigint = 0;
	SELECT @testConstituentID = ID FROM constituent where ID = @constituentID AND OFFICE = @OFFICE;
	SET @constituentID = @testConstituentID;

	BEGIN TRANSACTION;
	BEGIN TRY;

	-- if do not have a constituentID, do lookup, 
	-- using temp table simply to pass result from proc
	IF (@constituentID = 0)
		BEGIN
			CREATE TABLE #temp_constituent_id_table (constituentId bigint, is_my_constituent varchar(1) );
			INSERT INTO #temp_constituent_id_table (constituentId, is_my_constituent)
				EXECUTE [doesConstituentExist]
					@OFFICE,
					@emailAddress, -- in context, never empty
					@firstName,
					@lastName,
					@addressLine,
					@zip,
					@phone;	
			SELECT @constituentId = constituentID 
				FROM #temp_constituent_id_table;
			DROP TABLE #temp_constituent_id_table;
		END


	-- if did not have and did not find constituent_id, save a new one
	IF (@constituentID = 0)
		BEGIN
			-- save constituent record
			INSERT INTO constituent
				( 
				OFFICE
				, first_name
				, last_name
				, is_my_constituent
				, last_updated_time
				, last_updated_by
				)
			VALUES (
				@OFFICE
				, @firstName
				, @lastName
				, @is_my_constituent
				, [dbo].easternDate()
				, @current_user_id
			)

			-- set constituent id
			SELECT @constituentID = CAST(scope_identity() AS int)

			-- set flag that added constituent
			SET @email_batch_originated_constituent = 1;

			-- save email record (always present in email transaction)
			INSERT INTO	email
				(
				OFFICE
				, constituent_id
				, email_type
				, email_address
				, last_updated_time
				, last_updated_by
				) 
			VALUES (
				@OFFICE
				, @constituentID
				, @parsedType
				, @emailAddress
				, [dbo].easternDate()
				, @current_user_id
			);

			-- if have address data, save address record
			IF ( 
				@addressLine > '' OR
				@city > '' OR
				@state> '' OR
				@zip > ''
			)
				BEGIN
					INSERT INTO address
						(
							OFFICE
							, constituent_id
							, address_type
							, address_line
							, city
							, [state]
							, zip
							, last_updated_time
							, last_updated_by
						) 
					VALUES
						(
							@OFFICE
							, @constituentId
							, @parsedType
							, @addressLine
							, @city
							, @state
							, @zip
							, [dbo].easternDate()
							, @current_user_id
						)
				END

			-- if have phone data, save phone record
			IF (@phone > 0)
				BEGIN
					INSERT INTO phone
						(
						OFFICE
						, constituent_id 
						, phone_type
						, phone_number
						, last_updated_time
						, last_updated_by
						)
					VALUES
						(
						@office
						, @constituentId
						, @parsedType
						, @phone
						, [dbo].easternDate()
						, @current_user_id
						)
				END
		END
	ELSE
		-- have found constituent . . . do no updates to name or address, but 
		BEGIN
			-- check and add the email if it doesn't exist @@emailAddress always > '')
			DECLARE @countExistingEmail int = 0;
			SELECT @countExistingEmail = count(*) 
				FROM email 
				WHERE email_address = @emailAddress and constituent_id = @constituentId

			IF ( @countExistingEmail = 0 )
				BEGIN;
					INSERT INTO	email
						(
						OFFICE
						, constituent_id
						, email_type
						, email_address
						, last_updated_time
						, last_updated_by
						) 
					VALUES (
						@OFFICE
						, @constituentID
						, @parsedType
						, @emailAddress
						, [dbo].easternDate()
						, @current_user_id
					);
				END;
			-- if have a phone, check and add the phone as new if it doesn't exist
			IF ( @phone > 0 )
				BEGIN;
					DECLARE @countExistingPhone int = 0;
					SELECT @countExistingPhone = count(*) 
						FROM phone 
						WHERE phone_number = @phone AND constituent_id = @constituentId

					IF ( @countExistingPhone = 0 )
						BEGIN;
							INSERT INTO phone
								(
								OFFICE
								, constituent_id 
								, phone_type
								, phone_number
								, last_updated_time
								, last_updated_by
								)
							VALUES
								(
								@office
								, @constituentId
								, @parsedType
								, @phone
								, [dbo].easternDate()
								, @current_user_id
								)
						END;
				END;
		END
	-- save activity record
	INSERT INTO activity
		(
		OFFICE
		, constituent_id
		, activity_date
		, activity_type
		, issue
		, pro_con
		, activity_note
		, email_batch_originated_constituent
		, related_inbox_image_record
		, related_outbox_record
		, last_updated_time
		, last_updated_by
	)	
	VALUES (
		@OFFICE
		, @constituentId
		, @activity_date
		, @activityType
		, @issue
		, @pro_con
		, @activity_note
		, @email_batch_originated_constituent
		, @related_inbox_image_record
		, @related_outbox_record
		, [dbo].easternDate()
		, @current_user_id
	);

	-- get activity_id
	SELECT @activity_id = CAST(scope_identity() AS int);

	-- in the case of an new message insert, update the message record with found values
	IF ( @related_inbox_image_record > 0 ) 
		BEGIN
			UPDATE inbox_image
			SET 
			 assigned_constituent = @constituentId
		 	,mapped_issue = @issue
			,mapped_pro_con = @pro_con
			WHERE ID = @related_inbox_image_record AND OFFICE = @OFFICE
		END

	END TRY
	BEGIN CATCH
		IF @@TRANCOUNT > 0  
			ROLLBACK TRANSACTION;  
		SET @activity_id = 0;
	END CATCH;  
  	IF @@TRANCOUNT > 0  
		COMMIT TRANSACTION;  
	
	-- REPORT RESULTS
	SELECT 
		  @activity_id as activity_id
		, @constituentId as constituent_id
		, first_name
		, middle_name
		, last_name
		, salutation
		, gender
		, TRIM(CONCAT(first_name, ' ', last_name))  as name
		FROM constituent
		WHERE ID = @constituentId;
END

