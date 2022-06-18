using Microsoft.Graph;
using System;
using System.Collections.Generic;
using System.Data.SqlClient;
using System.Text.Json;

namespace SendMailGraph
{
    class GraphClientActions
    {
        /*
         * get next message using proc and send it
         *      return code 0 is OK sent message
         *      return code 1 is no eligible message to send
         *      return code 2 is graph bounced message 
         */
        public static async System.Threading.Tasks.Task<short> SendNextMessageAsync(GraphServiceClient graphClient, SqlConnection connection)
        {
            SqlDataReader reader = MessageSQLCommands.GetNextQueuedOutgoing(connection);
            while (reader.Read())
            {

                long outboxID = reader.GetInt64(0);
                string office_email = reader.GetString(1);
                string office_label = reader.GetString(2);
                // deserialize json from php
                var options = new JsonSerializerOptions
                {
                    PropertyNameCaseInsensitive = true,
                };
                CRMMessageObject outboxMessage;
                try
                {
                    outboxMessage = JsonSerializer.Deserialize<CRMMessageObject>(reader.GetString(3),options);
                }
                catch
                {
                    MessageSQLCommands.UpdateMessageQueueWithSendResults(
                        connection, outboxID, "Before request", "Bad JSON message format", 20);
                    reader.Close();
                    return 20;
                }
                // convert to_array to Recipient List
                List<Recipient> toRecipients = new List<Recipient>();
                foreach (CRMRecipient addresseeObject in outboxMessage.To_array) {
                    var toRecipient = new Recipient
                    {
                        EmailAddress = new EmailAddress
                        {
                            Address = addresseeObject.Address,
                            Name = addresseeObject.Name
                        }
                    };
                    toRecipients.Add(toRecipient);
                }

                // convert cc_array to Recipient List
                List<Recipient> ccRecipients = new List<Recipient>();
                foreach (CRMRecipient addresseeObject in outboxMessage.Cc_array)
                {
                    var ccRecipient = new Recipient
                    {
                        EmailAddress = new EmailAddress
                        {
                            Address = addresseeObject.Address,
                            Name = addresseeObject.Name
                        }
                    };
                    ccRecipients.Add(ccRecipient);
                }

                // convert bcc_array to Recipient List
                List<Recipient> bccRecipients = new List<Recipient>();
                foreach (CRMRecipient addresseeObject in outboxMessage.Bcc_array)
                {
                    var bccRecipient = new Recipient
                    {
                        EmailAddress = new EmailAddress
                        {
                            Address = addresseeObject.Address,
                            Name = addresseeObject.Name
                        }
                    };
                    bccRecipients.Add(bccRecipient);
                }

                // create reply to List
                List<Recipient> replyTo = new List<Recipient>();
                Recipient replyToItem = new Recipient
                {
                    EmailAddress = new EmailAddress
                    {
                        Address = office_email,
                        Name = office_label
                    }
                };
                replyTo.Add(replyToItem);

                // build the outgoing message object
                var message = new Message
                { 
                    From = new Recipient {
                        EmailAddress = new EmailAddress
                        {
                            Address = office_email,
                            Name = office_label
                        }
                    }, 
                    ReplyTo = replyTo,
                    Subject = outboxMessage.Subject,
                    Body = new ItemBody
                    {
                        ContentType = BodyType.Html,
                        Content = outboxMessage.Html_body,
                    },
                    ToRecipients = toRecipients,
                    CcRecipients = ccRecipients,
                    BccRecipients = bccRecipients 
                };
            
                string messageID;
                try
                {
                    Message response = await graphClient.Users[office_email].Messages
                        .Request()
                        .AddAsync(message);
                    messageID = response.Id;
                }
                catch (ServiceException e)
                {
                    short handleHow = ParseSendError(e);
                    MessageSQLCommands.UpdateMessageQueueWithSendResults(
                        connection, outboxID, e.Error.ClientRequestId, e.Error.Code, handleHow);
                    reader.Close();
                    return handleHow;
                }

                // have good message ID, proceed to add attachments if any
                SqlDataReader attachmentReader = MessageSQLCommands.GetOutgoingAttachments(connection, outboxID);
                while (attachmentReader.Read())
                {    
                    string cid = attachmentReader.GetString(0);
                    string filename = attachmentReader.GetString(1);
                    bool disposition = ("inline" == attachmentReader.GetString(2));
                    int size = attachmentReader.GetInt32(3);
                    string type = attachmentReader.GetString(4);
                    string body = attachmentReader.GetString(5); // base64 encoded

                    var attachment = new FileAttachment
                    {
                        ContentBytes = System.Convert.FromBase64String(body),
                        ContentId = cid,
                        ContentType = type,
                        IsInline = disposition,
                        Name = filename,
                        Size = size,
                    };

                    try
                    {
                        Attachment attachmentResponse =
                            await graphClient.Users[office_email].Messages[messageID].Attachments
                            .Request()
                            .AddAsync(attachment);

                    }
                    catch (ServiceException e)
                    {
                        short handleHow = ParseSendError(e);
                        MessageSQLCommands.UpdateMessageQueueWithSendResults(
                            connection, outboxID, e.Error.ClientRequestId, e.Error.Code, handleHow);
                        Console.WriteLine("172 error message:" + e.Message);
                        attachmentReader.Close();
                        return handleHow;
                    }

                }

                try
                // have added all attachments -- time to send 
                {

                    await graphClient.Users[office_email].Messages[messageID]
                        .Send()
                        .Request()
                        .PostAsync();
                    // good response from Graph
                    MessageSQLCommands.UpdateMessageQueueWithSendResults(
                        connection, outboxID, "","",0);
                    return 0;
                }
                catch (ServiceException e)
                {
                    short handleHow = ParseSendError(e);
                    MessageSQLCommands.UpdateMessageQueueWithSendResults(
                        connection, outboxID, e.Error.ClientRequestId, e.Error.Code, handleHow);
                    reader.Close();
                    return handleHow;
                }
                catch (Exception ex) // handle more general exception including unauthorized account
                {
                    // treat as a specific user related exception with out parsing
                    string errorMessage = "nonServiceException: " + ex.Message;
                    string errorMessageStub = errorMessage.Substring(0, 99);
                    MessageSQLCommands.UpdateMessageQueueWithSendResults(
                        connection, outboxID, "", errorMessageStub, 3);
                    reader.Close();

                    return 3;
                }
            } // message reader 

            // only get here if no message read, return 1 -- no eligible message to send
            reader.Close();
            return 1;
        } // function

