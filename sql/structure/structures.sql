
* * * COMMENT OUT THIS LINE IF YOU REALLY MEAN TO RUN THIS WHOLE PROC AND BLOW AWAY THE DATABASE * * * 
-- COLLATION SHOULD BE Latin1_General_100_CI_AS_SC_UTF8 SO THAT EXISTING UTF8 SPECIAL CHARACTERS DATA WILL BE HANDLED PROPERLY
DROP DATABASE IF EXISTS legcrm1;
CREATE DATABASE legcrm1 COLLATE Latin1_General_100_CI_AS_SC_UTF8;

-- USE OF NULL-- STRICTLY TYPED IN SQL SERVER:
--     NECESSARY IDENTITY OR LINK VALUES ARE NOT NULL NO DEFAULT SO FAILURE TO SUPPLY IS A READILY IDENTIFIABLE FATAL PROGRAMMING ERROR
--     DEFAULT EMPTY STRING OPTION VALUES -- EMPTY MEANS UNKNOWN OR AFFIRMATIVELY NOT SET
--     ALSO DEFAULT OTHER STRING VALUES TO EMPTY BECAUSE (A) USERS WILL LOOK FOR BLANK; (B) THAT WAS THE EFFECTIVE CONVENTION IN PRIOR VERSION; (C) BETTER LOOKING EXPORTS
--        EXCEPTION: READONLY STRING REGISTRATION FIELDS OTHER THAN ID; DEFAULT REG ID TO BLANK, SEARCH THAT WAY.  OTHERS MAY AS WELL BE NULL, NEVER CRITERION SEARCH
--        EXCEPTION: VALUES NOT HANDLED IN MAIN FORMS, E.G. FILE OR ATTACHMENT NAMES
--     DATES AND QUANTITIES ARE EITHER REQUIRED OR DEFAULTED TO AVOID INCONSISTENT HANDLING DUE TO PROGRAMMING ERROR -- DATES ARE ALL 1900-01-01; QUANTITIES ARE 0
--			Some dates may be app supplied on save,  in which case not null is just program integrity check
--     OFFICE AND UPDATED LOG FIELDS ARE NULLABLE BUT ENFORCED BY sqlsrv PROCS
--     NOTE: default for NULL|NOT NULL is NULL
USE legcrm1;


DROP TABLE IF EXISTS activity;
-- office specific; not necessarily constituent keyed in case of files
CREATE TABLE activity (
  OFFICE smallint NOT NULL, 	-- existence enforced by insert method of sqlsrv object
  ID int IDENTITY(1,1) PRIMARY KEY,
  constituent_id int DEFAULT 0, -- optional, can link only to issue
  activity_date datetime2(0) NOT NULL, -- required on insert
  activity_type varchar(30) DEFAULT '',
  activity_amount decimal(10,2) DEFAULT 0,
  issue int DEFAULT 0, -- optional, can link only to constituent
  pro_con varchar(1) DEFAULT '',
  activity_note varchar(max) DEFAULT '',
  file_name varchar(200),
  file_size bigint,
  file_content varbinary(max),
  email_batch_originated_constituent tinyint DEFAULT 0, -- used in undo of email
  related_inbox_image_record int DEFAULT 0,
  related_outbox_record int DEFAULT 0,
  upload_id int DEFAULT 0,
  conversion_id BIGINT DEFAULT 0,
  last_updated_time datetime2(0) NOT NULL,
  last_updated_by smallint NOT NULL
);
  CREATE INDEX ix_activity_constituent_id on activity (constituent_id);
  CREATE INDEX ix_activity_OFFICE_constituent_id on activity (OFFICE,constituent_id);
  CREATE INDEX ix_activity_OFFICE_activity_type on activity (OFFICE, activity_type);
  CREATE INDEX ix_activity_OFFICE_activity_date on activity (OFFICE, activity_date);
  CREATE INDEX ix_activity_OFFICE_file_name on activity (OFFICE, file_name);
  CREATE INDEX ix_activity_OFFICE_last_updated_time on activity (OFFICE, last_updated_time);
  CREATE INDEX ix_activity_OFFICE_last_updated_by on activity (OFFICE, last_updated_by);
  CREATE INDEX ix_activity_OFFICE_upload_id on activity (OFFICE, upload_id);
  CREATE INDEX ix_activity_OFFICE_related_inbox_image_record_activity_date on activity (OFFICE, related_inbox_image_record, activity_date);
  CREATE INDEX ix_activity_OFFICE_related_outbox_record_activity_date on activity (OFFICE, related_outbox_record, activity_date);
  CREATE INDEX ix_activity_issue_activity_date on activity (issue, activity_date ); -- issue is unique across all offices
  CREATE INDEX ix_activity_OFFICE_issue_activity_date on activity (office, issue, activity_date ); 
