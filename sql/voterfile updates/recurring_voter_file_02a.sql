--********************
-- STOP!
-- before running this script, office table office_secretary_of_state_code must be populated
--	  at least for target offices
--*******************

-- STEP A: CONSTITUENTS CONTINUING IN SAME DISTRICT
-- part 1: refresh address for existing constituents
UPDATE a 
	SET 
		address_line = 
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
			),
		a.city = v.city_town_name,
		a.state = 'MA',
		a.zip = left(v.residential_address_zip_code,10),-- some bad zips in voter file
		lat = 0,
		lon = 0
	FROM constituent c 
	INNER JOIN address a on a.constituent_id = c.ID
	INNER JOIN voter_file v ON c.registration_id = v.voter_id
	INNER JOIN office o on o.ID = c.OFFICE
	WHERE v.state_senate_district = o.office_secretary_of_state_code -- only refreshing if still in district
	AND address_type = 'wic_reserved_4'

-- part 2: stamp existing consituents refreshed (consider refreshed even if had no wic_reserved_4 if matched reg_id)
UPDATE c SET voter_file_refresh_date = [dbo].easternDate()
	FROM constituent c 
	INNER JOIN voter_file v ON c.registration_id = v.voter_id
	INNER JOIN office o on o.ID = c.OFFICE
	WHERE v.state_senate_district = o.office_secretary_of_state_code





