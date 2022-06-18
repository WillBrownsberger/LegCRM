USE [legcrm1]
GO
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
DROP PROCEDURE IF EXISTS [saveNewMessageAttachment]
GO
-- creates two temp tables for subsequent processing: 
	-- messages found on server that have been marked on db to_be_moved_on_server (but for aynch issues, these could be handled in this proc)
	-- messages found on server (all), to be compared to current inbox list to identify those to markNoLongerOnServer
CREATE PROCEDURE [dbo].[saveNewMessageAttachment] 
  @OFFICE smallint, 	
  @message_id bigint, -- id on inbox_image db file, not id on Graph
  @message_in_outbox bit, -- not used in main flow, but allows possible use of this proc for outbox messages
  @attachment varchar(max)
AS
BEGIN 
	DECLARE 
		-- receivers for JSON values
		@contentBytes varchar(max),
		@contentType varchar(200),
		@size bigint,
		@contentID varchar(1000),
		@name varchar(1000),
		@isInline varchar(6),
		-- additional fields
		@attachment_existing_copy bigint = 0,
		@attachment_hash binary(16);

	SELECT 
		@contentBytes = contentBytes,
		@contentType =contentType,
		@size		=size,
		@contentID	=contentId,
		@name		= [name],
		@isInline	=isInline
	FROM OPENJSON ( @attachment )  
	WITH (   
		contentBytes	varchar(max)   '$.contentBytes',  
        contentType     varchar(200)   '$.contentType',  
        size			int				'$.size',  
        contentId		varchar(1000)  '$.contentId', 
		[name]			varchar(1000)  '$.name',
		isInline		varchar(6)	   '$.isInline'
	)
	-- contentID is null for plain attachments; set to blank
	if (@contentID IS NULL) SET @contentID = '';

	-- does this attachment already exist on the attachment table
	SET @attachment_hash = HASHBYTES('MD5',@contentBytes);
	SELECT TOP 1 @attachment_existing_copy = attachment_id 
	FROM inbox_image_attachments_xref 
	WHERE attachment_md5 = @attachment_hash;
	
	-- if not, save it
	IF ( 0 = @attachment_existing_copy ) 
		BEGIN
			INSERT INTO inbox_image_attachments 
				(
				attachment_type,
				attachment_size,
				attachment 
				) 
			VALUES
				(
				@contentType,
				@size,
				@contentBytes -- base64 encoded, so character string
				);
			SELECT @attachment_existing_copy = CAST(scope_identity() AS int)
		END
		
	-- now save xref record (
		INSERT INTO inbox_image_attachments_xref  
			( 
				attachment_id,
				attachment_md5,
				message_in_outbox,
				message_id,
				message_attachment_cid,
				message_attachment_filename,
				message_attachment_disposition 
			) VALUES (
				@attachment_existing_copy,
				@attachment_hash,
				@message_in_outbox,
				@message_id, -- bigint insert id on inbox_image
				@contentID,
				@name, -- not guaranteed to be filename, but will likely save as file name; no other filename candidate on the object; works in ActiveSync
				IIF( @isInline != 'false', 'inline' , 'attachment' )
			)

END

GO