-- recommendation from azure
CREATE NONCLUSTERED INDEX [nci_wi_activity_date_with_include] ON [dbo].[activity] ([activity_date]) INCLUDE ([activity_type], [constituent_id], [issue], [pro_con])
-- FOLLOWING TWO LINES NOT TESTED -- USED WIZARD IN PRODUCTION
CREATE FULLTEXT CATALOG [activity_note_catalog] WITH ACCENT_SENSITIVITY = ON
CREATE FULLTEXT INDEX ON activity (activity_note) KEY INDEX PK__activity__3214EC279BB922E6 ON [activity_note_catalog] 
--
-- Table structure for table address
--
DROP TABLE IF EXISTS address;
-- office specific to accelerate selective searching
CREATE TABLE address (
  OFFICE smallint NOT NULL, 	
  ID int IDENTITY(1,1) PRIMARY KEY,
  constituent_id int NOT NULL, 
  address_type varchar(30) DEFAULT '',
  address_line varchar(100) DEFAULT '',
  left_address_line_5 as left(address_line,5) PERSISTED,
  city varchar(50) DEFAULT '',
  state varchar(50) DEFAULT '',
  zip varchar(10) DEFAULT '',
  left_zip_5 as left(zip,5) PERSISTED,
  last_updated_time datetime2(0) NOT NULL,
  last_updated_by int NOT NULL,
  lat decimal(10,8) DEFAULT 0, -- lat and lon implemented as "float_geo" control, always saves zero
  lon decimal(11,8) DEFAULT 0); -- this forces update of lat lon by batch process  on change
  CREATE INDEX ix_address_constituent_id on address (constituent_id);
  CREATE INDEX ix_address_OFFICE_constituent_id on address (OFFICE,constituent_id);
  CREATE INDEX ix_address_office_zip on address (OFFICE, zip);
  CREATE INDEX ix_address_zip on address (zip);
  CREATE INDEX ix_address_office_left_zip_5 on address(OFFICE, left_zip_5);
  CREATE INDEX ix_address_left_zip_5 on address(left_zip_5);
  CREATE INDEX ix_address_office_address_line on address (OFFICE, address_line);
  CREATE INDEX ix_address_address_line on address (address_line);
  CREATE INDEX ix_address_office_city on address (OFFICE, city);
  CREATE INDEX ix_address_city on address (city);
  CREATE INDEX ix_address_office_state on address (OFFICE, state);
  CREATE INDEX ix_address_state on address (state);
  CREATE INDEX ix_address_office_last_updated_time on address (OFFICE, last_updated_time);
  CREATE INDEX ix_address_office_last_updated_by on address (OFFICE, last_updated_by);
  CREATE INDEX ix_address_office_full_address on address (OFFICE, address_line, city,state,zip);
  CREATE INDEX ix_address_full_address on address (address_line, city,state,zip,lat);
  CREATE INDEX ix_address_OFFICE_left_address_line_5 on address (OFFICE, left_address_line_5); 
  CREATE INDEX ix_address_lat on address (lat);
  CREATE INDEX ix_address_lon on address (lon);
  -- recommendations from azure
  CREATE NONCLUSTERED INDEX [ix_address_lat_lon_address_line_plus_leaf] ON [dbo].[address] ([lat], [lon], [address_line]) INCLUDE ([city], [constituent_id], [state], [zip]);
--
-- Table structure for table address_geocode_cache
--
DROP TABLE IF EXISTS address_geocode_cache;
-- always centralized, never user accessed
CREATE TABLE address_geocode_cache (
  ID int IDENTITY(1,1) PRIMARY KEY,
  address_raw varchar(100),
  city_raw varchar(50),
  state_raw varchar(50),
  zip_raw varchar(10),
  --address_clean varchar(100) DEFAULT '', not using returned formatted address; 
  --hard to parse out address line from returned full formatted address and returned formatted street does not include apt
  lat decimal(10,8) DEFAULT 0,
  lon decimal(11,8) DEFAULT 0,
  zip_clean varchar(10) DEFAULT '',
  matched_clean_address varchar(200) default '',
  geocode_vendor varchar(30) DEFAULT '',
  geocode_vendor_accuracy decimal(3,2) DEFAULT 0, -- geocodio accuracy ranges from 0.0 to 1.0
  geocode_vendor_accuracy_type varchar(100) default '', -- e.g. "rooftop"
  geocode_vendor_source varchar(100) default '', -- e.g. "MassGIS . .. ",
  geocode_computed_date datetime2(0) default '1900-01-01'
 );
  CREATE INDEX ix_address_geocode_cache_zip_raw ON address_geocode_cache (zip_raw);
  CREATE INDEX ix_address_geocode_cache_address_raw ON address_geocode_cache (address_raw);
  CREATE INDEX ix_address_geocode_cache_city ON address_geocode_cache (city_raw);
  CREATE INDEX ix_address_geocode_cache_state ON address_geocode_cache (state_raw);
  CREATE INDEX ix_address_geocode_cache_full_address ON address_geocode_cache (address_raw,city_raw,state_raw,zip_raw);
  CREATE INDEX ix_address_geocode_cache_lat ON address_geocode_cache (lat);
  CREATE INDEX ix_address_geocode_cache_lon ON address_geocode_cache (lon);
