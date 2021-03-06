USE [legcrm1]
GO
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
DROP PROCEDURE IF EXISTS [dbo].[doesConstituentExistElsewhere] 
GO
/*
* iterates through the match process for standard legacy strategy sequence
* BUT LOOKING ELSEWHERE TO ESTABLISH NON-CONSTITUENT
* 
	emailfn
	email
	lnfnaddr
	lnfnaddr5
	lnfndob (not implemented)
	lnfnzip
	fnphone
*
*	-- this proc is to be executed after doesConstituentExist finds no constituent in own office
*   -- if found constituent in own office, will use that value for is_my_constituent even if empty
*
*   -- looks for actual match, then falls back to zip inference
*/
-- =============================================
CREATE PROCEDURE [dbo].[doesConstituentExistElsewhere]
	@OFFICE smallint,
	@emailAddress varchar(200),
	@firstName varchar(50),
	@lastName varchar(50),
	@addressLine varchar(100),
	@zip varchar(10),
	@phone bigint
AS
BEGIN 
	-- SET NOCOUNT ON to prevent extra result sets from
	-- interfering with final SELECT.
	SET NOCOUNT ON;

	-- do output as select for compatibility with php implementation
	DECLARE @is_my_constituent varchar(1) = '';
	DECLARE @found_count int = 0;

	-- get own district AND OFFICE TYPE (HOUSE VS SENATE)
	DECLARE @district varchar(5);
	DECLARE @office_type varchar(30);
	SELECT 
		@district = office_secretary_of_state_code,
		@office_type = office_type
	FROM office 
	WHERE ID = @office;

	-- in each case, only assigning value if one registered constituent found
	-- looking only in other offices; should plan to build out all offices for maximum effect
	-- only in other offices of same type!

	-- emailfn
	IF ( @emailAddress > '' AND @firstName > '' ) 
		BEGIN
			SELECT @found_count = COUNT(registration_id) FROM
				(
				SELECT registration_id
				FROM constituent 
				INNER JOIN email ON email.constituent_id = constituent.ID
				INNER JOIN office on office.ID = constituent.OFFICE
				WHERE 
					registration_id > '' AND
					constituent.OFFICE != @OFFICE AND -- for registered implies, that other district
					office.office_type = @office_type AND 
					email_address = @emailAddress AND 
					first_name = @firstName
				GROUP BY registration_id
				) reg_ids

			-- only want to accept of single registration id
			IF( @found_count = 1 )
				BEGIN
					SET	@is_my_constituent = 'N';
					GOTO done;
				END
		END

	-- email
	IF ( @emailAddress > '' ) 
		BEGIN
			SELECT @found_count = COUNT(registration_id) FROM
				(
				SELECT registration_id
				FROM constituent 
				INNER JOIN email ON email.constituent_id = constituent.ID
				INNER JOIN office on office.ID = constituent.OFFICE
				WHERE 
					registration_id > '' AND
					constituent.OFFICE != @OFFICE AND -- for registered implies, that other district
					office.office_type = @office_type AND 
					email_address = @emailAddress
				GROUP BY registration_id
				) reg_ids

			-- only want to accept if single registration id
			IF( @found_count = 1 )
				BEGIN
					SET	@is_my_constituent = 'N';
					GOTO done;
				END
		END

	-- lnfnaddr, lnfnaddr5
	IF ( @firstName > '' AND @lastName > '' AND @addressLine > '' )
		BEGIN
			SELECT @found_count = COUNT(registration_id) FROM
				(
				SELECT registration_id
				FROM constituent 
				INNER JOIN address ON address.constituent_id = constituent.ID
				INNER JOIN office on office.ID = constituent.OFFICE
				WHERE
					registration_id > '' AND
					office.office_type = @office_type AND 
					constituent.OFFICE != @OFFICE AND
					first_name = @firstName AND 
					last_name = @lastName and 
					address_line = @addressLine
				GROUP BY registration_id
				) reg_ids
			IF( @found_count = 1 )
				BEGIN
					SET	@is_my_constituent = 'N';
					GOTO done;
				END
		
			-- also try softer match -- controlled by requirement that only one reg_id found
			SELECT @found_count = COUNT(registration_id) FROM
				(
				SELECT registration_id
				FROM constituent 
				INNER JOIN address ON address.constituent_id = constituent.ID
				INNER JOIN office on office.ID = constituent.OFFICE
				WHERE
					registration_id > '' AND
					office.office_type = @office_type AND 
					constituent.OFFICE != @OFFICE AND
					fi_ln = concat(left(@firstName,1),@lastName) AND
					left_address_line_5 = LEFT(@addressLine,5)
				GROUP BY registration_id
				) reg_ids
			IF( @found_count = 1 )
				BEGIN
					SET	@is_my_constituent = 'N';
					GOTO done;
				END
		END

	--lnfnzip
	IF ( @firstName > '' AND @lastName > '' AND @zip > '' )
		BEGIN
			SELECT @found_count = COUNT(registration_id) FROM
				(
				SELECT registration_id
				FROM constituent 
				INNER JOIN address ON address.constituent_id = constituent.ID
				INNER JOIN office on office.ID = constituent.OFFICE
				WHERE
					registration_id > '' AND
					office.office_type = @office_type AND 
					constituent.OFFICE != @OFFICE AND
					first_name = @firstName AND 
					last_name = @lastName and 
					left_zip_5 = LEFT(@zip,5)
				GROUP BY registration_id
				) reg_ids
			IF( @found_count = 1 )
				BEGIN
					SET	@is_my_constituent = 'N';
					GOTO done;
				END
		END

	--fnphone
	IF ( @firstName > '' AND @phone > 0 )
		BEGIN
			SELECT @found_count = COUNT(registration_id) FROM
				(
				SELECT registration_id
				FROM constituent 
				INNER JOIN phone ON phone.constituent_id = constituent.ID
				INNER JOIN office on office.ID = constituent.OFFICE
				WHERE
					registration_id > '' AND
					office.office_type = @office_type AND 
					constituent.OFFICE != @OFFICE AND
					first_name = @firstName AND 
					phone_number = @phone
				GROUP BY registration_id
				) reg_ids
			IF( @found_count = 1 )
				BEGIN
					SET	@is_my_constituent = 'N';
					GOTO done;
				END
		END
	-- at this point, @is_my_constituent is still empty 
	-- make inference from voter file if possible
	IF (@zip > '')
		BEGIN
			DECLARE @zip_in_district bit = -1;
			DECLARE @zip_out_of_district bit = -1;
			IF (@office_type = 'state_senate_district')
				BEGIN
					SELECT 
						@zip_in_district 
							= max(IIF(@district = state_senate_district, 1, 0)),
						@zip_out_of_district 
							= max(IIF(@district != state_senate_district, 1, 0))
					FROM voter_file
					WHERE residential_address_zip_code = @zip
				END
			ELSE IF (@office_type = 'state_rep_district')
				BEGIN
					SELECT 
						@zip_in_district 
							= max(IIF(@district = state_rep_district, 1, 0)),
						@zip_out_of_district 
							= max(IIF(@district != state_rep_district, 1, 0))
					FROM voter_file
					WHERE residential_address_zip_code = @zip
				END			
		
			IF (@zip_in_district = 1 AND @zip_out_of_district = 0)
				SET @is_my_constituent = 'Y';
			ELSE IF (@zip_in_district = 0 AND @zip_out_of_district = 1)
				SET @is_my_constituent = 'N';

		END
done: 	
SELECT @is_my_constituent as 'is_my_constituent';
END

