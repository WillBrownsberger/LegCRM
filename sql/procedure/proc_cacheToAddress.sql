USE [legcrm1]
GO
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
DROP PROCEDURE IF EXISTS [cacheToAddress]
GO
/* 
* 
* Proc gets available geocodes and sets up addresses for lookup
*
* NOT trying to economize on lookups by consolidating units within an apartment building
*		if try to match only address stubs, have to implement rules for splitting off apartment numbers to create stubs
*		slight worry about false positive matches 
*		slight worry about slowing lookups with like queries
* Let the geocoding service worry about what is an apartment number in the address
*
*/

CREATE PROCEDURE [dbo].[cacheToAddress] 
AS
BEGIN 

-- first update all addresses with available geocodes
	UPDATE a 
	SET a.lat = c.lat, a.lon = c.lon, a.zip = IIF(c.zip_clean > '', c.zip_clean, a.zip)
	FROM address a
	INNER JOIN address_geocode_cache c ON
		address_line = address_raw AND 
		city = city_raw AND 
		state = state_raw AND 
		zip = zip_raw -- need to include zip here because within some cities have same full address in diff zip codes
					  -- also, zip and city may conflict in a few bad addresses; better to pass both and possibly generate failure	
	 WHERE a.lat = 0 AND a.lon = 0 and c.lat != 0 AND c.lon != 0 

-- store records needing geocoding in cache if not already there waiting
	INSERT INTO address_geocode_cache ( address_raw, city_raw, state_raw, zip_raw )
	SELECT address_line, city, state, zip FROM address a 
			LEFT JOIN address_geocode_cache c ON
				address_line = address_raw AND city = city_raw AND state = state_raw AND zip = zip_raw
	WHERE a.lat = 0 AND a.lon = 0 -- not yet geocoded
		AND c.ID IS NULL -- not already in cache
		AND address_line > '' AND city > '' AND state > '' -- enough information to geocode with
	GROUP BY address_line, city, state, zip -- insert it only once
END