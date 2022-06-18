using Microsoft.Graph;
using System;
using System.Threading.Tasks;
using parseMessages.ParsedMessageObjects;
using parseMessages.AssignCategory;
using System.Data.SqlClient;

namespace parseMessages
{
    class GraphClientActions
    {
		public static async Task<ParsedMessageObjects.ItemBody> GetUniqueBody(
			GraphServiceClient graphClient, 
			SqlConnection connection, 
			short OFFICE, 
			string officeEmail, 
			string messageId 
			)
		{

			ParsedMessageObjects.ItemBody workingUniqueBody = new ParsedMessageObjects.ItemBody();

			try
			{
				var message = await graphClient.Users[officeEmail].MailFolders["inbox"].Messages[messageId]
					.Request()
					.Header("Prefer", "outlook.Body-content-type=\"text\"")
					.Select("uniqueBody")
					.GetAsync();

				workingUniqueBody.content = message.UniqueBody.Content;
				workingUniqueBody.contentType = message.UniqueBody.ContentType.ToString();
			}
			catch (ServiceException ex)
			{

				// may get exception if message has alread been moved/deleted
				if ("ErrorItemNotFound" == ex.Error.Code)
				{
                    // not an error, acceptable outcome
                    _ = LegCRMtoGraph.MessageSQLCommands.MarkSingleNoLongerOnServer(connection, OFFICE, officeEmail, messageId);
				}
				else
				{
                    _ = LegCRMtoGraph.MessageSQLCommands.LogReceiveErrorEvent(connection, OFFICE, ex.Error.ClientRequestId, ex.Error.Code);
				}

				workingUniqueBody.content = string.Empty;
				workingUniqueBody.contentType = "EMPTY";
			}
			catch (Exception e)
			{
				Console.WriteLine(e.Message);
				workingUniqueBody.content = string.Empty;
				workingUniqueBody.contentType = "EMPTY";
			}

			return workingUniqueBody;
		}

		public static async Task AddCategories (
			
			GraphServiceClient graphClient,
			string officeEmail,
			string messageID,
			string[] newcats
			)
		{
			var messageTemplate = new Message
			{
				Categories = newcats,
				InferenceClassification = Categorize.ParseOutlookInferenceClassification(newcats)
			};

			try
			{
				await graphClient.Users[officeEmail].MailFolders["inbox"].Messages[messageID]
					.Request()
					.UpdateAsync(messageTemplate);
			}
			catch (Exception e)
			{
				Console.WriteLine(e.Message);
			}

		}

    }
}