        static short ParseSendError ( ServiceException e)
        {
            // basic expected graph error codes: https://docs.microsoft.com/en-us/graph/errors#code-property
            // exchange error codes: https://docs.microsoft.com/en-us/dotnet/api/exchangewebservices.responsecodetype?view=exchange-ews-proxy
            // exchange and other error codes apparently can be passed through on top level (not checking inner errors)
            switch (e.Error.Code)
            { 

                // possible message specific problem
                case "BadRequest":
                case "invalidRequest":
                case "ErrorMissingRecipients": // exch -- have succeeded in generating this error in log
                case "ErrorAttachmentSizeLimitExceeded": // exch
                case "ErrorMailboxDataArrayTooBig": // exch
                case "ErrorMessageSizeExceeded": // exch
                case "ErrorMissingInformationEmailAddress": // exch
                case "ErrorNonExistentMailbox": // exch
                case "ErrorInvalidProperty": // exch? -- have succeeded in generating this error
                case "RequestEntityTooLarge": // exch? -- have succeeded in generating this error
                case "generalException": // alternatively generated this with oversized array of bytes for body of attachment
                    return 20;
                // authorization issues related to particular user
                case "accessDenied":
                case "ErrorSendAsDenied":
                case "ErrorAccessDenied": // exch
                case "ErrorAccountDisabled": // exch
                    return 30;
                // activity level issues related to particular user
                case "activityLimitReached":
                case "quotaLimitReached":
                    return 31;
                // larger environmental problems
                case "serviceNotAvailable":
                case "unauthenticated":
                case "ErrorCallerIsInvalidADAccount": // exch
                case "ErrorConnectionFailed": //
                    return 40;
                // unanticipated -- will treat as 31 (throttle user)
                default:
                    return 50;
            }
            // receive errors not handled: resyncRequired, syncStateNotFound 
        }
    } // class
} // namespace
