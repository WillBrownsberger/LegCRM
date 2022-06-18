
-- STEP D Add truly new constituents
-- first the constituent record
INSERT INTO [dbo].[constituent]
           ([OFFICE]
           ,[last_name]
           ,[first_name]
           ,[middle_name]
           ,[date_of_birth]
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
           ,[voter_file_refresh_date])
SELECT  o.ID
		,v.[last_name]
		,v.[first_name]
		,v.[middle_name]
		,v.[date_of_birth]
		,'Y'
		,v.[gender]
		,v.[voter_id]
		,v.[registration_date]
		,v.[voter_status]
        ,v.[party]
        ,v.[ward]
        ,v.[precinct]
        ,v.[state_rep_district]
        ,v.[state_senate_district]
        ,v.[congressional_district]
        ,v.[county_name]
		,[dbo].easternDate()
		,1
		,[dbo].easternDate()
  FROM [dbo].[voter_file] v
  INNER JOIN [office] o ON o.office_secretary_of_state_code = v.state_senate_district
  LEFT JOIN constituent c ON 
	c.registration_id = v.voter_id AND
	c.OFFICE = o.ID
  WHERE c.id IS NULL

-- now the address recordS
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

	SELECT
            c.OFFICE
           ,c.ID
           ,'wic_reserved_4'
           ,concat(
				residential_address_street_no,
				IIF(
					residential_address_street_no_suffix IS NOT NULL, 
					residential_address_street_no_suffix, 
					''
				),
				' ',
				residential_address_street_name,
				IIF(
					residential_address_street_apt_no IS NOT NULL,
					CONCAT
					(
						' APT ',
						residential_address_street_apt_no
					),
					''
				)
			)
           ,v.city_town_name
		   ,'MA'
           ,left(v.residential_address_zip_code,10) -- zip code field contains some bad entries
           ,[dbo].easternDate()
           ,1
           ,0
           ,0
	FROM voter_file v
	INNER JOIN constituent c on c.registration_id = v.voter_id
	LEFT JOIN address a on a.constituent_id = c.id
	WHERE a.id IS NULL

