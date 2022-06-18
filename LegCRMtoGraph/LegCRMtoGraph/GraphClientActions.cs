using Microsoft.Graph;
using System;
using System.Collections.Generic;
using System.Data.SqlClient;
using System.Threading.Tasks;
using System.Web;

namespace LegCRMtoGraph
{
    public class GraphClientActions
    {

        public static async Task SynchMailFolder(GraphServiceClient graphClient, SqlConnection connection, short OFFICE, string office_email, string deltaToken )
        {
            /****
             * Synchronizing between outlook mail folder ("server") and "image" of that folder on database.  
             *      The synch process is not symmetric, because deletion from inbox can happen in app (image) or in outlook (server), but adds to inbox only happen in outlook
             *          On the app side, two fields are maintained to represent logical delete: to_be_moved_on_server and no_longer_in_server_folder 
             * 
             * Logic is intended to be rerunnable, restartable, not dependent on prior synch.
             * 
             * If string deltatoken is provided does incremental synch; otherwise does full synch; reset synch by blanking the deltatoken field on the office database
             * 
             * Considers four disjoint situations for messages (which cover all possibilities):
             * 
             * 1: In server folder, not in image folder:  Add to image.   
             *      Same logic whether full or incremental sync --  done by SaveNewGraphMessage.
             * 2. In server folder and in image folder, but marked on image as to_be_moved_on_server:  Delete from server.
             *      (NOTE THAT "DELETE" FROM SERVER IS NOT DELETE, JUST MOVE TO DELETED ITEMS FOLDER.)
             *      In full sync case, all messages in the server (whether to be inserted or found as already extant) are stamped with nowStamp for testing in case 4
             *          In delta run, only stamp new messages for the record; case 4 is handled with news of removal in the main loop
             * 3. In Folder and image, not marked as to_be_moved_on_server: No action, already synchronized.
             *      Same logic (i.e., do nothing) whether full or incremental synch
             * 4. In image, not on server:  mark as no_longer_in_server_folder in image
             *      In full sync case, this in handled by identifying messages not stamped in this run
             *      In incremental case, getting incremental delete notices and marking the image as to them.
             *
             */

            var queryOptions = new List<Microsoft.Graph.Option>();
            bool startUp = true; 
            // null or empty deltaToken option generates error, but absence of deltaToken option is OK with DeltaQuery
            if ( !String.IsNullOrWhiteSpace(deltaToken))
            {
                var deltaTokenOption = new QueryOption("$deltatoken", deltaToken);
                queryOptions.Add (deltaTokenOption);
                startUp = false;
            }

            // in full sync case, set now stamp to track extant messages (both new and added to inbox_image)
            long nowStamp = 0;
            if ( startUp )
            {
                nowStamp = MessageSQLCommands.GetNowStamp(connection);
            }


            // get messages (all in inbox or just the delta if delta is not empty
            try
            {
                var messages = await graphClient.Users[office_email].MailFolders["inbox"].Messages
                    .Delta()
                    .Request(queryOptions)
                    .Header("Prefer", "outlook.Body-content-type=\"html\"") // necessary to specify this even though nominal default; otw plain text sent messages come as plain
                    .Select(
                        " bccRecipients" +
                        ",body" +
                        ",bodyPreview" +
                        ",categories" +
                        ",ccRecipients" +
                        ",changeKey" +
                        ",conversationId" +
                        ",conversationIndex" +
                        ",createdDateTime" +
                        ",flag" +
                        ",from" +
                        ",hasAttachments" +
                        ",id" +
                        ",importance" +
                        ",inferenceClassification" +
                        ",internetMessageHeaders" + // only available on explicit select, the only reason to do this full listing of requested properties
                        ",internetMessageId" +
                        ",isDeliveryReceiptRequested" +
                        ",isDraft" +
                        ",isRead" +
                        ",isReadReceiptRequested" +
                        ",lastModifiedDateTime" +
                        ",receivedDateTime" +
                        ",replyTo" +
                        ",sender" +
                        ",sentDateTime" +
                        ",subject" +
                        ",toRecipients" +
                        // ",uniqueBody" -- have to get later in plain text form
                        ",webLink"
                      )
                    .GetAsync();

                //https://docs.microsoft.com/en-us/graph/sdks/paging?tabs=csharp
                // documentation doesn't say this, but if do other asynch tasks within the lambda, 
                // results are unpredictable; cannot await those results because this lambda must return bool to satisfy the CreatePageIterator type def
                var pageIterator = PageIterator<Message>
                     .CreatePageIterator(graphClient, messages, (m) =>
                     {
                        /* 
                         * need to interrogate AdditionalData to interpret deltaquery results.  
                         * if key @removed is present, indicates message was removed from inbox.
                         * delta query response includes other types of changes, like read events, and probably others like label adds
                         * 
                         * AdditionalData is a compiler-recognized property of the message object although not obviously documented.   AdditionalData appears 
                         *    as a "property bag" attached to many Microsoft objects.  It is a dictionary object.
                         *    Must be inherited property for messages, but cannot see it in meta data -- https://graph.microsoft.com/v1.0/$metadata
                         *    EntityType message has base type outlookitem which has base type entity, neither of which refers to Additional data.
                         *    
                         *    Has to be extendedproperty collection, a navigationproperty of message?  https://docs.microsoft.com/en-us/graph/api/resources/extended-properties-overview?view=graph-rest-1.0
                         *    Could it go back to Outlook Messaging API term that is not exposed in the graph API?
                         *
                         */
                        if (null != m.AdditionalData && m.AdditionalData.ContainsKey("@removed"))
                        {
                                // handling case 4 in incremental mode -- picking up deletions
                                bool deleted = MessageSQLCommands.MarkSingleNoLongerOnServer(connection, OFFICE, office_email, m.Id);
                        }
                        /*
                         * checking for message elements so as to
                         * exclude read events and other changes from SaveNewGraphMessage 
                         * (which would reject them anyway as already matching)
                         */
                        else if (m.From != null || m.Body != null || m.Subject != null)
                        {
                             bool hasAttachments = false;
                             if (true == m.HasAttachments
                                // Hasattachments property does not reflect inline attachments https://docs.microsoft.com/en-us/graph/api/resources/message?view=graph-rest-1.0
                                // This simple search for inline images may be over or under inclusive, but neither should break code;
                                || m.Body.Content.Contains(" src=\"cid:im", System.StringComparison.CurrentCultureIgnoreCase) // just src generates false positives which creates prohibitive unnecessary lookups;
                                // note, cannot do this search on the sql side because json_value does not parse the body well
                             )
                             {
                                 hasAttachments = true;
                             }

                             // handle case 1 (bypassing case 3 in partial mode but in full mode, marking case 3 records with this run's nowStamp)
                             long savedID = MessageSQLCommands.SaveNewGraphMessage(
                                connection,
                                OFFICE,
                                office_email,
                                m,
                                m.Id,
                                startUp, // 0/false if incremental, 1/true if full synch
                                nowStamp,
                                hasAttachments
                            );

                            // Console.WriteLine(savedID + " : " + m.SentDateTime + " : " + m.From.EmailAddress.Address + " : " + m.Subject);
                        }
                        return true;
                    });

                await pageIterator.IterateAsync();
                
                string newDeltaToken = HttpUtility.ParseQueryString(pageIterator.Deltalink.Split("?", 2)[1])["$deltatoken"];
                _ = MessageSQLCommands.StashDeltaToken(connection, OFFICE, newDeltaToken);
            }
            catch( ServiceException ex )
            {
                // this error log procedure will also reset office sync state on ex.Error.Code = 'syncStateNotFound' OR ex.Error.Code = 'resyncRequired' OR ex.Error.Code = 'badRequest'
                LegCRMtoGraph.MessageSQLCommands.LogReceiveErrorEvent(connection, OFFICE, ex.Error.ClientRequestId, ex.Error.Code);
                return;
                /*
                 * if return before iterator completed . . .
                 *      unreached case 1 messages (new) will be picked up on next cycle; 
                 *          next cycle will start from same delta token and possibly attempt (unsuccessfully) to dup save messages
                 *      case 2 messages (delete from image to server) are outside the iterator loop and will be picked up after any successful cycle
                 *      case 3 messages are no action anyway
                 *      case 4 messages (delete from server to inbox) 
                 *             are reattempted on incremental sync (since same delta token) . . . no harm if twice
                 *             are outside the loop for full sync
                 *      attachments are independent, but will happe on next successful loop
                 */
            }
            catch (Exception e) // successfully completed iterator?
            {
                Console.WriteLine(e.Message);
                return;
            }

            // whole message set has been successfully processed without exception at this stage doing procedures outside the iterator loop

            // handle case 2 -- retrieve messages ready to be deleted -- same in full or partial sync
            await DeleteMarkedMessages(graphClient, connection, OFFICE, office_email);

            if (startUp)
            {
                // handle case 4 in startup run -- test for messages that did not get nowStamp from this run (either inserted or restamped)
                MessageSQLCommands.MarkNoLongerOnServer(connection, OFFICE, office_email, nowStamp );
                // in the incremental run, getting notice of individual case 4 deletions and already handled them in the main request loop
            }

            // get attachments for this Office -- may or may not be associated with current batch of retrieved messages
            await GetAttachments(graphClient, connection, OFFICE, office_email);
            
        } // function 