-- azure performance recognition
  CREATE NONCLUSTERED INDEX [nci_wi_address_geocode_cache_lat_lon_address_raw_plus_leaf] ON [dbo].[address_geocode_cache] ([lat], [lon], [address_raw]) INCLUDE ([city_raw], [state_raw], [zip_clean], [zip_raw]);

--
-- Table structure for table constituent
--
DROP TABLE IF EXISTS constituent;
-- office specific
CREATE TABLE constituent (
  OFFICE smallint NOT NULL, 	
  ID int IDENTITY(1,1) PRIMARY KEY,
  last_name varchar(50) DEFAULT '',
  first_name varchar(50) DEFAULT '',
  middle_name varchar(50) DEFAULT '',
  fi_ln as concat(left(first_name,1),last_name) PERSISTED, -- computed column for indexing for fi/ln soft match
  date_of_birth datetime2(0) DEFAULT '1900-01-01',
  year_of_birth varchar(4) DEFAULT '', -- updated only off line on load from clerk's offices who don't supply dob
  is_deceased tinyint DEFAULT 0,
  consented_to_email_list varchar(1) DEFAULT '',
  is_my_constituent varchar(1) DEFAULT '',
  case_assigned bigint DEFAULT 0,
  case_review_date datetime2(0) DEFAULT '1900-01-01',
  case_status varchar(1) DEFAULT '',
  gender varchar(1) DEFAULT '',
  occupation varchar(100) DEFAULT '',
  employer varchar(100) DEFAULT '',
  registration_id varchar(50) DEFAULT '',
  registration_date datetime2(0) DEFAULT '1900-01-01',
  registration_status varchar(1),
  party varchar(5),
  ward varchar(5),
  precinct varchar(5),
  council_district varchar(50),
  state_rep_district varchar(50),
  state_senate_district varchar(50),
  congressional_district varchar(50),
  councilor_district varchar(50),
  county varchar(50),
  other_district_1 varchar(50),
  other_district_2 varchar(50),
  last_updated_time datetime2(0) NOT NULL,
  last_updated_by int NOT NULL,
  salutation varchar(50) DEFAULT '',
  other_system_id  varchar(50) DEFAULT '',
  conversion_id bigint default 0,
  voter_file_refresh_date datetime2 DEFAULT '1900-01-01');
  CREATE INDEX ix_constituent_OFFICE_last_name ON constituent (OFFICE, last_name);
  CREATE INDEX ix_constituent_last_name ON constituent (last_name);
  CREATE INDEX ix_constituent_OFFICE_middle_name ON constituent (OFFICE, middle_name);
  CREATE INDEX ix_constituent_OFFICE_dob ON constituent (OFFICE, date_of_birth);
  CREATE INDEX ix_constituent_OFFICE_gender ON constituent (OFFICE, gender);
  CREATE INDEX ix_constituent_OFFICE_first_name ON constituent (OFFICE, first_name);
  CREATE INDEX ix_constituent_first_name ON constituent (first_name);
  CREATE INDEX ix_constituent_OFFICE_is_deceased ON constituent (OFFICE, is_deceased);
  CREATE INDEX ix_constituent_OFFICE_assigned ON constituent (OFFICE, case_assigned);
  CREATE INDEX ix_constituent_OFFICE_case_review_date ON constituent (OFFICE, case_review_date);
  CREATE INDEX ix_constituent_OFFICE_case_status ON constituent (OFFICE, case_status);
  CREATE INDEX ix_constituent_OFFICE_fnln ON constituent (OFFICE, last_name,first_name);
  CREATE INDEX ix_constituent_OFFICE_fi_ln ON constituent (OFFICE, fi_ln);
  CREATE INDEX ix_constituent_OFFICE_last_updated_time ON constituent (OFFICE, last_updated_time);
  CREATE INDEX ix_constituent_OFFICE_last_updated_by ON constituent (OFFICE, last_updated_by);
  CREATE INDEX ix_constituent_OFFICE_registration_id ON constituent (OFFICE, registration_id);
  CREATE INDEX ix_constituent_OFFICE_conversion_id ON constituent (OFFICE, conversion_id);
  CREATE INDEX ix_constituent_OFFICE_voter_file_refresh_date ON constituent (OFFICE, voter_file_refresh_date);
  CREATE INDEX ix_constituent_OFFICE_consented on constituent (OFFICE,consented_to_email_list);
