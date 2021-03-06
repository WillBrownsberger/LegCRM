USE [legcrm1]
GO
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
DROP PROCEDURE IF EXISTS [dbo].[autocomplete]
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
CREATE PROCEDURE [dbo].[autocomplete] 
	@searchMode varchar(20) = '',	-- misc form fields
	@term varchar(250) = '',		-- incoming search term
	@userEmail varchar(200)			-- direct from Azure security
AS
BEGIN 
	SET NOCOUNT ON; -- SET NOCOUNT ON added to prevent extra result sets
	DECLARE 
		@selectedRows [dbo].[autocompleteTable], -- user defined table type
		@foundTotalRows int = 0,
		@targetCount int = 20, -- static for convenience
		@office int = -1,
		@table varchar(250) = 'NOT',
		@searchString Nvarchar(500),
		@termPlus Nvarchar(252),
		@parmDefinition Nvarchar(200),
		@autocompleteResults varchar(max);

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

	-- handle valid @searchMode
	IF ( 'activity_issue_all' = @searchMode )
		BEGIN;
			WITH matching_posts AS 
				(
				SELECT TOP 20 p.ID AS ID, post_title, wic_live_issue, count(a.id) as activity_count
				FROM issue p LEFT JOIN activity a on a.issue = p.id
				WHERE post_type = 'post' AND post_title like '%' + @term + '%' and p.OFFICE = @office 
				GROUP BY p.ID, post_title, wic_live_issue
				ORDER BY post_title
				)
			INSERT INTO @selectedRows
			SELECT concat( post_title,  '(' , wic_live_issue, iif( wic_live_issue > '', ' - ', '' ), activity_count, ' activities)' ), 
				ID, 
				'issue', '','' from matching_posts 
				
			SELECT @foundTotalRows = count(*) from @selectedRows; 
			if ( 0 = @foundTotalRows )
				INSERT INTO @selectedRows
				SELECT  'No issues/posts found including "' + @term + '".', -1, 'issue', '', '';				
			GOTO sendData;
		END;

	-- handle other allowed mode cases
	IF( @searchMode IN('first_name','last_name','middle_name') )
		BEGIN;
			SET @table = 'constituent';
		END;
	IF ( 'email_address' = @searchMode ) 
		BEGIN;
			SET @table = 'email';
		END;
	IF ( @searchMode IN ('city','zip','address_line') )
		BEGIN
			SET @table = 'address';
		END
	-- note that phone_number field is not currently activated in form for autocomplete
	IF( 'phone_number' = @searchMode )
		BEGIN
			SET @table = 'phone';
		END

	-- 
	IF( @searchMode IN( 'post_category','issue_value' ) )
		BEGIN
			IF( 'issue_value' = @searchMode )
				BEGIN
					SET @searchMode = 'post_category';
				END
			SET @table = 'issue';
		END

	-- quit if not set table, so validating both @searchMode and @table
	IF ( 'NOT' = @table )
		BEGIN;
		INSERT INTO @selectedRows VALUES
		( 'Unauthorized Search.', -1, 'constituent', '', '' );	
		GOTO sendData
		END;

	-- define query string from restricted table and searchMode set 
	IF ( 'activity_issue_all' != @searchMode )
		BEGIN;
			SET @searchString = 
				'SELECT TOP 20 ' 
				+ @searchMode + ', ' + @searchMode + ', ''' +  @searchMode + ''', '''', '''''
				+ ' FROM ' 
				+ @table 
				+' WHERE ' + @searchMode + ' LIKE @termPlusIN AND OFFICE = ' + cast(@office as varchar(10)) +
				'GROUP BY ' + @searchMode; 
			
			SET @termPlus = @term + '%';

			SET @ParmDefinition = N'@termPlusIN varchar(252)'; 
		
			-- @searchString is variable, but no user suppliable elements; @term is still a parameter
			INSERT INTO @selectedRows 
			EXECUTE	sp_executesql @searchString, @ParmDefinition, @termPlusIN = @termPlus
			
			SELECT @foundTotalRows = count(*) from @selectedRows; 
			IF ( 0 = @foundTotalRows )
				INSERT INTO @selectedRows
				SELECT  @term, @term, @searchMode, '', '';					
		END;
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