USE [legcrm1]
GO
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
DROP PROCEDURE IF EXISTS [cacheToGeocodIo]
GO
/* 
* proc prepares results ready to be formatted for json query
* unfortunately, FOR JSON does not appear to support use of varying ID as object property name in out put object
*/


CREATE PROCEDURE [dbo].[cacheToGeocodIo] 
AS
BEGIN 
	-- would like to run at 10000 per packet which geocodio advertises, but running into intermittent errors
	SELECT TOP 1000 -- indifferent to order of geocoding process
		ID, 
		CONCAT( 
			address_raw, 
			', ',
			city_raw, 
			', ', 
			state_raw, 
			' ', zip_raw 
			) AS address_query
	FROM address_geocode_cache
	WHERE 
		lat = 0 AND 
		lon = 0 AND 
		address_raw > '' AND 
		city_raw > '' AND 
		state_raw > ''
-- FOR JSON AUTO . . . not using this approach; some lit on string truncation for long strings. https://stackoverflow.com/questions/51087037/sql-server-json-truncated-even-when-using-nvarcharmax#:~:text=SQL%20Server%20json%20truncated%20(even%20when%20using%20NVARCHAR(max)%20),-sql%20json%20sql&text=This%20returns%20a%20json%20string,characters%2C%20with%20some%20results%20truncated.&text=This%20returns%20a%20json%20string%20of%20~2000%20characters.
END