--
-- Table structure for table core_settings
--

DROP TABLE IF EXISTS core_settings; -- this table is transitional;  all of these could be standardized eliminated in final consolidation
-- office specific for now
CREATE TABLE core_settings (
  OFFICE smallint NOT NULL, 
  setting_group varchar(50) DEFAULT '',
  setting_name varchar(50) NOT NULL,
  setting_value varchar(max) NOT NULL);
  CREATE UNIQUE INDEX ix_OFFICE_setting_name on core_settings (OFFICE, setting_name);
  CREATE INDEX ix_OFFICE_setting_group on core_settings (OFFICE, setting_group);


--
-- Table structure for table email
--
DROP TABLE IF EXISTS email;
-- office specific, but constituent keyed
CREATE TABLE email (
  OFFICE smallint NOT NULL, 	
  ID int IDENTITY(1,1) PRIMARY KEY,
  constituent_id int NOT NULL, 
  email_type varchar(30) DEFAULT '',
  email_address varchar(200) DEFAULT '',
  last_updated_time datetime2(0) NOT NULL,
  last_updated_by int NOT NULL);
  CREATE INDEX ix_email_constituent_id on email (constituent_id);
  CREATE INDEX ix_email_office_constituent_id on email (office, constituent_id);
  CREATE INDEX ix_email_email_address ON email (email_address);
  CREATE INDEX ix_email_office_email_address ON email (OFFICE, email_address);
  CREATE INDEX ix_email_office_email_type ON email (OFFICE, email_type);
  CREATE INDEX ix_email_office_last_updated_time ON email (OFFICE, last_updated_time);
  CREATE INDEX ix_email_office_last_updated_by ON email (OFFICE, last_updated_by);

--
-- Table structure for table inbox_image
--
DROP TABLE IF EXISTS inbox_image;
-- Office specific
CREATE TABLE inbox_image (
  OFFICE smallint NOT NULL, 	
  ID bigint IDENTITY(1,1) PRIMARY KEY,
  office_email varchar(200) NOT NULL,
  no_longer_in_server_folder tinyint DEFAULT 0 ,
  folder_uid bigint NOT NULL,
  to_be_moved_on_server tinyint DEFAULT 0,
  utc_time_stamp float(53) DEFAULT 0,
  from_personal varchar(200) DEFAULT '',
  from_email varchar(200) DEFAULT '',
  from_domain varchar(200) DEFAULT '',
  subject varchar(400) DEFAULT '',
  activity_date datetime2(0) DEFAULT '1900-01-01',
  email_date_time datetime2(0) DEFAULT '1900-01-01',
  message_json varchar(max) NOT NULL,
    -- json https://docs.microsoft.com/en-us/graph/api/resources/message?view=graph-rest-1.0
  parsed_message_json varchar(max) DEFAULT '',
  has_attachments bit default 0,
  now_stamp_of_last_sync bigint DEFAULT 0,
  mapped_issue bigint DEFAULT 0,
  mapped_pro_con varchar(1) DEFAULT '',
  is_my_constituent_guess varchar(1) DEFAULT '',
  assigned_constituent bigint DEFAULT 0,
  account_thread_id varchar(255) DEFAULT '',
  snippet varchar(1000) DEFAULT '',
  category varchar(200) DEFAULT '',
  extended_message_id varchar(512) COLLATE SQL_Latin1_General_CP1_CS_AS DEFAULT '', -- message id in office 365 is case SENSITIVE
    -- message id never reused in folder https://stackoverflow.com/questions/43568178/message-ids-are-known-to-change-on-move-copy-etc-but-are-they-ever-repeated
  inbox_defined_staff bigint DEFAULT 0,
  inbox_defined_issue bigint DEFAULT 0,
  inbox_defined_pro_con varchar(1) DEFAULT '',
  inbox_defined_reply_text varchar(max) DEFAULT '',
  inbox_defined_reply_is_final tinyint DEFAULT 0);
  CREATE INDEX ix_inbox_image_OFFICE_in_folder_no_longer ON inbox_image (OFFICE, no_longer_in_server_folder);
  CREATE INDEX ix_inbox_image_OFFICE_uid ON inbox_image (OFFICE,folder_uid);
  CREATE INDEX ix_inbox_image_OFFICE_pending_subject ON inbox_image (OFFICE,subject,no_longer_in_server_folder,to_be_moved_on_server);
  CREATE INDEX ix_inbox_image_OFFICE_ready_to_move ON inbox_image (OFFICE,no_longer_in_server_folder,to_be_moved_on_server);
  CREATE INDEX ix_inbox_image_OFFICE_from_email ON inbox_image (OFFICE,from_email);
  CREATE INDEX ix_inbox_image_OFFICE_account_thread_id ON inbox_image (OFFICE,account_thread_id);
  CREATE INDEX ix_inbox_image_OFFICE_extended_message_id ON inbox_image (OFFICE,extended_message_id);
  CREATE INDEX ix_inbox_image_OFFICE_from_domain on inbox_image (OFFICE,from_domain);
  CREATE INDEX ix_inbox_image_OFFICE_staff_subject ON inbox_image (OFFICE,inbox_defined_staff,subject);
  CREATE INDEX ix_inbox_image_OFFICE_staff_final_subject ON inbox_image (OFFICE,inbox_defined_staff,inbox_defined_reply_is_final,subject);
  CREATE INDEX ix_inbox_image_OFFICE_has_attachments_in_inbox ON inbox_image (OFFICE,no_longer_in_server_folder,to_be_moved_on_server,has_attachments);
  CREATE INDEX ix_inbox_image_OFFICE_now_stamp_of_last_sync_on_server ON inbox_image (OFFICE,no_longer_in_server_folder,now_stamp_of_last_sync);
  CREATE INDEX ix_inbox_image_for_get_next_to_parse ON inbox_image(folder_uid, no_longer_in_server_folder, to_be_moved_on_server, ID );
  CREATE INDEX ix_inbox_image_for_inbox_load ON inbox_image (			OFFICE, no_longer_in_server_folder,	to_be_moved_on_server, folder_uid, subject_is_final, inbox_defined_reply_is_final, mapped_issue, assigned_subject, email_date_time);
  CREATE UNIQUE INDEX ix_inbox_image_graph_office_email_message_id ON inbox_image (office_email,extended_message_id);
  -- azure performance recommendation
  CREATE NONCLUSTERED INDEX [nci_wi_inbox_image_OFFICE_office_email_plus_leaf] ON [dbo].[inbox_image] ([OFFICE], [office_email]) INCLUDE ([extended_message_id]);

