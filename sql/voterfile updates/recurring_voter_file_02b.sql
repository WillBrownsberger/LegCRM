
-- STEP B 
-- soft match additional not registered constituents 
-- and claim them as registered
-- based on city and first 5 of fn, ln, address_line
-- note: leave name and address lines unrefreshed
--	they were good enough to soft match, but leaving them as
--     allows recognition that constituent was soft matched
--		. . . in case soft match was bad
-- if not reversed, then will catch up on address in future refresh
UPDATE c 
   SET 
	  --[OFFICE] = <OFFICE, smallint,>
	  --,c.[last_name] = v.last_name
      --,c.[first_name] = v.first_name
      --,c.[middle_name] = v.middle_name
      c.[date_of_birth] = v.date_of_birth
      --,[year_of_birth] = <year_of_birth, smallint,>
      --,[is_deceased] = <is_deceased, tinyint,>
      --,[is_my_constituent] = <is_
      --,[case_assigned] = <case_assigned, bigint,>
      --,[case_review_date] = <case_review_date, datetime2(0),>
      --,[case_status] = <case_status, varchar(1),>
      ,c.[gender] = v.gender
      --,[occupation] = <occupation, varchar(50),>
      --,[employer] = <employer, varchar(50),>
      ,c.[registration_id] = v.voter_id
      ,c.[registration_date] = v.registration_date
      ,c.[registration_status] = v.voter_status
      ,c.[party] = v.party
      ,c.[ward] = v.ward
      ,c.[precinct] = v.precinct
      --,[council_district] = <council_district, varchar(50),>
      ,c.[state_rep_district] = v.state_rep_district
      ,c.[state_senate_district] = v.state_senate_district
      ,c.[congressional_district] = v.congressional_district
      --,[councilor_district] = <councilor_district, varchar(50),>
      ,c.[county] = v.county_name
      --,[other_district_1] = <other_district_1, varchar(50),>
      --,[other_district_2] = <other_district_2, varchar(50),>
      --,[last_updated_time] = <last_updated_time, datetime2(0),>
      --,[last_updated_by] = <last_updated_by, int,>
      --,[salutation] = <salutation, varchar(50),>
      --,[other_system_id] = <other_system_id, varchar(50),>
      --,[conversion_id] = <conversion_id, bigint,>
      ,[voter_file_refresh_date] = [dbo].easternDate()
	FROM constituent c 
	INNER JOIN voter_file v ON 
		LEFT(c.first_name,5) = LEFT(v.first_name,5) AND
		LEFT(c.last_name,5) = LEFT(v.last_name,5) 
	INNER JOIN address a ON a.constituent_id = c.ID
	WHERE 
		a.city = v.city_town_name AND
		LEFT(a.address_line,5) = LEFT(
			concat(
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
		,5) AND
		c.registration_id = '';


