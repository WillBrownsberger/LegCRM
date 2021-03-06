USE [legcrm1]
GO
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
DROP PROCEDURE IF EXISTS [dbo].[doesConstituentExist] 
GO
/*
* iterates through the match process for standard legacy strategy sequence
* 
	emailfn
	email
	lnfnaddr
	lnfnaddr5
	lnfndob (not implemented)
	lnfnzip
	fnphone
*
*   returns found constituent id or zero
*/
-- =============================================
CREATE PROCEDURE [dbo].[doesConstituentExist]
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
	DECLARE @constituentId bigint = 0;
	DECLARE @is_my_constituent varchar(1) = '';

	-- emailfn
	IF ( @emailAddress > '' AND @firstName > '' ) 
		BEGIN
			SELECT TOP 1 @constituentId = constituent.ID, @is_my_constituent = is_my_constituent
				FROM constituent 
				INNER JOIN email ON email.constituent_id = constituent.ID
				WHERE 
					constituent.OFFICE = @OFFICE AND
					email_address = @emailAddress AND 
					first_name = @firstName;
			IF @constituentId > 0 GOTO done;
		END

	-- email
	IF ( @emailAddress > '' ) 
		BEGIN
			SELECT TOP 1 @constituentId = constituent_id, @is_my_constituent = is_my_constituent
				FROM constituent 
				INNER JOIN email ON email.constituent_id = constituent.ID
				WHERE 
					constituent.OFFICE = @OFFICE AND
					email_address = @emailAddress;
			IF @constituentId > 0 GOTO done;
		END

	-- lnfnaddr, lnfnaddr5
	IF ( @firstName > '' AND @lastName > '' AND @addressLine > '' )
		BEGIN
			SELECT TOP 1 @constituentId = constituent.ID, @is_my_constituent = is_my_constituent
				FROM constituent 
				INNER JOIN address ON address.constituent_id = constituent.ID
				WHERE
					constituent.OFFICE = @OFFICE AND
					first_name = @firstName AND 
					last_name = @lastName and 
					address_line = @addressLine
			IF @constituentId > 0 GOTO done;
			
			-- reoptimized, new persisted fields and indices
			SELECT TOP 1 @constituentId = constituent.ID, @is_my_constituent = is_my_constituent
				FROM constituent 
				INNER JOIN address ON address.constituent_id = constituent.ID
				WHERE
					constituent.OFFICE = @OFFICE AND
					fi_ln = concat(left(@firstName,1),@lastName) AND
					left_address_line_5 = LEFT(@addressLine,5)
			IF @constituentId > 0 GOTO done;					
		END

	--lnfnzip
	IF ( @firstName > '' AND @lastName > '' AND @zip > '' )
		BEGIN
			SELECT TOP 1 @constituentId = constituent.ID, @is_my_constituent = is_my_constituent
				FROM constituent 
				INNER JOIN address ON address.constituent_id = constituent.ID
				WHERE
					constituent.OFFICE = @OFFICE AND
					first_name = @firstName AND 
					last_name = @lastName and 
					left_zip_5 = LEFT(@zip,5)
			IF @constituentId > 0 GOTO done;
		END

	--fnphone
	IF ( @firstName > '' AND @phone > 0 )
		BEGIN
			SELECT TOP 1 @constituentId = constituent.ID, @is_my_constituent = is_my_constituent
				FROM constituent 
				INNER JOIN phone ON phone.constituent_id = constituent.ID
				WHERE
					constituent.OFFICE = @OFFICE AND
					first_name = @firstName AND 
					phone_number = @phone
			IF @constituentId > 0 GOTO done;
		END
	-- attempt an address match to a registered constituent of different name, but same address
	-- consider someone a constituent if address_line_5/zip_5 is found (for other constituent id)
	IF (  @addressLine > '' AND @zip > '' )
		BEGIN
				SELECT TOP 1 @is_my_constituent = is_my_constituent
				FROM constituent 
				INNER JOIN address ON address.constituent_id = constituent.ID
				WHERE 
					constituent.OFFICE = @OFFICE AND
					registration_id > '' AND
					left_address_line_5 = LEFT(@addressLine,5) AND left_zip_5 = LEFT(@zip,5)
		END
done: 	
-- at this point, @constituent_id is still 0 or a found constituent
SELECT @constituentId as 'constituentId', @is_my_constituent as 'is_my_constituent';
END

