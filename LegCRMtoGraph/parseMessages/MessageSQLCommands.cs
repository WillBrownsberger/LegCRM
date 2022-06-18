using System;
using System.Data;
using System.Data.SqlClient;
using LegCRMtoGraph;
using parseMessages.ParsedMessageObjects;
using SendMailGraph;
using System.Text.Json;

namespace parseMessages
{
    class MessageSQLCommands
    {

        public static UnparsedMessage GetSingleMessageToParse(SqlConnection connection, long IDpointer)
        {
            try
            {
                // Create the command and set its properties.
                var command = new SqlCommand
                {
                    Connection = connection,
                    CommandText = "getSingleMessageToParse",
                    CommandType = CommandType.StoredProcedure
                };

                var IDpointerParm = new SqlParameter
                {
                    ParameterName = "@IDpointer",
                    SqlDbType = SqlDbType.BigInt,
                    Direction = ParameterDirection.Input,
                    Value = IDpointer
                };
                _ = command.Parameters.Add(IDpointerParm);

                // output

                var OFFICEParm = new SqlParameter
                {
                    ParameterName = "@OFFICE",
                    SqlDbType = SqlDbType.SmallInt,
                    Direction = ParameterDirection.Output
                };
                _ = command.Parameters.Add(OFFICEParm);

                var office_emailParm = new SqlParameter
                {
                    ParameterName = "@office_email",
                    SqlDbType = SqlDbType.VarChar,
                    Direction = ParameterDirection.Output,
                    Size = 200
                };
                _ = command.Parameters.Add(office_emailParm);

                var IDParm = new SqlParameter
                {
                    ParameterName = "@ID",
                    SqlDbType = SqlDbType.BigInt,
                    Direction = ParameterDirection.Output
                };
                _ = command.Parameters.Add(IDParm);

                var extended_message_idParm = new SqlParameter
                {
                    ParameterName = "@extended_message_id ",
                    SqlDbType = SqlDbType.VarChar,
                    Direction = ParameterDirection.Output,
                    Size = 512
                };
                _ = command.Parameters.Add(extended_message_idParm);

                var message_jsonParm = new SqlParameter
                {
                    ParameterName = "@message_json ",
                    SqlDbType = SqlDbType.VarChar,
                    Direction = ParameterDirection.Output,
                    Size = -1
                };
                _ = command.Parameters.Add(message_jsonParm);

                command.ExecuteNonQuery();

                return new UnparsedMessage((short) OFFICEParm.Value, (string) office_emailParm.Value, (long)IDParm.Value, (string) extended_message_idParm.Value, (string)message_jsonParm.Value);

            }
            catch (Exception e)
            {
                Console.WriteLine("GetSingleMessageToParse: " + e.Message);
                return new UnparsedMessage(
                    0,
                    "",
                    IDpointer+1, // move the ID pointer so that can't get permanently stuck on a bad message
                    "",
                    ""
                    );
            }
        } // function


        /* comparison is to voter file -- less likely to be polluted with non names */
        public static short CheckName(SqlConnection connection, string name)
        /* 0 is no match; 1 is first; 2 is second; 3 is both */
        {
            try
            {
                // Create the command and set its properties.
                var command = new SqlCommand
                {
                    Connection = connection,
                    CommandText = "checkName",
                    CommandType = CommandType.StoredProcedure
                };

                var nameParm = new SqlParameter
                {
                    ParameterName = "@name",
                    SqlDbType = SqlDbType.VarChar,
                    Direction = ParameterDirection.Input,
                    Value = name
                };
                _ = command.Parameters.Add(nameParm);

                var findResultParm = new SqlParameter
                {
                    ParameterName = "@findResultParm",
                    SqlDbType = SqlDbType.SmallInt,
                    Direction = ParameterDirection.Output
                };
                _ = command.Parameters.Add(findResultParm);

                _ = command.ExecuteNonQuery();
                if (findResultParm.Value.GetType().ToString() != "System.DBNull")
                {
                    return (short)findResultParm.Value;
                }
                else
                {
                    Console.WriteLine("Null return from checkName");
                    return 0;
                }
            }
            catch (Exception e)
            {
                Console.WriteLine("CheckName: " + e.Message);
                return 0;
            }
        }


