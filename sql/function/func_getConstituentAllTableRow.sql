USE [legcrm1]
GO

/****** Object:  UserDefinedFunction [dbo].[GetConstituentAll]    Script Date: 9/23/2020 4:46:26 PM ******/
SET ANSI_NULLS ON
GO

SET QUOTED_IDENTIFIER ON
GO
DROP FUNCTION IF EXISTS [dbo].[getConstituentAllTableRow] 
GO
CREATE FUNCTION [dbo].[getConstituentAllTableRow] 
(
	@id bigint
	-- returns a single row table with best information on constituent in autocomplete format
)
RETURNS @constituentTable TABLE -- [dbo].[autocompleteTable] -- can't seem to just use the definition here
(
	[label] [varchar](200) NULL,
	[value] [varchar](200) NULL,
	[entity_type] [varchar](20) NULL,
	[email_name] [varchar](200) NULL,
	[latest_email_address] [varchar](200) NULL
)
AS
BEGIN
	-- Declare needed variables
	DECLARE 
	@fulldata varchar(450),
	@name varchar(100),
	@address varchar(200), 
	@email varchar(100), 
	@phone varchar(20);

	SET @name = ( SELECT 
		CONCAT(
			first_name, iif(first_name > '', ' ',''),
			middle_name, iif(first_name > '', ' ',''),
			last_name, iif(first_name > '', ' ','')
			)
		FROM constituent WHERE ID = @id
		);
	SET @address = (
		SELECT TOP 1 CONCAT(
			address_line, iif(address_line > '', ' ',''), 
			city, iif(city > '', ' ',''), 
			state, iif(state > '', ' ',''), 
			zip, iif(state > '', ' ','') 
			)
		FROM address WHERE constituent_id = @id
		ORDER BY iif( address_line > '', 1,0) desc,
		iif( city > '', 1,0) desc,
		iif( zip > '', 1,0) desc,
		iif( state > '', 1,0) desc
		);
	SET @email =  (
		SELECT TOP 1 email_address 
		FROM email
		WHERE email_address > '' AND constituent_id = @id
		ORDER BY last_updated_time DESC
		);
	SET @phone = (
		SELECT TOP 1 [dbo].[formatPhoneNumber](phone_number) 
		FROM phone
		WHERE phone_number > '' AND constituent_id = @id
		ORDER BY last_updated_time DESC
		);

	SET @name	 = TRIM(@name);
	SET @address = TRIM(@address);
	SET @email	 = TRIM(@email);
	SET @phone	 = TRIM(@phone);
	SET @fulldata = CONCAT_WS (' | ', @name, @address, @email, @phone);

	INSERT INTO @constituentTable
	SELECT @fulldata, @id, 'constituent' , @name, @email;
	RETURN;

END
GO


