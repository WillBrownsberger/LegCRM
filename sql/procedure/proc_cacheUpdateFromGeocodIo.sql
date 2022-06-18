USE [legcrm1]
GO
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
DROP PROCEDURE IF EXISTS [cacheUpdateFromGeocodIo]
GO
CREATE PROCEDURE [dbo].[cacheUpdateFromGeocodIo] 
	@jsonData nvarchar(max)
AS
BEGIN 
	UPDATE a
	SET lat = jsonData.lat,
		lon = jsonData.lon,
		zip_clean = jsonData.zip_clean,
		matched_clean_address = jsonData.matched_clean_address,
		geocode_vendor  = 'GeocodIo',
		geocode_vendor_accuracy = jsonData.geocode_vendor_accuracy,
		geocode_vendor_accuracy_type = jsonData.geocode_vendor_accuracy_type,
		geocode_vendor_source = jsonData.geocode_vendor_source,
		geocode_computed_date = [dbo].easternDate()
	FROM address_geocode_cache a
	INNER JOIN (
		SELECT * 
		FROM OPENJSON ( @jsonData )  
		WITH (  
			  cache_ID int '$.cache_ID',
			  lat decimal(10,8) '$.lat',
			  lon decimal(11,8) '$.lon',
			  zip_clean varchar(10) '$.zip_clean',
			  matched_clean_address varchar(200)'$.matched_clean_address',
			  geocode_vendor_accuracy decimal(3,2) '$.geocode_vendor_accuracy',
			  geocode_vendor_accuracy_type varchar(100) '$.geocode_vendor_accuracy_type',
			  geocode_vendor_source varchar(100) '$.geocode_vendor_source'
		)       
	) jsonData ON cache_ID = a.ID
END