        public static void GetSubjectLineMapping(SqlConnection connection, ref CRMParsedMessageObject parsedMessageObject)
        {

            // set default values regardless of success
            parsedMessageObject.Batch_mapped_issue = 0;
            parsedMessageObject.Batch_mapped_pro_con = string.Empty;

            // do the look up
            try
            {
                // Create the command and set its properties.
                var command = new SqlCommand
                {
                    Connection = connection,
                    CommandText = "getSubjectLineMapping",
                    CommandType = CommandType.StoredProcedure
                };

                var OFFICEParm = new SqlParameter
                {
                    ParameterName = "@OFFICE",
                    SqlDbType = SqlDbType.SmallInt,
                    Direction = ParameterDirection.Input,
                    Value = parsedMessageObject.OFFICE
                };
                _ = command.Parameters.Add(OFFICEParm);


                var subjectLineParm = new SqlParameter
                {
                    ParameterName = "@subjectLine",
                    SqlDbType = SqlDbType.VarChar,
                    Direction = ParameterDirection.Input,
                    Value = parsedMessageObject.subject
                };
                _ = command.Parameters.Add(subjectLineParm);

                // get the next message ID and object
                SqlDataReader reader = command.ExecuteReader();

                while (reader.Read())
                {
                    parsedMessageObject.Batch_mapped_issue = reader.GetInt64(0);
                    parsedMessageObject.Batch_mapped_pro_con = reader.GetString(1);
                    break; // only one value retrieved
                }
                reader.Close();
            }
            catch (Exception e)
            {
                // not a big problem if mappings not set
                Console.WriteLine("GetSubjectLineMapping: " + e.Message);
                return;
            }
        }

        public static void DoesConstituentExist(SqlConnection connection, ref CRMParsedMessageObject parsedMessageObject)
        {

            // set default value regardless of success
            parsedMessageObject.Batch_found_constituent_id = 0;
            parsedMessageObject.Batch_is_my_constituent = string.Empty;

            // by pass if out of state
            if ( parsedMessageObject.state != string.Empty && parsedMessageObject.state != "MA")
            {
                parsedMessageObject.Batch_is_my_constituent = "N";
                return;
            }

            // Create the command and set its properties.
            try
            {
                var command = new SqlCommand
                {
                    Connection = connection,
                    CommandText = "doesConstituentExist",
                    CommandType = CommandType.StoredProcedure
                };

                var OFFICEParm = new SqlParameter
                {
                    ParameterName = "@OFFICE",
                    SqlDbType = SqlDbType.SmallInt,
                    Direction = ParameterDirection.Input,
                    Value = parsedMessageObject.OFFICE
                };
                _ = command.Parameters.Add(OFFICEParm);

                var email_addressParm = new SqlParameter
                {
                    ParameterName = "@emailAddress",
                    SqlDbType = SqlDbType.VarChar,
                    Direction = ParameterDirection.Input,
                    Value = parsedMessageObject.email_address
                };
                _ = command.Parameters.Add(email_addressParm);

                var firstNameParm = new SqlParameter
                {
                    ParameterName = "@firstName",
                    SqlDbType = SqlDbType.VarChar,
                    Direction = ParameterDirection.Input,
                    Value = parsedMessageObject.first_name
                };
                _ = command.Parameters.Add(firstNameParm);

                var lastNameParm = new SqlParameter
                {
                    ParameterName = "@lastName",
                    SqlDbType = SqlDbType.VarChar,
                    Direction = ParameterDirection.Input,
                    Value = parsedMessageObject.last_name
                };
                _ = command.Parameters.Add(lastNameParm);

                var addressLineParm = new SqlParameter
                {
                    ParameterName = "@addressLine",
                    SqlDbType = SqlDbType.VarChar,
                    Direction = ParameterDirection.Input,
                    Value = parsedMessageObject.address_line
                };
                _ = command.Parameters.Add(addressLineParm);

                var zipParm = new SqlParameter
                {
                    ParameterName = "@zip",
                    SqlDbType = SqlDbType.VarChar,
                    Direction = ParameterDirection.Input,
                    Value = parsedMessageObject.zip
                };
                _ = command.Parameters.Add(zipParm);

                var phoneParm = new SqlParameter
                {
                    ParameterName = "@phone",
                    SqlDbType = SqlDbType.BigInt,
                    Direction = ParameterDirection.Input,
                    Value = parsedMessageObject.phone_number
                };
                _ = command.Parameters.Add(phoneParm);

                // get the next message ID and object
                SqlDataReader reader = command.ExecuteReader();

                while (reader.Read())
                {
                    parsedMessageObject.Batch_found_constituent_id = reader.GetInt64(0);
                    parsedMessageObject.Batch_is_my_constituent = reader.GetString(1);
                    break; // only one value retrieved
                }
                reader.Close();

                if ( String.IsNullOrEmpty(parsedMessageObject.Batch_is_my_constituent) )
                {
                    DoesConstituentExistElsewhere(connection, ref parsedMessageObject);
                }
            }
            catch (Exception e)
            {
                // not a big problem if mappings not set
                Console.WriteLine("DoesConstituentExist: " + e.Message);
                return;
            }
        }


