using System;
using System.Collections.Generic;
using System.Globalization;
using System.Text;
using Microsoft.Graph;
using System.Data.SqlClient;
using System.Text.RegularExpressions;
using System.Text.Json;



namespace parseMessages.ParsedMessageObjects
{
    class CRMParsedMessageObject
    {
		public CRMParsedMessageObject(SqlConnection connection, UnparsedMessage unparsedMessage )
        {
			OFFICE = unparsedMessage.OFFICE;
			officeEmail = unparsedMessage.OfficeEmail;
			inbox_image_id = unparsedMessage.ID;

			// deserialize messageJson as object that has all properties of both CRM inbox message object and graph message object
			try
			{
				OriginalMessage = JsonSerializer.Deserialize<ParsedMessageObject>(unparsedMessage.MessageJson);
				successfulParse = true;
			}
			catch (Exception e)
			{
				Console.WriteLine("Failed Parse at CRMParsedMessageObject construct: " + e.Message);
				Console.WriteLine("Inbox Message ID for failed parse: " + inbox_image_id.ToString());
				successfulParse = false;
				return;

			}
			
			// fill in known CRM inbox message object properties
			MoveGraphToLegacy( connection );
		}

		public readonly short OFFICE;
		public readonly string officeEmail;
		public readonly bool successfulParse;

		public ParsedMessageObject OriginalMessage { get; set; }

		/*
		 *  The following properties of this object match the public properties of the legacy PHP object:
		 *		WIC_DB_Email_Message_Object
		 *  
		 *  When loaded in PHP from JSON, this object has all the necessary properties of that object.
		 *  
		 *  Must be initialized as non-null because may not be set before being used in queries
		 */
		[System.Diagnostics.CodeAnalysis.SuppressMessage("Style", "IDE1006:Naming Styles", Justification = "<Consistency>")]
		public string email_address { get; set; } = "";

		[System.Diagnostics.CodeAnalysis.SuppressMessage("Style", "IDE1006:Naming Styles", Justification = "<Consistency>")]
		public long phone_number { get; set; } = 0;

		[System.Diagnostics.CodeAnalysis.SuppressMessage("Style", "IDE1006:Naming Styles", Justification = "<Consistency>")]
		public string first_name { get; set; } = "";

		[System.Diagnostics.CodeAnalysis.SuppressMessage("Style", "IDE1006:Naming Styles", Justification = "<Consistency>")]
		public string middle_name { get; set; } = "";

		[System.Diagnostics.CodeAnalysis.SuppressMessage("Style", "IDE1006:Naming Styles", Justification = "<Consistency>")]
		public string last_name { get; set; } = "";

		[System.Diagnostics.CodeAnalysis.SuppressMessage("Style", "IDE1006:Naming Styles", Justification = "<Consistency>")]
		public string address_line { get; set; } = "";

		[System.Diagnostics.CodeAnalysis.SuppressMessage("Style", "IDE1006:Naming Styles", Justification = "<Consistency>")]
		public string city { get; set; } = "";

		[System.Diagnostics.CodeAnalysis.SuppressMessage("Style", "IDE1006:Naming Styles", Justification = "<Consistency>")]
		public string state { get; set; } = "";

		[System.Diagnostics.CodeAnalysis.SuppressMessage("Style", "IDE1006:Naming Styles", Justification = "<Consistency>")]
		public string zip { get; set; } = "";

		// starting position of found address in plain text unique body
		public int AddressIndex { get; set; } = -1;
		public short AddressLength { get; set; } = -1;

		/*
		 *  Properties that duplicate named properties of standard message object
		 * 
		 *  Needed for consistency
		 */

		[System.Diagnostics.CodeAnalysis.SuppressMessage("Style", "IDE1006:Naming Styles", Justification = "<Consistency>")]
		public string to_personal { get; set; } // extracted from to object

