USE [legcrm1]
GO
DROP TYPE IF EXISTS dbo.autocompleteTable
/****** Object:  UserDefinedTableType [dbo].[autocompleteTable]    Script Date: 9/24/2020 6:00:23 AM ******/
CREATE TYPE [dbo].[autocompleteTable] AS TABLE(
	[label] [varchar](600) NULL,
	[value] [varchar](200) NULL,
	[entity_type] [varchar](20) NULL,
	[email_name] [varchar](200) NULL,
	[latest_email_address] [varchar](200) NULL
)
GO


