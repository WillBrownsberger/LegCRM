USE [legcrm1]
GO
/****** Object:  StoredProcedure [dbo].[searchBox]    Script Date: 9/20/2020 5:16:46 PM ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
DROP PROCEDURE IF EXISTS [dbo].[searchBox] 
GO
-- =============================================
-- Author:		WillBrownsberger
-- Create date: 9/20/2020
-- Description:	Search Box Logic
--	  splits incoming term
--	  does a series of selects
--	  for each select for constituents, references function getConstituentFullTable
--	  
-- =============================================
CREATE PROCEDURE [dbo].[searchBox] 
	@searchMode varchar(20) = '',	-- constituent, issue, both or constituent_email
	@term varchar(250) = '',		-- incoming search term
	@userEmail varchar(200)		-- direct from Azure security
AS
BEGIN 
	SET NOCOUNT ON; -- SET NOCOUNT ON added to prevent extra result sets
	DECLARE 
		@termSplitArray [dbo].[termSplitArray],  -- user defined table type (max term part length oversized at 600)
		@selectedRows [dbo].[autocompleteTable], -- user defined table type
		@tempConstituents [dbo].[autocompleteTable],
		@foundConstituents int = 0,
		@foundTotalRows int = 0,
		@firstTerm varchar(200),
		@lastTerm varchar(200),
		@id_string varchar(200),
		@issueSearchString varchar(200) = '', -- will limit number of terms considered to 10
		@termPointer int = 0,
		@targetCount int = 20, -- static for convenience
		@countTerms int,
		@containsPhrase varchar(600),
		@office int = -1;

	-- before all else, check authorization and identify_office
	SELECT @office = user_office_number from getUserFromEmail(@userEmail)
	IF ( @office < 0 )
		BEGIN;
		INSERT INTO @selectedRows VALUES
		( 'Unauthorized Search.', -1, 'constituent', '', '' );	
		GOTO sendData
		END;
	-- discard everything after an initial tag open or close
	SET @term = dbo.rejectTags(@term);
	
	-- cannot just set @termSplitArray equal to result of function (??!) 
	-- so have to two step it -- insert/select
	INSERT @termSplitArray
	SELECT * FROM explodeTerm( @term );

	-- count the terms
	SELECT @countTerms = count(*) FROM @termSplitArray;

	-- if zero terms branch to the end with message
	IF ( 0 = @countTerms )
		BEGIN
			INSERT INTO @selectedRows VALUES
			( 'Not enough to search with in phrase: "' + @term + '".', -1, 'constituent', '', '' );
			GOTO sendData;
		END
	-- not zero terms, extract first and last (adding right wild cards)
	SET @firstTerm = ( [dbo].[selectTermPart] ( 1, @termSplitArray ) );
	SET @lastTerm =  ( [dbo].[selectTermPart] ( @countTerms, @termSplitArray ) );

	-- do constituent search if search mode constituent or both
	-- begins a series of repetitive code . . . code write as 
	IF ( @searchMode = 'both' or @searchMode = 'constituent' )
		BEGIN
			IF ( 1 = @countTerms ) 
				BEGIN
					-- consider the term as a possible first or last name if no numerics in it
					IF ( @firstTerm NOT LIKE '%[0-9]%' )
						BEGIN
							WITH base_search AS
								(	
								SELECT TOP (@targetCount - @foundConstituents) ID from constituent
								WHERE office = @office AND ( first_name like @firstTerm + '%' OR last_name like @firstTerm +'%' )
								ORDER BY last_name, first_name ASC 
								) 
							SELECT @id_string = STRING_AGG(ID, ' ') FROM base_search; 

							IF ( @id_string > '' ) 
								BEGIN
									INSERT INTO @tempConstituents
									SELECT * FROM getConstituentFullTable( @id_string );
									SELECT @foundConstituents = count(*) FROM @tempConstituents;
									IF @foundConstituents >= 20 GOTO handleIssues;
					 			END; -- found names
						END; -- first term like name -- usually end here
					-- for a single term  try a phone number search if it makes sense
					IF ( @firstTerm LIKE '[0-9][0-9][0-9]%' AND @firstTerm NOT LIKE '%[a-z]%' )
						BEGIN
							WITH base_search AS
								(	
								SELECT TOP (@targetCount - @foundConstituents) constituent_id as ID from phone 
								WHERE office = @office AND ( phone_number like @firstTerm + '%' )
								ORDER BY phone_number ASC 
								) 
							SELECT @id_string = STRING_AGG(ID, ' ') FROM base_search; 

							IF ( @id_string > '' ) 
								BEGIN
									INSERT INTO @tempConstituents
									SELECT * FROM getConstituentFullTable( @id_string );
									SELECT @foundConstituents = count(*) FROM @tempConstituents;
									IF @foundConstituents >= 20 GOTO handleIssues;
					 			END; -- found phone numbers
						END; -- first term like phone number
					-- finish off single term situation by trying as an email if haven't filled quota
					WITH base_search AS
						(	
						SELECT TOP (@targetCount) constituent_id as ID from email 
						WHERE office = @office AND ( email_address like @firstTerm + '%' )
						ORDER BY email_address ASC 
						) 
					SELECT @id_string = STRING_AGG(ID, ' ') FROM base_search; 
					-- found emails
					IF ( @id_string > '' ) 
						BEGIN
							-- accumulate the email find
							INSERT INTO @tempConstituents
							SELECT * FROM getConstituentFullTable( @id_string );
							-- check total count now
							SELECT @foundConstituents = count(*) FROM @tempConstituents;
							IF @foundConstituents >= 20 GOTO handleIssues;
					 	END -- found emails
				END -- count terms = 1
			-- now considering the term a possible name with more than one term supplied
			ELSE -- IF ( @countTerms > 1 ), already handled zero
				BEGIN;
					WITH base_search AS
						(	
						SELECT TOP (@targetCount - @foundConstituents) ID from constituent
						WHERE office = @office AND ( first_name like @firstTerm + '%' and last_name like @lastTerm + '%' )
						ORDER BY last_name, first_name ASC 
						) 
					SELECT @id_string = STRING_AGG(ID, ' ') FROM base_search; 
					IF ( @id_string > '' ) 
						BEGIN
							INSERT INTO @tempConstituents
							SELECT * FROM getConstituentFullTable( @id_string );
							SELECT @foundConstituents = count(*) FROM @tempConstituents;
							IF @foundConstituents >= 20 GOTO handleIssues
						END -- found consituents by name
				END; -- more than one found

			-- now consider is it worth doing an address search (regardless of term count)
			-- use the whole term for address search
			-- must begin with number
			IF (    @term LIKE '[0-9]%'
				 OR @term LIKE 'one%'
				 OR @term LIKE 'three%'
				 OR @term LIKE 'four%'
				 OR @term LIKE 'five%'
				 OR @term LIKE 'six%'
				 OR @term LIKE 'seven%'
				 OR @term LIKE 'eight%'
				 OR @term LIKE 'nine%'
				 OR @term LIKE 'zero%' )
				BEGIN
				WITH base_search AS
						(	
						SELECT TOP (@targetCount - @foundConstituents) constituent_id as ID from address
						WHERE office = @office AND ( address_line like @term + '%' )
						ORDER BY address_line ASC 
						) 
					SELECT @id_string = STRING_AGG(ID, ' ') FROM base_search; 
					IF ( @id_string > '' ) 
						BEGIN
							INSERT INTO @tempConstituents
							SELECT * FROM getConstituentFullTable( @id_string );
							SELECT @foundConstituents = count(*) FROM @tempConstituents;
							IF @foundConstituents >= 20 GOTO handleIssues
						END -- found address
				END; -- term like address
		END -- both or constituent
	
	handleIssues:
	-- before proceeding further to handle issues, consolidate duplicates across multiple constituent searches
	-- if nothing found, put in an appropriate not found row
	IF ( 0 = @foundConstituents AND @searchMode != 'constituent_email' )
		BEGIN;
			INSERT INTO @selectedRows VALUES
			( 'No constituent name, email, street address or phone found using phrase "' + @term + '".', -1, 'constituent', '', '' );
		END;
	ELSE
		BEGIN;
			INSERT INTO @selectedRows
			SELECT [label], [value], entity_type, email_name, latest_email_address FROM @tempConstituents
			GROUP BY [label], [value], entity_type, email_name, latest_email_address
		END;
	-- now handle issues
	IF ( @searchMode = 'both' or @searchMode = 'issue' )
		BEGIN
			IF ( @searchMode = 'both' ) 
				BEGIN
					-- separate constituent results
					INSERT INTO @selectedRows VALUES
						( REPLICATE ('-',30), -1, 'constituent', '', '' );					
					-- first, if have a full boat of constituents, just return this and ask for more input if searching for issue
					-- this is annoying, so don't do it.
					/*IF (@foundConstituents >= @targetCount )
						BEGIN;
							INSERT INTO @selectedRows VALUES
							( 'Enter more characters to find issues.', -1, 'constituent', '', '' );	
							GOTO sendData
						END; */
				END -- search mode was just both
			SELECT @foundConstituents = count(*) FROM @selectedRows; -- update to include spacer rows
			-- using full text index but uncertain of phrase structure
			BEGIN TRY
				SET @containsPhrase = [dbo].[containsPhraseFromTerm](@term);
				INSERT INTO @selectedRows
				SELECT TOP (@targetCount) post_title, ID, 'issue', '', ''
				FROM ISSUE 
				WHERE office = @office AND 
					( 
						contains(post_title, @containsPhrase) OR 
						( post_title like '%'+@term+'%' ) 
					) AND 
					post_type = 'post'
				ORDER BY post_title;
			END TRY
			BEGIN CATCH
				INSERT INTO @selectedRows VALUES
					( 'Could not parse search phrase "' + @term + '" as post search.  Use strings separated by spaces.', -1, 'constituent', '', '' );	
			END CATCH
			-- add a not found notice if appropriate
			SELECT @foundTotalRows = count(*) FROM @selectedRows;
			IF ( @foundConstituents = @foundTotalRows ) 
				BEGIN
					INSERT INTO @selectedRows VALUES
						( 'No issues found searching for phrase "' + @term + '".', -1, 'constituent', '', '' );	
				END; -- found no issues
		END;  -- both or issue

	-- this search is fresh, although appearing at the end of the proc
	-- used to return autocomplete in email function
	-- no secondary searcch
	IF ( @searchMode = 'constituent_email' ) 
		BEGIN;
			-- gather constituent emails prioritizing first constituents with high activity, then their latest updated email_address if more than one
			INSERT INTO @selectedRows
			SELECT TOP (@targetCount) -- directly inserting final result rows
				trim( first_name + ' ' + last_name ), 
				c.id, 
				'constituent_email', 
				trim( first_name + ' ' + last_name ), 
				email_address 
			FROM constituent c INNER JOIN email e on e.constituent_id = c.id
				LEFT JOIN activity a on a.constituent_id = c.id
			WHERE c.office = @office AND ( 
				( @countTerms = 1 AND first_name LIKE @firstTerm + '%' ) OR -- term only applies where single term . . .
				( @countTerms = 1 AND last_name LIKE @firstTerm + '%' ) OR
				( @countTerms = 1 AND email_address LIKE @firstTerm + '%' ) OR
				( @countTerms > 1 AND first_name LIKE @firstTerm + '%' and last_name LIKE @lastTerm + '%' ) 
				)
			GROUP BY c.id, first_name, last_name, email_address, e.last_updated_time
			ORDER BY count(a.ID) DESC, e.last_updated_time DESC
			-- check count
			SELECT @foundConstituents = count(*) FROM @selectedRows; 
			if (0 = @foundConstituents)
				BEGIN
					INSERT INTO @selectedRows
					SELECT @term, 0, 'constituent_email', @term, @term;	
				END
		END; -- constituent_email

	-- send back the results or excuses; never null
	sendData:

	SELECT TOP (@targetCount + 3) -- allow for separators; give room for the not found message 
		dbo.htmlSpecialChars( [label] ) as label,
		dbo.htmlSpecialChars( [value] ) as value,
		dbo.htmlSpecialChars( [entity_type] ) as entity_type,
		dbo.htmlSpecialChars( [email_name] ) as email_name,
		dbo.htmlSpecialChars( [latest_email_address] ) as latest_email_address
	FROM @selectedRows
	FOR JSON PATH

END