--
-- Table structure for table inbox_image_attachments
--
DROP TABLE IF EXISTS inbox_image_attachments;
-- not office specific but keyed by message ID
CREATE TABLE inbox_image_attachments (
  ID bigint IDENTITY(1,1) PRIMARY KEY,
  attachment_type varchar(200),
  attachment_size int,
  attachment varchar(max)); -- base 64 encoded
  CREATE INDEX ix_inbox_image_attachments_ID on inbox_image_attachments (ID);
--
-- Table structure for table inbox_image_attachments_xref
--
DROP TABLE IF EXISTS inbox_image_attachments_xref;
-- not office specific, but keyed by message ID
CREATE TABLE inbox_image_attachments_xref (
  ID bigint IDENTITY(1,1) PRIMARY KEY,
  attachment_id bigint,
  attachment_md5 binary(16),
  message_in_outbox tinyint,
  message_id bigint,
  message_attachment_cid varchar(1000),
  message_attachment_filename varchar(1000),
  message_attachment_disposition varchar(50));
  CREATE INDEX ix_inbox_image_attachments_xref_attachment_md ON  inbox_image_attachments_xref (attachment_md5);
  CREATE INDEX ix_inbox_image_attachments_xref_attachment_id ON inbox_image_attachments_xref (attachment_id,message_in_outbox,message_id);
  CREATE INDEX ix_inbox_image_attachments_xref_message_id_only ON inbox_image_attachments_xref (message_id);
  CREATE INDEX ix_inbox_image_attachments_xref_message_id_plus ON inbox_image_attachments_xref(message_in_outbox,message_id,attachment_id);
  CREATE INDEX ix_inbox_image_attachments_xref_message_attachment_filename ON inbox_image_attachments_xref (message_attachment_filename);

--
-- Table structure for table inbox_incoming_filter
--
DROP TABLE IF EXISTS inbox_incoming_filter;
-- always office specific
CREATE TABLE inbox_incoming_filter (
  OFFICE smallint NOT NULL, 	
  ID int IDENTITY(1,1) PRIMARY KEY,
  from_email_box varchar(100),
  from_email_domain varchar(200),
  subject_first_filtered varchar(500),
  block_whole_domain tinyint DEFAULT 0,
  filtered_since datetime2(0));
  CREATE INDEX ix_inbox_incoming_filter_email_domain_box on inbox_incoming_filter (OFFICE, from_email_domain,from_email_box);