        // this function is the same whether full or partial sync
        // may attempt to delete messages that user has deleted in both  outlook and on server
        //      this will generate ErrorItemNotFound exception which is handled in the same way as  success -- mark message no_longer_in_server_folder
        public static async Task DeleteMarkedMessages(GraphServiceClient graphClient, SqlConnection connection, short OFFICE, string office_email) // add variable for delta or not
        {
            /*
             * this is not a 'delete' actually, 
             * just a move to deleted items; 
             * the delete graph request would effect a hard delete beyond the deleted folder to the recoverable items folder
             */
            var destinationId = "deleteditems"; 
            SqlDataReader mtbdReader = MessageSQLCommands.GetMessagesToBeDeleted(connection, OFFICE, office_email);
            while (mtbdReader.Read())
            {
                var message_id = mtbdReader.GetString(0);
                try
                {
                    await graphClient.Users[office_email].Messages[message_id]
                        .Move(destinationId)
                        .Request()
                        .PostAsync();

                    // update message on inbox_image as no_longer_in_server_folder (set = 1)
                    // all parameters are needed for this request
                    MessageSQLCommands.MarkSingleNoLongerOnServer(connection, OFFICE, office_email, message_id);
                }
                catch (ServiceException ex)
                {
                    // may get exception if message has alread been moved/deleted
                    if ("ErrorItemNotFound" == ex.Error.Code)
                    {
                        // not an error, acceptable outcome
                        MessageSQLCommands.MarkSingleNoLongerOnServer(connection, OFFICE, office_email, message_id);
                    }
                    else
                    {
                        LegCRMtoGraph.MessageSQLCommands.LogReceiveErrorEvent(connection, OFFICE, ex.Error.ClientRequestId, ex.Error.Code);
                    }
                }
                catch (Exception e) 
                {
                    Console.WriteLine(e.Message);
                }
            }
            // while loop continues regardless of try/catch outcome
            mtbdReader.Close();
            return;
        } // function 

