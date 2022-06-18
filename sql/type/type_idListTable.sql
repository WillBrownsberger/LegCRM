USE [legcrm1]
GO

/****** Object:  UserDefinedTableType [dbo].[idListTable]    Script Date: 10/1/2020 7:21:13 AM ******/
DROP TYPE IF EXISTS [dbo].[idListTable]
GO

/****** Object:  UserDefinedTableType [dbo].[idListTable]    Script Date: 10/1/2020 7:21:13 AM ******/
CREATE TYPE [dbo].[idListTable] AS TABLE(
	[id] [bigint] NULL
)
GO