--
-- Table structure for table interface
--

DROP TABLE IF EXISTS interface;
 -- offices may want different mappings, but can benefit from shared experience more
CREATE TABLE interface (
  upload_field_name varchar(255) PRIMARY KEY,
  matched_entity varchar(255),
  matched_field varchar(255),
);

--
-- Table structure for table issue
--
DROP TABLE IF EXISTS issue;
-- office controlled
CREATE TABLE issue (
  OFFICE smallint NOT NULL, 	
  ID int IDENTITY(1,1) PRIMARY KEY,
  post_title varchar(400) DEFAULT '',
  post_content varchar(max) DEFAULT '',
  post_type varchar(30) DEFAULT 'post',
  post_category varchar(50),
  follow_up_status varchar(10) DEFAULT '',
  issue_staff bigint DEFAULT 0,
  review_date datetime2(0) DEFAULT '1900-01-01',
  wic_live_issue varchar(10) DEFAULT '',
  serialized_shape_array varchar(max) NULL,
  -- links to reply procon(''), reply pro (0) and reply con(1)
  reply bigint DEFAULT 0,
  reply0 bigint DEFAULT 0,
  reply1 bigint DEFAULT 0,
  --
  conversion_id bigint DEFAULT 0,
  last_updated_time datetime2(0) NOT NULL,
  last_updated_by int NOT NULL);
  CREATE INDEX ix_issue_OFFICE_post_title on issue (OFFICE, post_title);
  CREATE INDEX ix_issue_OFFICE_issue_staff on issue (OFFICE, issue_staff);
  CREATE INDEX ix_issue_OFFICE_live_issue_post_title on issue (OFFICE, wic_live_issue, post_title);
  CREATE INDEX ix_issue_OFFICE_post_category on issue (OFFICE, post_category);
  CREATE INDEX ix_issue_OFFICE_conversion_id on issue (OFFICE, conversion_id);
  CREATE INDEX ix_issue_OFFICE_last_updated_time on issue (OFFICE, last_updated_time);
  CREATE INDEX ix_issue_OFFICE_last_updated_by on issue (OFFICE, last_updated_by);
-- azure performance recommendation
CREATE NONCLUSTERED INDEX [nci_wi_issue_OFFICE_wic_live_issue_post_type_plus] ON [dbo].[issue] ([OFFICE], [wic_live_issue], [post_type]) INCLUDE ([issue_staff], [post_title], [reply], [reply0], [reply1], [review_date]);
CREATE FULLTEXT CATALOG [post_content_catalog] WITH ACCENT_SENSITIVITY = ON
-- following line needs to be run with primary key identifier specified (point to ID primary key)
--CREATE FULLTEXT INDEX ON issue (post_title,post_content) KEY INDEX [PK__issue__3214EC276F5EA4D3] ON [post_content_catalog]


--
-- Table structure for table office 
--
DROP TABLE IF EXISTS office;
CREATE TABLE office( 
  ID smallint IDENTITY(1,1) PRIMARY KEY,
  office_email varchar(200) NOT NULL,
  office_enabled bit NOT NULL,
  office_outlook_categories_enabled bit default 0,
  office_label varchar(50) NOT NULL,
  office_last_delta_token varchar (4000) default '', -- observed length is 790
  office_last_delta_token_refresh_time datetime2,
  office_secretary_of_state_code varchar(5) default '',
  office_throttled_until bigint default 0,
  office_send_mail_held bit default 0,
  office_type varchar(50) NOT NULL,
  OFFICE smallint, -- this field is populated with the office of the superuser creating the office; not used in processing
  last_updated_time datetime2(0) NOT NULL,
  last_updated_by int NOT NULL
);
CREATE INDEX ix_office_enabled_ID on office ( office_enabled, ID );
create index ix_office_secretary_of_state_code on office (office_secretary_of_state_code);
--
-- Table structure for table outbox
--
DROP TABLE IF EXISTS outbox;
-- offices own their view, but processor does all
CREATE TABLE outbox (
  OFFICE smallint NOT NULL, 	
  ID bigint IDENTITY(1,1) PRIMARY KEY,
  sent_time_stamp bigint,
  sent_ok tinyint DEFAULT 0,
  held tinyint DEFAULT 0,
  is_draft tinyint DEFAULT 0,
  queued_date_time datetime2(0),
  sent_date_time datetime2(0),
  is_reply_to bigint DEFAULT 0,
  subject varchar(400) DEFAULT '',
  serialized_email_object varchar(max) NOT NULL,
  json_email_object varchar(max) NOT NULL,
  to_address_concat varchar(max));
  CREATE INDEX ix_outbox_sent_ok_queued_date_time ON outbox (sent_ok,queued_date_time); -- index without office for processing
  CREATE INDEX ix_outbox_OFFICE_sent_ok_queued_date_time ON outbox (OFFICE, sent_ok,queued_date_time); -- index with office for outbox display
  CREATE INDEX ix_outbox_OFFICE_sent_date_time ON outbox (OFFICE, sent_date_time); -- for sent display
  CREATE INDEX ix_outbox_OFFICE_subject ON outbox (OFFICE, subject);
  CREATE INDEX ix_outbox_is_reply_to ON outbox (is_reply_to); -- office limited but keyed by message
  CREATE INDEX ix_outbox_OFFICE_sent_time_stamp ON outbox (OFFICE, sent_time_stamp);
  CREATE INDEX ix_outbox_sent_time_stamp_OFFICE ON outbox (sent_time_stamp, OFFICE);
  CREATE INDEX ix_outbox_held_is_draft_sent_ok_sent_time_stamp ON outbox([held], [is_draft], [sent_ok], [sent_time_stamp]);
  CREATE INDEX ix_outbox_OFFICE_sent_ok_is_draft_sent_date_time ON outbox(OFFICE, sent_ok, is_draft, sent_date_time );

