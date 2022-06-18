-- ================================
-- Create User-defined Table Type
-- ================================
USE legcrm1
GO
DROP TYPE IF EXISTS termSplitArray
GO
-- Create the data type
CREATE TYPE termSplitArray AS TABLE 
(
	term_order int,
	value varchar(600)
)
GO
