USE [legcrm1]
GO

SET ANSI_NULLS ON
GO

SET QUOTED_IDENTIFIER ON
GO

DROP PROCEDURE IF EXISTS [markNoLongerOnServer]
GO
-- INVOKED AFTER THE WHOLE GRAPH FOLDER HAS BEEN PAGED THROUGH AND
--		ALL MESSAGES THAT ARE IN THE IMAGES AND ALSO ON THE SERVER HAVE BEEN STAMPED WITH @now_sync_stamp
-- ANY MESSAGES NOT STAMPED BY CURRENT SYNC RUN ARE EITHER
	-- NO LONGER ON SERVER, SO HAVE AN EARLIER SYNC STAMP FROM PRIOR RUN
	-- NEWLY ADDED BY COMPETING PROCESS AND WILL HAVE LATER STAMP AND REMAIN UNTOUCHED
		-- SHOULD BE NO COMPETING PROCESS, BUT THIS IS BULLETPROOFING
CREATE PROCEDURE [dbo].[markNoLongerOnServer] 
  @OFFICE smallint, 	
  @office_email nvarchar(200),
  @now_sync_stamp bigint -- tracker for full sync case -- used to identify records existing in both server and image for full sync run
AS
BEGIN 
	UPDATE inbox_image 
	SET no_longer_in_server_folder = 1
	WHERE 
		OFFICE = @OFFICE AND
		office_email = @office_email AND 
		no_longer_in_server_folder = 0 AND
		now_stamp_of_last_sync < @now_sync_stamp - 10000
	-- subtracting 10000 to cover case where competing processes have caught up with each other (like bus bunching due to front process running slower)
	-- in the bus bunching case, they can both try to insert the same message as new, one succeeds and the other fails outright and does not stamp it; 
	--		they may go in either order as they race down the new message pageIterator, have seen variable number of insert failures when process competing
	-- run this step allowing for a delay so that both are right as they mark no longer on server (they will treat the other's found messages as their own); 
	-- the question of how much delay to allow depends on how much of a gap one believes one process could make up on the other, in other words,
	-- is 10000 ms delay enough? if process 1 starts at time T and process 2 starts at T plus delay is it possible that 2 could catch up to 1 and start competing for inserts?
	--		in current version, process 2 should have a disadvantage and never catch up because it is doing updates which are slower than inserts (.02ms vs. .005 ms in one test)
	--			catch up would only occur if graph responds more quickly to second process
	--			if had 10000 messages, that would be graph 1000 pages; if graph responded 10ms faster, 
	--				that would be a lot b/c most of the delay is internet time; selecting 10 msgs must be << 10ms in total and any diff probably < 1ms
	-- is 10000ms delay too much? No.  Unless processes are competing, an earlier full sync in normal loop would have to *start* (although not end) at least 120000 (2 min.) ago.  So, won't be accepting results of prior sync.
	--		if processes are competing, then by def, the delay is << 10000.

END
GO


