
-- STEP C PURGE NO LONGER PRESENT REGISTERED VOTERS WITH NO  ACTIVITY OR PHONE OR EMAIL
DELETE a FROM
	constituent c 
	INNER JOIN address a on a.constituent_id = c.ID
	INNER JOIN office o on o.ID = c.OFFICE
	LEFT JOIN voter_file v ON 
		c.registration_id = v.voter_id AND
		v.state_senate_district = o.office_secretary_of_state_code
	LEFT JOIN email on email.constituent_id = c.id
	LEFT JOIN phone on phone.constituent_id = c.id
	LEFT JOIN activity on activity.constituent_id = c.id
	WHERE 
		v.last_name IS NULL AND
		email.constituent_id IS NULL AND
		phone.constituent_id IS NULL AND
		activity.constituent_id IS NULL AND
		c.registration_id > ''

DELETE c FROM
	constituent c 
	INNER JOIN office o on o.ID = c.OFFICE
	LEFT JOIN voter_file v ON 
		c.registration_id = v.voter_id AND
		v.state_senate_district = o.office_secretary_of_state_code
	LEFT JOIN email on email.constituent_id = c.id
	LEFT JOIN phone on phone.constituent_id = c.id
	LEFT JOIN activity on activity.constituent_id = c.id
	WHERE 
		v.last_name IS NULL AND
		email.constituent_id IS NULL AND
		phone.constituent_id IS NULL AND
		activity.constituent_id IS NULL AND
		c.registration_id  > ''
	