        public static void DoesConstituentExistElsewhere(SqlConnection connection, ref CRMParsedMessageObject parsedMessageObject)
        {

            // Create the command and set its properties.
            try
            {
                var command = new SqlCommand
                {
                    Connection = connection,
                    CommandText = "doesConstituentExistElsewhere",
                    CommandType = CommandType.StoredProcedure
                };

                var OFFICEParm = new SqlParameter
                {
                    ParameterName = "@OFFICE",
                    SqlDbType = SqlDbType.SmallInt,
                    Direction = ParameterDirection.Input,
                    Value = parsedMessageObject.OFFICE
                };
                _ = command.Parameters.Add(OFFICEParm);

                var email_addressParm = new SqlParameter
                {
                    ParameterName = "@emailAddress",
                    SqlDbType = SqlDbType.VarChar,
                    Direction = ParameterDirection.Input,
                    Value = parsedMessageObject.email_address
                };
                _ = command.Parameters.Add(email_addressParm);

                var firstNameParm = new SqlParameter
                {
                    ParameterName = "@firstName",
                    SqlDbType = SqlDbType.VarChar,
                    Direction = ParameterDirection.Input,
                    Value = parsedMessageObject.first_name
                };
                _ = command.Parameters.Add(firstNameParm);

                var lastNameParm = new SqlParameter
                {
                    ParameterName = "@lastName",
                    SqlDbType = SqlDbType.VarChar,
                    Direction = ParameterDirection.Input,
                    Value = parsedMessageObject.last_name
                };
                _ = command.Parameters.Add(lastNameParm);

                var addressLineParm = new SqlParameter
                {
                    ParameterName = "@addressLine",
                    SqlDbType = SqlDbType.VarChar,
                    Direction = ParameterDirection.Input,
                    Value = parsedMessageObject.address_line
                };
                _ = command.Parameters.Add(addressLineParm);

                var zipParm = new SqlParameter
                {
                    ParameterName = "@zip",
                    SqlDbType = SqlDbType.VarChar,
                    Direction = ParameterDirection.Input,
                    Value = parsedMessageObject.zip
                };
                _ = command.Parameters.Add(zipParm);

                var phoneParm = new SqlParameter
                {
                    ParameterName = "@phone",
                    SqlDbType = SqlDbType.BigInt,
                    Direction = ParameterDirection.Input,
                    Value = parsedMessageObject.phone_number
                };
                _ = command.Parameters.Add(phoneParm);

                // get the next message ID and object
                SqlDataReader reader = command.ExecuteReader();

                while (reader.Read())
                {
                    parsedMessageObject.Batch_is_my_constituent = reader.GetString(0);
                    break; // only one value retrieved
                }
                reader.Close();

            }
            catch (Exception e)
            {
                // not a big problem if mappings not set
                Console.WriteLine("DoesConstituentExistElsewhere:" + e.Message);
                return;
            }
        }



        public static void IsEmailAddressFiltered(SqlConnection connection, ref CRMParsedMessageObject parsedMessageObject)
        {
            // set default regardless of success
            parsedMessageObject.Batch_filtered = false;

            try
            {
                // Create the command and set its properties.
                var command = new SqlCommand
                {
                    Connection = connection,
                    CommandText = "isEmailAddressFiltered",
                    CommandType = CommandType.StoredProcedure
                };

                var OFFICEParm = new SqlParameter
                {
                    ParameterName = "@OFFICE",
                    SqlDbType = SqlDbType.SmallInt,
                    Direction = ParameterDirection.Input,
                    Value = parsedMessageObject.OFFICE
                };
                _ = command.Parameters.Add(OFFICEParm);

                var email_addressParm = new SqlParameter
                {
                    ParameterName = "@emailAddress",
                    SqlDbType = SqlDbType.VarChar,
                    Direction = ParameterDirection.Input,
                    Value = parsedMessageObject.email_address
                };
                _ = command.Parameters.Add(email_addressParm);

                SqlDataReader reader = command.ExecuteReader();

                while (reader.Read())
                {
                    parsedMessageObject.Batch_filtered = reader.GetBoolean(0);
                    break; // only one value retrieved
                }
                reader.Close();
            }
            catch (Exception e)
            {
                // not a big problem if mappings not set
                Console.WriteLine("IsEmailAddressFiltered: " + e.Message);
                return;
            }
        }