        private static async Task GetAttachments(GraphServiceClient graphClient, SqlConnection connection, short OFFICE, string office_email )
        {
            SqlDataReader attachmentReader = MessageSQLCommands.GetMessagesWithAttachments(connection, OFFICE);
            while (attachmentReader.Read())
            {
                // message with attachments to retrieve
                string message_id = attachmentReader.GetString(0);
                long inbox_message_id = attachmentReader.GetInt64(1);                
                
                try
                {
                    var attachments = await graphClient.Users[office_email].Messages[message_id].Attachments
                            .Request()
                            .GetAsync();

                    if ( 0 == attachments.Count )
                    {
                        Console.WriteLine("Attempted to get attachments and found none for inbox message: " + inbox_message_id.ToString());
                        // stop message from cycling; 
                        MessageSQLCommands.MarkMessageAsWithoutAttachment(connection, inbox_message_id);
                    }

                    var pageIterator = PageIterator<Attachment>
                        .CreatePageIterator(graphClient, attachments, (a) =>
                        {
                        // for now only handling file attachments (not item or reference attachments)
                        if (a.ODataType == "#microsoft.graph.fileAttachment")
                            {
                                MessageSQLCommands.SaveNewMessageAttachment(
                                    connection,
                                    OFFICE,
                                    inbox_message_id,
                                    ref a
                               );
                            } // if
                        return true;
                        }); // define iterator lambda

                    await pageIterator.IterateAsync();
                }
                catch (ServiceException ex)
                {
                    LegCRMtoGraph.MessageSQLCommands.LogReceiveErrorEvent(connection, OFFICE, ex.Error.ClientRequestId, "Attachment: " + ex.Error.Code);
                }
                catch (Exception e)
                {
                    Console.WriteLine(e.Message);
                }
            }
            attachmentReader.Close();
            return;
        } // function
    } // class
} // namespace