		[System.Diagnostics.CodeAnalysis.SuppressMessage("Style", "IDE1006:Naming Styles", Justification = "<Consistency>")]
		public string to_email { get; set; }    // extracted from to object

		[System.Diagnostics.CodeAnalysis.SuppressMessage("Style", "IDE1006:Naming Styles", Justification = "<Consistency>")]
		public string from_personal	{ get; set; }   // extracted from from object

		[System.Diagnostics.CodeAnalysis.SuppressMessage("Style", "IDE1006:Naming Styles", Justification = "<Consistency>")]
		public string from_email { get; set; }  // extracted from from object	

		[System.Diagnostics.CodeAnalysis.SuppressMessage("Style", "IDE1006:Naming Styles", Justification = "<Consistency>")]
		public string from_domain { get; set; }     // domain portion of string from_email

		[System.Diagnostics.CodeAnalysis.SuppressMessage("Style", "IDE1006:Naming Styles", Justification = "<Consistency>")]
		public string reply_to_personal	{ get; set; }   // extracted from reply_to object

		[System.Diagnostics.CodeAnalysis.SuppressMessage("Style", "IDE1006:Naming Styles", Justification = "<Consistency>")]
		public string reply_to_email { get; set; }  // extracted from reply_to object

		[System.Diagnostics.CodeAnalysis.SuppressMessage("Style", "IDE1006:Naming Styles", Justification = "<Consistency>")]
		public List<string[]> to_array { get; set; }  // processed from to object;

		[System.Diagnostics.CodeAnalysis.SuppressMessage("Style", "IDE1006:Naming Styles", Justification = "<Consistency>")]
		public List<string[]> cc_array { get; set; }  // processed from from object	

		[System.Diagnostics.CodeAnalysis.SuppressMessage("Style", "IDE1006:Naming Styles", Justification = "<Consistency>")]
		public string email_date_time { get; set; } // The message date_time in blog local timze zone in mysql format (for display and sorting only)	

		[System.Diagnostics.CodeAnalysis.SuppressMessage("Style", "IDE1006:Naming Styles", Justification = "<Consistency>")]
		public string subject { get; set; }

		[System.Diagnostics.CodeAnalysis.SuppressMessage("Style", "IDE1006:Naming Styles", Justification = "<Consistency>")]
		public string message_id { get; set; }  

		[System.Diagnostics.CodeAnalysis.SuppressMessage("Style", "IDE1006:Naming Styles", Justification = "<Consistency>")]
		public string raw_html_body { get; set; }  // only used for display in message viewer

		[System.Diagnostics.CodeAnalysis.SuppressMessage("Style", "IDE1006:Naming Styles", Justification = "<Consistency>")]
		public long inbox_image_id { get; set; }

		// presentation variables -- loaded in object for transport across parse steps, but also saved on image record . . . never changed aftre parse

		[System.Diagnostics.CodeAnalysis.SuppressMessage("Style", "IDE1006:Naming Styles", Justification = "<Consistency>")]
		public string category { get; set; } = "CATEGORY_INDIVIDUAL";

		[System.Diagnostics.CodeAnalysis.SuppressMessage("Style", "IDE1006:Naming Styles", Justification = "<Consistency>")]
		public string snippet { get; set; }

		[System.Diagnostics.CodeAnalysis.SuppressMessage("Style", "IDE1006:Naming Styles", Justification = "<Consistency>")]
		public string account_thread_id { get; set; }

		// following properties are used to retain found values prior to posting them to data base
		public long Batch_mapped_issue { get; set; } = 0;
		public string Batch_mapped_pro_con { get; set; } = "";
		public long Batch_found_constituent_id { get; set; } = 0;
		public bool Batch_filtered { get; set; } = false; // either on email_address or constituent_geography
		public string Batch_is_my_constituent { get; set; } = "";

