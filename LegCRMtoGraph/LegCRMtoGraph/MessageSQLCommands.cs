/*
 * EXAMPLE REFERENCES
 * https://docs.microsoft.com/en-us/dotnet/framework/data/adonet/commands-and-parameters
 * 
 * 
 */
using Microsoft.Graph;
using Newtonsoft.Json;
using System;
using System.Data;
using System.Data.SqlClient; // https://docs.microsoft.com/en-us/dotnet/api/system.data.sqlclient?view=dotnet-plat-ext-3.1

namespace LegCRMtoGraph
{
    public class MessageSQLCommands
    {
        public static long GetNowStamp(SqlConnection connection)
        {
            // Create the command and set its properties.
            var command = new SqlCommand
            {
                Connection = connection,
                CommandText = "getNowStamp",
                CommandType = CommandType.StoredProcedure
            };

            var nowStampParm = new SqlParameter
            {
                ParameterName = "@nowStamp",
                SqlDbType = SqlDbType.BigInt,
                Direction = ParameterDirection.Output
            };
            command.Parameters.Add(nowStampParm);

            command.ExecuteNonQuery();
            if (nowStampParm.Value.GetType().ToString() != "System.DBNull")
            {
                return (long)nowStampParm.Value;
            }
            else
            {
                Console.WriteLine("Null return from get now stamp.");
                return 0;
            }
        }

        public static long SaveNewGraphMessage(
            SqlConnection connection, 
            short OFFICE, 
            string office_email, 
            Message message, 
            string message_id, 
            Boolean startup, 
            long nowStamp, 
            bool hasAttachments 
        )
        {
            try
            {
                // Create the command and set its properties.
                var command = new SqlCommand
                {
                    Connection = connection,
                    CommandText = "saveNewGraphMessage",
                    CommandType = CommandType.StoredProcedure
                };

                var OFFICEParm = new SqlParameter
                {
                    ParameterName = "@OFFICE",
                    SqlDbType = SqlDbType.SmallInt,
                    Direction = ParameterDirection.Input,
                    Value = OFFICE
                };
                command.Parameters.Add(OFFICEParm);

                var office_emailParm = new SqlParameter
                {
                    ParameterName = "@office_email",
                    SqlDbType = SqlDbType.NVarChar,
                    Direction = ParameterDirection.Input,
                    Value = office_email
                };
                command.Parameters.Add(office_emailParm);

                var messageParm = new SqlParameter
                {
                    ParameterName = "@message",
                    SqlDbType = SqlDbType.NVarChar,
                    Direction = ParameterDirection.Input,
                    Value = JsonConvert.SerializeObject(message)
                };
                command.Parameters.Add(messageParm);

                var message_idParm = new SqlParameter
                {
                    ParameterName = "@message_id",
                    SqlDbType = SqlDbType.NVarChar,
                    Direction = ParameterDirection.Input,
                    Value = message_id
                };
                command.Parameters.Add(message_idParm);

                var startupParm = new SqlParameter
                {
                    ParameterName = "@startup",
                    SqlDbType = SqlDbType.Bit,
                    Direction = ParameterDirection.Input,
                    Value = startup
                };
                command.Parameters.Add(startupParm);

                var nowStampParm = new SqlParameter
                {
                    ParameterName = "@now_sync_stamp",
                    SqlDbType = SqlDbType.BigInt,
                    Direction = ParameterDirection.Input,
                    Value = nowStamp
                };
                command.Parameters.Add(nowStampParm);

                var hasAttachmentsParm = new SqlParameter
                {
                    ParameterName = "@has_attachments",
                    SqlDbType = SqlDbType.Bit,
                    Direction = ParameterDirection.Input,
                    Value = hasAttachments
                };
                command.Parameters.Add(hasAttachmentsParm);


                var insertedMessageIDParm = new SqlParameter
                {
                    ParameterName = "@insertedMessageID",
                    SqlDbType = SqlDbType.BigInt,
                    Direction = ParameterDirection.Output
                };
                command.Parameters.Add(insertedMessageIDParm);

                // execute the insert
                command.ExecuteNonQuery();
                if (insertedMessageIDParm.Value.GetType().ToString() != "System.DBNull")
                {
                    return (long)insertedMessageIDParm.Value;
                }
                else
                {
                    Console.WriteLine("Null return from message insert.");
                    return 0;
                }


            }
            catch (Exception e)
            {
                Console.WriteLine(e.Message);
                return 0;
            }
        } // function