        // note that since job is handling all offices first come first serve, have to check settings on each message
        // this proc is essentially a settings check (and send autoreply if so ordered); already know all there is to know about settings
        public static void IsConstituentFiltered(SqlConnection connection, ref CRMParsedMessageObject parsedMessageObject)
        {

            // default value
            parsedMessageObject.Batch_filtered = false;

            // never geographically filter own constituents;
            if ( "Y" == parsedMessageObject.Batch_is_my_constituent ) {
                return; 
            }

            // only filter trained messages
            if ( 0 == parsedMessageObject.Batch_mapped_issue ) {
                return; 
            }

            try
            {
                // command
                var command = new SqlCommand
                {
                    Connection = connection,
                    CommandText = "isConstituentFiltered",
                    CommandType = CommandType.StoredProcedure
                };

                // input parameters
                var OFFICEParm = new SqlParameter
                {
                    ParameterName = "@OFFICE",
                    SqlDbType = SqlDbType.SmallInt,
                    Direction = ParameterDirection.Input,
                    Value = parsedMessageObject.OFFICE
                };
                _ = command.Parameters.Add(OFFICEParm);

                var isMyConstituentParm = new SqlParameter
                {
                    ParameterName = "@isMyConstituent",
                    SqlDbType = SqlDbType.VarChar,
                    Direction = ParameterDirection.Input,
                    Value = parsedMessageObject.Batch_is_my_constituent
                };
                _ = command.Parameters.Add(isMyConstituentParm);

                // output parameters
                var responseSubjectLineParm = new SqlParameter
                {
                    ParameterName = "@responseSubjectLine",
                    SqlDbType = SqlDbType.VarChar,
                    Direction = ParameterDirection.Output,
                    Size = 400
                };
                _ = command.Parameters.Add(responseSubjectLineParm);

                var responseBodyParm = new SqlParameter
                {
                    ParameterName = "@responseBody",
                    SqlDbType = SqlDbType.VarChar,
                    Direction = ParameterDirection.Output,
                    Size = -1
                };
                _ = command.Parameters.Add(responseBodyParm);

                var filteredParm = new SqlParameter
                {
                    ParameterName = "@filtered",
                    SqlDbType = SqlDbType.Bit,
                    Direction = ParameterDirection.Output,
                };
                _ = command.Parameters.Add(filteredParm);

                _ = command.ExecuteNonQuery();

                // apply output parameters
                parsedMessageObject.Batch_filtered = (bool)filteredParm.Value;
                if (parsedMessageObject.Batch_filtered)
                {
                    // create and populate outgoing object
                    CRMMessageObject replyMessage = new CRMMessageObject();

                    // set up to recipient
                    CRMRecipient recipient = new CRMRecipient
                    {
                        Name = parsedMessageObject.first_name + " " + parsedMessageObject.last_name,
                        Address = parsedMessageObject.email_address,
                        Constituent = (int)parsedMessageObject.Batch_found_constituent_id
                    };
                    CRMRecipient[] recipientArray = { recipient };
                    replyMessage.To_array = recipientArray;

                    // create empty arrays of cc and bcc recipients
                    CRMRecipient[] emptyRecipientArray = { };
                    replyMessage.Bcc_array = emptyRecipientArray;
                    replyMessage.Cc_array = emptyRecipientArray;

                    // slot in other fields . . .
                    replyMessage.Subject = responseSubjectLineParm.Value.ToString();
                    // composing message as form + transition line + original message
                    replyMessage.Html_body =
                        responseBodyParm.Value.ToString() +
                        "<br/><hr/>Replying to message from &lt;" +
                            parsedMessageObject.from_email +
                            "&gt; sent " +
                            parsedMessageObject.email_date_time.ToString() +
                            "<br/><br/>" +
                            parsedMessageObject.raw_html_body;
                    replyMessage.Text_body = string.Empty;
                    replyMessage.Is_draft = 0;
                    replyMessage.Is_reply_to = (int)parsedMessageObject.inbox_image_id;
                    replyMessage.Include_attachments = false;
                    replyMessage.Issue = 0; // don't assign issue -- not creating constituent
                    replyMessage.Pro_con = string.Empty;
                    replyMessage.Search_type = string.Empty;
                    replyMessage.Search_id = 0;
                    replyMessage.Search_parm = string.Empty;
                    replyMessage.Draft_id = 0;

                    _ = SendMailGraph.MessageSQLCommands.QueueOutgoing(connection, parsedMessageObject.OFFICE, replyMessage);
                    return;
                }

            }
            catch (Exception e)
            {
                Console.WriteLine("IsConstituentFiltered said: " + e.Message);
                // if was in the reply branch, isConstituentFiltered set filtered to true, 
                // but since reply failed, set to false so that will remain in inbox
                parsedMessageObject.Batch_filtered = false;
                return;
            }
        }

