/*
 * 
 */
using System;
using System.Data;
using System.Data.SqlClient;
using LegCRMtoGraph;
using System.Text.Json;

namespace SendMailGraph
{
    public class MessageSQLCommands
    {

        public static SqlDataReader GetNextQueuedOutgoing(SqlConnection connection)
        {
            try
            {

                var accessObjects = new SetAccessObjects();

                // Create the command and set its properties.
                var command = new SqlCommand
                {
                    Connection = connection,
                    CommandText = "getNextQueuedOutgoing",
                    CommandType = CommandType.StoredProcedure
                };

                // Add the input parameters and their properties, one by one
                var maxMessagesPerMinuteParm = new SqlParameter
                {
                    ParameterName = "@maxMessagesPerMinute",
                    SqlDbType = SqlDbType.SmallInt,
                    Direction = ParameterDirection.Input,
                    Value = accessObjects.appConfig["maxMessagesPerMinute"]
                };
                command.Parameters.Add(maxMessagesPerMinuteParm);

                var maxRecipientsPerDayParm = new SqlParameter
                {
                    ParameterName = "@maxRecipientsPerDay",
                    SqlDbType = SqlDbType.NVarChar,
                    Direction = ParameterDirection.Input,
                    Value = accessObjects.appConfig["maxRecipientsPerDay"]
                };
                command.Parameters.Add(maxRecipientsPerDayParm);

                // get the next message ID and object
                SqlDataReader reader = command.ExecuteReader();
                return reader;
            }
            catch (Exception e)
            {
                Console.WriteLine(e.Message);
                return null;
            }
        } // function

        public static bool QueueOutgoing( SqlConnection connection, short Office, CRMMessageObject message )
        {
            string outgoingMessageJson = JsonSerializer.Serialize(message);

            try
            {
                // Create the command and set its properties.
                var command = new SqlCommand
                {
                    Connection = connection,
                    CommandText = "queueOutgoing",
                    CommandType = CommandType.StoredProcedure
                };

                var OFFICEParm = new SqlParameter
                {
                    ParameterName = "@OFFICE",
                    SqlDbType = SqlDbType.SmallInt,
                    Direction = ParameterDirection.Input,
                    Value = Office
                };
                command.Parameters.Add(OFFICEParm);

                var outgoingMessageJsonParm = new SqlParameter
                {
                    ParameterName = "@outgoingMessageJson",
                    SqlDbType = SqlDbType.NVarChar,
                    Direction = ParameterDirection.Input,
                    Value = outgoingMessageJson
                };
                command.Parameters.Add(outgoingMessageJsonParm);

                command.ExecuteNonQuery();
                return true;
            }
            catch (Exception e)
            {
                Console.WriteLine(e.Message);
                return false;
            }

        }

        public static bool UpdateMessageQueueWithSendResults(SqlConnection connection, long outboxID, string requestId, string errorCode, short handleHow )
        {
            try
            {

                var accessObjects = new SetAccessObjects();

                // Create the command and set its properties.
                var command = new SqlCommand
                {
                    Connection = connection,
                    CommandText = "updateMessageQueueWithSendResults",
                    CommandType = CommandType.StoredProcedure
                };

                // Add the input parameters and their properties, one by one
                var outboxIDParm = new SqlParameter
                {
                    ParameterName = "@outboxID",
                    SqlDbType = SqlDbType.BigInt,
                    Direction = ParameterDirection.Input,
                    Value = outboxID
                };
                command.Parameters.Add(outboxIDParm);

                var throttleIntervalParm = new SqlParameter
                {
                    ParameterName = "@throttleInterval",
                    SqlDbType = SqlDbType.BigInt,
                    Direction = ParameterDirection.Input,
                    Value = accessObjects.appConfig["throttleInterval"]
                };
                command.Parameters.Add(throttleIntervalParm);

                // no request id available on attachment add
                if(String.IsNullOrEmpty(requestId) )
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

                var handleHowParm = new SqlParameter
                {
                    ParameterName = "@handleHow",
                    SqlDbType = SqlDbType.SmallInt,
                    Direction = ParameterDirection.Input,
                    Value = handleHow
                };
                command.Parameters.Add(handleHowParm);

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


        public static SqlDataReader GetOutgoingAttachments( SqlConnection connection, long outboxID )
        {
            try
            {

                // Create the command and set its properties.
                var command = new SqlCommand
                {
                    Connection = connection,
                    CommandText = "getOutgoingAttachments",
                    CommandType = CommandType.StoredProcedure
                };

                // Add the input parameters and their properties, one by one
                var outboxIDParm = new SqlParameter
                {
                    ParameterName = "@outboxID",
                    SqlDbType = SqlDbType.BigInt,
                    Direction = ParameterDirection.Input,
                    Value = outboxID
                };
                command.Parameters.Add(outboxIDParm);

                // execute the insert
                SqlDataReader reader = command.ExecuteReader();
                return reader;
            }
            catch (Exception e)
            {
                Console.WriteLine(e.Message);
                return null;
            }
        } // function

    } // class
} // namespace