-- Table structure for table mail_error_log
--
DROP TABLE IF EXISTS mail_error_log;
CREATE TABLE mail_error_log (
  event_type varchar(10),
  error_code varchar(100),
  OFFICE smallint NOT NULL, 	
  ID bigint IDENTITY(1,1) PRIMARY KEY,
  outbox_message_id bigint default 0,
  event_attempt_time_stamp bigint,
  event_date_time datetime2(0),
  message_subject varchar(100) DEFAULT '',
  client_request_guid varchar(100)
)

-- Table structure for table phone
--
DROP TABLE IF EXISTS phone;
-- phone table is user specific, office tracked for search performance
CREATE TABLE phone (
  OFFICE smallint NOT NULL, 	
  ID int IDENTITY(1,1) PRIMARY KEY,
  constituent_id int NOT NULL, 
  phone_type varchar(30) DEFAULT '',
  phone_number bigint DEFAULT 0,
  extension varchar(10) DEFAULT '',
  last_updated_time datetime2(0) NOT NULL,
  last_updated_by int NOT NULL)
  CREATE INDEX ix_phone_constituent_id ON phone (constituent_id);
  CREATE INDEX ix_phone_office_constituent_id ON phone (office,constituent_id);
  CREATE INDEX ix_phone_phone_number ON phone (phone_number);
  CREATE INDEX ix_phone_office_phone_number ON phone (OFFICE, phone_number);
  CREATE INDEX ix_phone_last_updated_time ON phone (last_updated_time);
  CREATE INDEX ix_phone_last_updated_by ON phone (last_updated_by);

--
-- Table structure for table search_log
--
DROP TABLE IF EXISTS search_log;
-- search log is user specific
CREATE TABLE search_log (
  OFFICE smallint NOT NULL, 	
  ID int IDENTITY(1,1) PRIMARY KEY,
  favorite tinyint default 0,
  user_id int NOT NULL,
  search_time datetime2 NOT NULL,
  share_name varchar(50) DEFAULT '',
  is_named tinyint default 0,
  entity varchar(30) NOT NULL,
  serialized_search_array varchar(max) NOT NULL,
  download_time datetime2,
  serialized_search_parameters varchar(max) NOT NULL,
  result_count bigint,
  serialized_shape_array varchar(max) NULL);
  CREATE INDEX ix_search_log_OFFICE_named_name_user_favorite_time  ON search_log (OFFICE,is_named,share_name,user_id,favorite,search_time);


--
-- Table structure for table subject_issue_map
--
DROP TABLE IF EXISTS subject_issue_map;
-- subject issue map is user specific
CREATE TABLE subject_issue_map (
  OFFICE smallint NOT NULL, 	
  ID int IDENTITY(1,1) PRIMARY KEY,
  incoming_email_subject varchar(400) NOT NULL,
  email_batch_time_stamp datetime2(0) NOT NULL,
  mapped_issue bigint NOT NULL,
  mapped_pro_con varchar(20) DEFAULT '');
  CREATE INDEX subject_issue_map_OFFICE_subject_stamp ON subject_issue_map (incoming_email_subject,email_batch_time_stamp);
  CREATE INDEX subject_issue_map_OFFICE_stamp_subject ON subject_issue_map (OFFICE, email_batch_time_stamp,incoming_email_subject);