        public static bool SaveParsedMessageObject(SqlConnection connection, ref CRMParsedMessageObject message)
        {
            string messageJson = "";
            try {
                messageJson = JsonSerializer.Serialize(message);
            }
            catch (Exception e)
            {
                Console.WriteLine(e.Message);
            }

            try
            {
                // Create the command and set its properties.
                var command = new SqlCommand
                {
                    Connection = connection,
                    CommandText = "saveParsedMessageObject",
                    CommandType = CommandType.StoredProcedure
                };


                var messageJsonParm = new SqlParameter
                {
                    ParameterName = "@messageJson",
                    SqlDbType = SqlDbType.VarChar,
                    Direction = ParameterDirection.Input,
                    Value = messageJson,
                    Size = -1
                };
                _ = command.Parameters.Add(messageJsonParm);

                _ = command.ExecuteNonQuery();

                return true;
            }
            catch (Exception e)
            {
                Console.WriteLine( "SaveParsedMessageObject said:" + e.Message);
                return false;
            }
        }



        public static bool AreOutlookCategoriesEnabled(SqlConnection connection, ref CRMParsedMessageObject message)
        {

            try
            {
                // Create the command and set its properties.
                var command = new SqlCommand
                {
                    Connection = connection,
                    CommandText = "areOutlookCategoriesEnabled",
                    CommandType = CommandType.StoredProcedure
                };

                var OFFICEParm = new SqlParameter
                {
                    ParameterName = "@OFFICE",
                    SqlDbType = SqlDbType.SmallInt,
                    Direction = ParameterDirection.Input,
                    Value = message.OFFICE
                };
                _ = command.Parameters.Add(OFFICEParm);

                var categoriesEnabledParm = new SqlParameter
                {
                    ParameterName = "@categoriesEnabled",
                    SqlDbType = SqlDbType.Bit,
                    Direction = ParameterDirection.Output
                };
                _ = command.Parameters.Add(categoriesEnabledParm);

                _ = command.ExecuteNonQuery();

                return (bool) categoriesEnabledParm.Value;
            }
            catch (Exception e)
            {
                Console.WriteLine("AreOutlookCategoriesEnabled said:" + e.Message);
                return false;
            }
        }




        public static String GetConstituentFromEmailAddress(SqlConnection connection, short OFFICE, string emailAddress)
        {
            try
            {
                // Create the command and set its properties.
                var command = new SqlCommand
                {
                    Connection = connection,
                    CommandText = "getConstituentFromEmailAddress",
                    CommandType = CommandType.StoredProcedure
                };

                var OFFICEParm = new SqlParameter
                {
                    ParameterName = "@OFFICE",
                    SqlDbType = SqlDbType.SmallInt,
                    Direction = ParameterDirection.Input,
                    Value = OFFICE
                };
                _ = command.Parameters.Add(OFFICEParm);

                var emailAddressParm = new SqlParameter
                {
                    ParameterName = "@emailAddress",
                    SqlDbType = SqlDbType.VarChar,
                    Direction = ParameterDirection.Input,
                    Value = emailAddress
                };
                _ = command.Parameters.Add(emailAddressParm);

                var constituentIdParm = new SqlParameter
                {
                    ParameterName = "@constituentID",
                    SqlDbType = SqlDbType.BigInt,
                    Direction = ParameterDirection.Output
                };
                _ = command.Parameters.Add(constituentIdParm);

                _ = command.ExecuteNonQuery();
                if (constituentIdParm.Value.GetType().ToString() != "System.DBNull")
                {
                    return constituentIdParm.Value.ToString();
                }
                else
                {
                    Console.WriteLine("Null return from getConstituentFromEmailAddress");
                    return "0";
                }
            }
            catch (Exception e)
            {
                Console.WriteLine("GetConstituentFromEmailAddress said" + e.Message);
                return null;
            }
       }
    }
}
