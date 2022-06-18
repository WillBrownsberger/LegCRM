using parseMessages.AddressExtraction;
using parseMessages.AssignCategory;
using parseMessages.EmailExtraction;
using parseMessages.NameExtraction;
using parseMessages.ParsedMessageObjects;
using parseMessages.PhoneExtraction;
using System;
using System.Data.SqlClient;
using System.Text.Json;
using System.Threading;
using System.Threading.Tasks;


using System.Data;
using System.Net.Http;

namespace parseMessages
{
    class Program
    {
        static async Task Main()
        {

            /*
             * This process is designed to be run as a continuously running web job.
             * 
             * It will cycle not more frequently than every 60 seconds, checking the database for messages that need to be parsed
             * 
             */
            String runStatus = "GO";
            while ("GO" == runStatus)
            {
                DateTime startRun = DateTime.Now;

                // set up access to Graph and Database
                var accessObjects = new LegCRMtoGraph.SetAccessObjects();
                var graphClient = accessObjects.GraphClient();
                var connection = accessObjects.SqlConnection();

                // check for possible shutdown
                runStatus = accessObjects.appConfig["runStatus"];
                // open database connection
                connection.Open();

                // messages meeting selection criteria will be selected for parsing in order by id
                long IDPointer = 0;

                // start loop
                while (1==1)
                {
                    // get a message to parse
                    UnparsedMessage unparsedMessage = MessageSQLCommands.GetSingleMessageToParse(connection, IDPointer);
                    if ( unparsedMessage.ID == 0)
                    {
                        break;  // effectively, this is a while reader.read loop, but getting single messages to avoid reader close issues
                                // for reasons not understood, cannot update the message record while the reader loop is open, even with a separate connection
                                // . . . cannot reproduce this problem with simple model though
                    } else
                    {
                        IDPointer = unparsedMessage.ID; // LegCRM ID of message
                    }

                    // skip message retrieval exception in this pass, but keep looking for more -- pointer has advanced;
                    if (string.IsNullOrEmpty(unparsedMessage.MessageJson)) continue;
                    
                    // construct fully parsedMessageObject with fields mapping to legacy fields
                    CRMParsedMessageObject parsedMessageObject = new CRMParsedMessageObject(connection, unparsedMessage);

                    // skip message parse failure
                    if (!parsedMessageObject.successfulParse) continue;

                    // get Unique Body for message
                    var workingUniqueBody = await GraphClientActions.GetUniqueBody(
                        graphClient,
                        connection,
                        parsedMessageObject.OFFICE,
                        parsedMessageObject.officeEmail,
                        parsedMessageObject.message_id
                    );

                    // skip message if could not retrieve unique body (empty string has contentType "text")
                    // most likely reason is that message is no longer on server, deleted on outlook side
                    if ("EMPTY" == workingUniqueBody.contentType)
                    {
                        continue;
                    }
                    else
                    {
                        parsedMessageObject.OriginalMessage.uniqueBody = new ItemBody
                        {
                            content = workingUniqueBody.content,
                            contentType = workingUniqueBody.contentType
                            // note that empty string is a valid uniqueBody but with contentType text
                        };
                    };


                    // run address extraction, populating address fields of message from text
                    ExtractAddress.Extract(ref parsedMessageObject);

                    // run name extraction, populating name fields of message from text
                    ExtractName.Extract(connection, ref parsedMessageObject);

                    // run phone extraction from text
                    ExtractPhone.Extract(ref parsedMessageObject);

                    // run email extraction from text
                    ExtractEmail.Extract(ref parsedMessageObject);

                    // do subject line mapping -- populates Batch_mapped_issue or pro_con vs db; no db updates
                    MessageSQLCommands.GetSubjectLineMapping(connection, ref parsedMessageObject);

                    // do constituent lookup -- populates Batch_found_constituent_id and Batch_is_my_constituent; no db updates
                    MessageSQLCommands.DoesConstituentExist(connection, ref parsedMessageObject);

                    // do email filtering -- populates Batch_filtered; no db updates
                    MessageSQLCommands.IsEmailAddressFiltered(connection, ref parsedMessageObject);

                    // do geography filtering based on is_my_constituent rules if not already filtered
                    if (!parsedMessageObject.Batch_filtered)
                    {
                        MessageSQLCommands.IsConstituentFiltered(connection, ref parsedMessageObject);
                        // will send auto reply is settings say so
                    }

                    // do category assignment
                    Categorize.CategorizeMessage(ref parsedMessageObject);

                    // save object to CRM
                   _ = MessageSQLCommands.SaveParsedMessageObject(connection, ref parsedMessageObject);

                    // save category and constituent indicator to graph
                    if (MessageSQLCommands.AreOutlookCategoriesEnabled(connection, ref parsedMessageObject))
                    {
                        await GraphClientActions.AddCategories(
                            graphClient,
                            parsedMessageObject.officeEmail,
                            parsedMessageObject.OriginalMessage.id,
                            Categorize.ParseOutlookCategories(parsedMessageObject)
                        );
                    }
                    // consider adding a post process that categorizes based on volume?

                }

                // NOTE: no longer compiling thread date times 

                // cleanup
                connection.Close();

                // wait to make the loop one minute minimum
                DateTime endRun = DateTime.Now;
                TimeSpan timeSpan = (endRun - startRun);
                var elapsed = (int)timeSpan.TotalSeconds;
                if (elapsed < 60)
                {
                    int cycleDelay = 60 - elapsed;
                    Thread.Sleep(1000 * cycleDelay);
                }
            }
        }
    }
}