        public static bool MarkNoLongerOnServer(SqlConnection connection, short OFFICE, string office_email, long nowStamp )
        {
            try
            {
                // Create the command and set its properties.
                var command = new SqlCommand
                {
                    Connection = connection,
                    CommandText = "markNoLongerOnServer",
                    CommandType = CommandType.StoredProcedure
                };

                // Add the input parameters and their properties, one by one
                var OFFICEParm = new SqlParameter
                {
                    ParameterName = "@OFFICE",
                    SqlDbType = SqlDbType.SmallInt,
                    Direction = ParameterDirection.Input,
                    Value = OFFICE
                };
                command.Parameters.Add(OFFICEParm);

                var office_emailParm = new SqlParameter
                {
                    ParameterName = "@office_email",
                    SqlDbType = SqlDbType.NVarChar,
                    Direction = ParameterDirection.Input,
                    Value = office_email
                };
                command.Parameters.Add(office_emailParm);

                var nowStampParm = new SqlParameter
                {
                    ParameterName = "@now_sync_stamp",
                    SqlDbType = SqlDbType.BigInt,
                    Direction = ParameterDirection.Input,
                    Value = nowStamp
                };
                command.Parameters.Add(nowStampParm);

                // execute the proc
                command.ExecuteNonQuery();

                return true;
            }
            catch (Exception e)
            {
                Console.WriteLine(e.Message);
                return false;
            }
        } // function markNoLongerOnServer

        public static bool MarkSingleNoLongerOnServer(SqlConnection connection, short OFFICE, string office_email, string message_id)
        {
            try
            {
                // Create the command and set its properties.
                var command = new SqlCommand
                {
                    Connection = connection,
                    CommandText = "markSingleNoLongerOnServer",
                    CommandType = CommandType.StoredProcedure
                };


                // Add the input parameters and their properties, one by one
                var OFFICEParm = new SqlParameter
                {
                    ParameterName = "@OFFICE",
                    SqlDbType = SqlDbType.SmallInt,
                    Direction = ParameterDirection.Input,
                    Value = OFFICE
                };
                command.Parameters.Add(OFFICEParm);

                var office_emailParm = new SqlParameter
                {
                    ParameterName = "@office_email",
                    SqlDbType = SqlDbType.NVarChar,
                    Direction = ParameterDirection.Input,
                    Value = office_email
                };
                command.Parameters.Add(office_emailParm);

                var message_idParm = new SqlParameter
                {
                    ParameterName = "@message_id",
                    SqlDbType = SqlDbType.NVarChar,
                    Direction = ParameterDirection.Input,
                    Value = message_id
                };
                command.Parameters.Add(message_idParm);


                // execute the proc
                command.ExecuteNonQuery();

                return true;
            }
            catch (Exception e)
            {
                Console.WriteLine(e.Message);
                return false;
            }
        } // function
        
        public static SqlDataReader GetMessagesToBeDeleted( SqlConnection connection, short OFFICE, string office_email )
        {
            // Create the command and set its properties.
            var command = new SqlCommand
            {
                Connection = connection,
                CommandText = "getMessagesToBeDeleted",
                CommandType = CommandType.StoredProcedure
            };
            // Add the input parameters and their properties, one by one
            var OFFICEParm = new SqlParameter
            {
                ParameterName = "@OFFICE",
                SqlDbType = SqlDbType.SmallInt,
                Direction = ParameterDirection.Input,
                Value = OFFICE
            };
            command.Parameters.Add(OFFICEParm);

            var office_emailParm = new SqlParameter
            {
                ParameterName = "@office_email",
                SqlDbType = SqlDbType.NVarChar,
                Direction = ParameterDirection.Input,
                Value = office_email
            };
            command.Parameters.Add(office_emailParm);
            using (command)
            {
                SqlDataReader reader = command.ExecuteReader();
                return reader;
            }
        } // function


        public static SqlDataReader GetMessagesWithAttachments(SqlConnection connection, short OFFICE)
        {
            try 
            { 
                // Create the command and set its properties.
                var command = new SqlCommand
                {
                    Connection = connection,
                    CommandText = "getMessagesWithAttachments",
                    CommandType = CommandType.StoredProcedure
                };

                // Add the input parameters and their properties, one by one
                var OFFICEParm = new SqlParameter
                {
                    ParameterName = "@OFFICE",
                    SqlDbType = SqlDbType.SmallInt,
                    Direction = ParameterDirection.Input,
                    Value = OFFICE
                };
                command.Parameters.Add(OFFICEParm);

                using (command)
                {
                    SqlDataReader reader = command.ExecuteReader();
                    return reader;
                }
            }
            catch (Exception e)
            {
                Console.WriteLine(e.Message);
                return null;
            }
        } // function



        public static SqlDataReader GetOffices(SqlConnection connection)
        {
            // Create the command and set its properties.
            var command = new SqlCommand
            {
                Connection = connection,
                CommandText = "getOffices",
                CommandType = CommandType.StoredProcedure
            };

            using (command)
            {
                SqlDataReader reader = command.ExecuteReader();
                return reader;
            }
        } // function

