# Overview
This open source software (offered free under the MIT License) is designed and built to meet the unique needs of legislative offices.   It  automates the drudgery of replying to repetitive computer-generated incoming email campaigns.  It supports targeted outgoing bulk email based on constituent interests and geography (through an interface with Google Maps).  It includes basic constituent relationship management functions.  

To be cost-effective, this software needs to be installed at the level of a legislative branch (or a whole legislature).  It needs to be configured by a legislative information services department to run within the Azure/Office environment of the branch.  It is currently in use by many senators in the Massachusetts State Senate. The publisher is a Massachusetts state senator. The publisher can be reached at william.brownsberger@masenate.gov and is happy to informally demonstrate the product to legislators or technical personnel from other states who are considering adopting the product.  The publisher does not seek and will not accept any remuneration in connection with this product.

# Requirements
This software presumes that the legislative office is using Microsoft Office 365 and Outlook for email correspondence.
This software is built to run in the Microsoft Azure cloud. The major components to be configured in Azure are:
1. A PHP Web App -- the web interface is supported by php.  
2. An SQL Server -- the underlying data base is SQL Server.
3. Azure secret vaults for storing passwords, etc.
4. Several Web Jobs that run C# routines to interact with Microsoft Graph to read, parse, and send email messages.

Strongly desirable additional elements from outside Azure include:
* List of voters (could run without this and build through correspondence)
* Google Maps API key (to support geographic visualization and refinement of constituent search results)
* US Postal Service API Key (for address standardization and zip code assignment; the software only handles U.S. formatted addresses)
* GEOJSON files defining legislative districts and municipal boundaries
* A geocoding service -- this version of the software includes an interface with GEOCOD.IO
# Incorporated Open Source Software
This software relies on three open source software components, compatible versions of which are packaged in the /js directory.
1. JQuery/JQueryUI
2. TinyMCE
3. PLUpload

JQuery and JQueryUI are licensed under the same MIT license as this software.  See: https://jquery.org/license/
PLupload and TinyMCE are licensed under varations of GNU Version 2.  Their license texts are included in this distribution.


# Installation
The installation will depend somewhat on the environment, but the major steps are:
1. Configure a PHP Web App and an SQL Server in Azure.
* Configure the Web App to be accessible only for users authorized in Azure Active Directory; the App relies on Azure AAD services to identify authorized users
* Configure the SQL Server to be accessible only to the Web App and to the Web Jobs configured further below.
2. Install all of the contents of this repo, excepting the /LegCRMtoGraph subdirectory, in the root directory of the Azure Web App.
3. Edit legcrm-config.php to reflect the Azure Web App and Azure SQL server location and credentials.  
4. If they are available available, edit legcrm-config.php to add the legislative district geojson files (model them on the samples in the GeoJSON directory), the Google Maps key, and the U.S. Postal Service API key.  
5. Execute the scripts in the /sql subdirectory in the following order: 
* /structure/structures.sql
* /data/interface.sql
* all scripts in /type
* all scripts in /function
* all scripts in /procedure
6. If a voter file is available, 
* analyze how to map it into the voter_file table (see /sql/structure/structures.sql) 
* upload it to that table, using routines appropriate to your environment -- the best procedure may involve constructing a csv file with the same fields as the voter_file table and uploading it first to Azure storage and then bulk loading it from there -- see for example /sql/voterfile updates/recurring_voter_file_01.sql.  However, you might also upload the voter file to an sql table and then do the field mapping within SQL.
* critical link fields are registration_id which should be unique to each voter and state_senate_district/state_rep_district which should be unique codes for each district.
7. Set up a master office and user
* Manually insert a master office record in the office table  -- the only fields that need to be populated are office_email (arbitrary) and office_enabled (set to 1).
* Manually insert a master user record in the user table -- link the user to the master office by setting office_id to the ID value of the master office record; the user_email must be a valid email authorized to access the webapp through Azure Active Directory; user_enabled must be 1; user_max_capability should be 'super'.
8. Set up offices for each branch member.  This can now be done manually by the master user who will have access to the web app and can access the Office creation link at the top right of the screen.  Or, a table of offices can be uploaded manually.  Either way, the office_secretary_of_state_code for each member office  (SS code in the Office add interface) should correspond to the district code on the voter_file. The office_email should be the public facing email address for the member.
9. After the office table has been populated and a voter file has been loaded, the constituent and related tables for each office can be populated from the voter file.  Run the four scripts /sql/voterfile updates/recurring_voter_file_02a[b,c,d].sql.  Depending upon the branch, vary the line "WHERE v.state_senate_district = o.office_secretary_of_state_code" to reflect the appropriate branch.  The recurring voter file update scripts are designed to allow each office to maintain its own list of constituents but receive newly registered constituents periodically.
10. Add users for each office -- again, this can be done  through the Office interface or a table of users can be uploaded, but each user must belong to an Office.  All data within the application is segmented by office.  
11. Use the files in /LegCRMtoGraph to populate a repo in your Azure Devops and from there pipeline the C# code to setup the four key webjobs.  Each should set to run continuously in singleton mode -- they are all designed to loop and sleep efficiently.
* LegCRMtoGraph synchronizes the Outlook message graph to an image in the SQL database
* parseMessages does message preprocessing to support the email automation (and for Offices in which Outlook Categories is enabled) applies labels to messages in the Outlook inbox to identify constituent vs. non-constituent email as well as advocacy and bulk email.
* SendMailGraph sends messages originated in the app under the name of the originating office's email address
* Geocodio geocodes newly added addresses (only those not present in the address cache -- refreshing the voter list does not necessarily generate any new addresses).

