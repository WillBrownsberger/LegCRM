DROP EXTERNAL DATA SOURCE testBlob;
DROP DATABASE SCOPED CREDENTIAL testBlob;
DROP MASTER KEY;
CREATE MASTER KEY ENCRYPTION BY PASSWORD = 'NEWPASSWORD';
CREATE DATABASE SCOPED CREDENTIAL testBlob 
WITH IDENTITY = 'SHARED ACCESS SIGNATURE',
SECRET = 'ASDFAS'; -- get from Storage Account > Shared Access Signature > SAS TOKEN, drop the '?'
CREATE EXTERNAL DATA SOURCE testBlob with (TYPE = BLOB_STORAGE, 
	LOCATION = 'https://testblobstorage91.blob.core.windows.net/voter-file-2020-11-02',
	-- location from Storage Account > Shared Access Signature > SAS URL (drop the SAS Token)
	CREDENTIAL = testBlob );

COMMENT OUT THIS LINE AND UNCOMMENT THE NEXT LINE WHEN YOU ARE READY TO RUN!
-- TRUNCATE TABLE [legcrm1].[dbo].[voter_file] -- necessary on all but first build
BULK INSERT [legcrm1].[dbo].[voter_file] FROM 'statewide_voter.txt' 
WITH (FORMAT= 'CSV',DATA_SOURCE='testBlob',FIELDTERMINATOR = '|',FIELDQUOTE='')
-- field quote '' is critical as file may contain stray quotes; only '|' seems reliable