		// move and reformat the graph properties into expect legacy values
		public void MoveGraphToLegacy( SqlConnection connection )
		{

			try
			{
				to_personal = OriginalMessage.toRecipients.Length > 0 ? OriginalMessage.toRecipients[0].emailAddress.name : "";
				to_email = OriginalMessage.toRecipients.Length > 0 ? OriginalMessage.toRecipients[0].emailAddress.address : "";
			}
			catch (Exception e)
            {
				to_personal = "";
				to_email = "";
				Console.WriteLine("Exception caught at CRMParsedMessageObject.cs, line 158 (likely bad index in incoming message toRecipients array):" + e.ToString());
			}

			from_personal = OriginalMessage.from?.emailAddress.name;
			from_email = OriginalMessage.from?.emailAddress.address;
			
			try
			{
				from_domain = from_email?.Split("@")[1]; // takes for granted that from_email if not null is a valid email; experience says this is not always true, so try/catch
			}
			catch (Exception e)
            {
				Console.WriteLine("Exception caught at CRMParsedMessageObject.cs, issue with from email, no @:" + e.ToString());
				from_domain = "ErrorOnDomainParse";

			}

			try
			{
				reply_to_personal = OriginalMessage.replyTo.Length > 0 ? OriginalMessage.replyTo[0].emailAddress.name : "";
				reply_to_email = OriginalMessage.replyTo.Length > 0 ? OriginalMessage.replyTo[0].emailAddress.address : "";
			}
			catch (Exception e)
			{
				Console.WriteLine("Exception caught at CRMParsedMessageObject.cs, issue with reply email index:" + e.ToString());
				reply_to_personal = "";
				reply_to_email = "";

			}

			to_array = RepackAddressArray(connection, OriginalMessage.toRecipients);
			cc_array = RepackAddressArray(connection, OriginalMessage.ccRecipients);

			// sentDateTime is in eastern time zone -- accept it without further analysis
			email_date_time = OriginalMessage.sentDateTime.ToString("u", CultureInfo.CreateSpecificCulture("en-US")).Substring(0, 19);
			// strips external marker from subjectline
			subject = String.IsNullOrEmpty( OriginalMessage.subject ) ? "" : Regex.Replace(OriginalMessage.subject, @"^ *\[ *external[: ]*\][: ]", string.Empty, RegexOptions.IgnoreCase, TimeSpan.FromSeconds(2));

			message_id = OriginalMessage.id;
			// do not repack internet headers -- run category analysis directly from underlying message
			// do not store text body separately -- requesting html always gets html even if text is sent by originator
			raw_html_body = OriginalMessage.body.content;

			// inbox_image_id is set in constructor
			category = string.Empty; // will set category later;
			snippet = OriginalMessage.bodyPreview;
			account_thread_id = OriginalMessage.conversationId;
		}


		private List<string[]> RepackAddressArray(SqlConnection connection,GraphRecipient[] recipientArray)
		{
			List<string[]> newRecipientList = new List<string[]>();
			foreach (GraphRecipient recipient in recipientArray)
			{
				var recipientReformatted = new string[]
				{
					recipient.emailAddress.name,
					IsValidEmail(recipient.emailAddress.address) ? recipient.emailAddress.address : string.Empty,
					GetConstituentFromEmailAddress (connection, recipient.emailAddress.address)
				};
				newRecipientList.Add(recipientReformatted);
			}

			return newRecipientList;
		}

		// https://stackoverflow.com/questions/1365407/c-sharp-code-to-validate-email-address
		private bool IsValidEmail(string email)
		{
			try
			{
				var addr = new System.Net.Mail.MailAddress(email);
				return addr.Address == email;
			}
			catch
			{
				return false;
			}
		}

		// returns int constituent id as string
		public string GetConstituentFromEmailAddress( SqlConnection connection, string emailAddress )
        {
			return MessageSQLCommands.GetConstituentFromEmailAddress( connection, OFFICE, emailAddress) ;
        }

	} // class
} // namespace