--
-- Table structure for table uid_reservation
--
DROP TABLE IF EXISTS uid_reservation;
-- UID reservation is user segmented, but this may go away as a need
CREATE TABLE uid_reservation (
  OFFICE smallint NOT NULL, 	
  ID int IDENTITY(1,1),
  uid bigint NOT NULL,
  time_stamp datetime2(0),
  reservation_time decimal(20,6),
  batch_subject varchar(255),
  CONSTRAINT PK_uid_reservation_OFFICE_uid PRIMARY KEY (OFFICE, uid));


--
-- Table structure for table upload
--
DROP TABLE IF EXISTS upload;
-- Current list of upoads is user segmented
CREATE TABLE upload (
  OFFICE smallint NOT NULL, 	
  ID int IDENTITY(1,1) PRIMARY KEY,
  upload_time datetime2(0),
  upload_by bigint,
  upload_chunks int,
  upload_description varchar(255),
  upload_file varchar(255),
  upload_status varchar(255),
  serialized_upload_parameters varchar(max),
  serialized_column_map varchar(max),
  serialized_match_results varchar(max),
  serialized_default_decisions varchar(max),
  serialized_final_results varchar(max),
  last_updated_time datetime2(0) NOT NULL,
  last_updated_by int NOT NULL,
  purged tinyint);
  CREATE INDEX ix_upload_OFFICE_time_by on upload (OFFICE,upload_time,upload_by);


--
-- Table structure for table upload_temp
--
DROP TABLE IF EXISTS upload_temp;
-- this table is central (facilitates regular purge); ID is always unique
CREATE TABLE upload_temp (
  ID int IDENTITY(1,1) PRIMARY KEY,
  upload_id int,
  chunk_id int,
  chunk varchar(max));
  CREATE INDEX ix_upload_temp_upload_chunk on upload_temp (upload_id,chunk_id);

--
-- Table structure for table users
--
DROP TABLE IF EXISTS [user];
-- This table is central
CREATE TABLE [user] (
  ID int IDENTITY(1,1) PRIMARY KEY,
  office_id smallint NOT NULL,
  user_enabled tinyint default 1,
  user_email varchar(200) NOT NULL,
  user_name varchar(100) DEFAULT '',
  user_max_capability varchar(10) NOT NULL,
  user_preferences varchar(max) DEFAULT '',
  last_updated_time datetime2(0) NOT NULL,
  last_updated_by int NOT NULL,
  );
  CREATE UNIQUE INDEX ix_user_user_email ON [user] (user_email);
  CREATE INDEX ix_user_office_office_id ON [user] (office_id);
--
-- Table structure for voter file --  THIS STRUCTURE MUST BE MAINTAINED AS FIXED EVEN HE CHANGES IT -- ACCESSED DIRECTLY IN CODE
-- load from secretary of state with this command, note that field widths have to be larger than specified
-- BULK INSERT [legcrm1].[dbo].voter_file FROM 'd:\voter_file\statewide_voter.txt' [replace with file path]
-- WITH (FORMAT= 'CSV',FIELDTERMINATOR = '|',FIELDQUOTE='') (there will be stray quotes in the file, so set no FIELDQUOTE)
DROP TABLE IF EXISTS [voter_file]
-- This table is central
CREATE TABLE [voter_file](
row_number int,
city_town_code smallint,
city_town_name varchar(25),
county_name varchar(10),
voter_id varchar(15),
last_name varchar(25),
first_name varchar(15),
middle_name varchar(15),
title varchar(3),
residential_address_street_no int,
residential_address_street_no_suffix varchar(5),
residential_address_street_name varchar(25),
residential_address_street_apt_no varchar(6),
residential_address_zip_code varchar(11),
mailing_address_street_addresss varchar(75),
mailing_address_apt_no varchar(20),
mailing_address_city_town varchar(50),
mailing_address_state varchar(2),
mailing_address_zip_code varchar(11),
party varchar(2),
gender varchar(1),
date_of_birth datetime2,
registration_date datetime2,
ward varchar(5),
precinct varchar(5),
congressional_district varchar(5),
state_senate_district varchar(5),
state_rep_district varchar(5),
voter_status varchar(1)
)
CREATE INDEX ix_zip_state_senate_district ON voter_file (residential_address_zip_code, state_senate_district);
CREATE INDEX ix_zip_state_rep_district ON voter_file (residential_address_zip_code, state_rep_district);
CREATE INDEX ix_last_name ON voter_file (last_name);
CREATE INDEX ix_first_name ON voter_file (first_name);
create index ix_voter_file_voter_id on voter_file (voter_id);