        public static bool MarkMessageAsWithoutAttachment ( SqlConnection connection, long WithoutAttachment )
        {
            try { 
                var command = new SqlCommand
                {
                    Connection = connection,
                    CommandText = "markMessageAsWithoutAttachment",
                    CommandType = CommandType.StoredProcedure
                };

                // inbox_image ID
                var message_idParm = new SqlParameter
                {
                    ParameterName = "@message_id",
                    SqlDbType = SqlDbType.BigInt,
                    Direction = ParameterDirection.Input,
                    Value = WithoutAttachment
                };
                command.Parameters.Add(message_idParm);

                // execute the proc
                command.ExecuteNonQuery();

                return true;
            }
            catch (Exception e)
            {
                Console.WriteLine(e.Message);
                return false;
            }
        }



        public static bool SaveNewMessageAttachment(SqlConnection connection, short OFFICE, long withAttachment, ref Attachment a)
        {

            try
            {
                // Create the command and set its properties.
                var command = new SqlCommand
                {
                    Connection = connection,
                    CommandText = "saveNewMessageAttachment",
                    CommandType = CommandType.StoredProcedure
                };

                // Add the input parameters and their properties, one by one
                var OFFICEParm = new SqlParameter
                {
                    ParameterName = "@OFFICE",
                    SqlDbType = SqlDbType.SmallInt,
                    Direction = ParameterDirection.Input,
                    Value = OFFICE
                };
                command.Parameters.Add(OFFICEParm);

                var message_idParm = new SqlParameter
                {
                    ParameterName = "@message_id",
                    SqlDbType = SqlDbType.BigInt,
                    Direction = ParameterDirection.Input,
                    Value = withAttachment
                };
                command.Parameters.Add(message_idParm);

                var attachmentParm = new SqlParameter
                {
                    ParameterName = "@attachment",
                    SqlDbType = SqlDbType.VarChar,
                    Direction = ParameterDirection.Input,
                    Value = JsonConvert.SerializeObject(a)
                };
                command.Parameters.Add(attachmentParm);

                var messageInOutboxParm = new SqlParameter
                {
                    ParameterName = "@message_in_outbox",
                    SqlDbType = SqlDbType.Bit,
                    Direction = ParameterDirection.Input,
                    Value = 0
                };
                command.Parameters.Add(messageInOutboxParm);


                // execute the proc
                command.ExecuteNonQuery();

                return true;
            }
            catch (Exception e)
            {
                Console.WriteLine(e.Message);
                return false;
            }

        }

        public static bool StashDeltaToken(SqlConnection connection, short OFFICE, string deltaToken)
        {
            try
            {
                // Create the command and set its properties.
                var command = new SqlCommand
                {
                    Connection = connection,
                    CommandText = "stashDeltaToken",
                    CommandType = CommandType.StoredProcedure
                };


                // Add the input parameters and their properties, one by one
                var OFFICEParm = new SqlParameter
                {
                    ParameterName = "@OFFICE",
                    SqlDbType = SqlDbType.SmallInt,
                    Direction = ParameterDirection.Input,
                    Value = OFFICE
                };
                command.Parameters.Add(OFFICEParm);

                var deltaTokenParm = new SqlParameter
                {
                    ParameterName = "@deltaToken",
                    SqlDbType = SqlDbType.NVarChar,
                    Direction = ParameterDirection.Input,
                    Value = deltaToken
                };
                command.Parameters.Add(deltaTokenParm);

                // execute the proc
                _ = command.ExecuteNonQuery();

                return true;
            }
            catch (Exception e)
            {
                Console.WriteLine(e.Message);
                return false;
            }
        } // function

        public static bool LogReceiveErrorEvent(SqlConnection connection, short office, string requestId, string errorCode)
        {
            try
            {

                var accessObjects = new SetAccessObjects();

                // Create the command and set its properties.
                var command = new SqlCommand
                {
                    Connection = connection,
                    CommandText = "logReceiveErrorEvent",
                    CommandType = CommandType.StoredProcedure
                };

                // Add the input parameters and their properties, one by one
                var officeParm = new SqlParameter
                {
                    ParameterName = "@office",
                    SqlDbType = SqlDbType.SmallInt,
                    Direction = ParameterDirection.Input,
                    Value = office
                };
                command.Parameters.Add(officeParm);

                // no request id available on attachment add
                if (String.IsNullOrEmpty(requestId))
                {
                    requestId = "Unavailable";
                }

                var requestIdParm = new SqlParameter
                {
                    ParameterName = "@requestId",
                    SqlDbType = SqlDbType.NVarChar,
                    Direction = ParameterDirection.Input,
                    Value = requestId
                };
                command.Parameters.Add(requestIdParm);

                var errorCodeParm = new SqlParameter
                {
                    ParameterName = "@errorCode",
                    SqlDbType = SqlDbType.NVarChar,
                    Direction = ParameterDirection.Input,
                    Value = errorCode
                };
                command.Parameters.Add(errorCodeParm);

                // execute the update
                command.ExecuteNonQuery();
                return true;
            }
            catch (Exception e)
            {
                Console.WriteLine(e.Message);
                return false;
            }
        } // function
    } // class
} // namespace