using System;
using System.Threading.Tasks;
using System.Data.SqlClient;
using System.Threading;

namespace LegCRMtoGraph
{
    class Program
    {
        static async Task Main()
        {

            /*
             * This process is designed to be run as a continuously running web job.
             * It will cycle NOT more frequently than every two minutes.
             * 
             * Timed sync may be the right model here, as opposed to getting push notifications of change.
             *      The sync is in both directions.  On the other hand, could implement reverse synch by trigger in database to post to the same URL as push.
             *      So, it is possible that push notifications could be implemented effectively.  For a future round of development.
             * 
             */
            String runStatus = "GO";
            while ("GO" == runStatus)
            {
                DateTime startRun = DateTime.Now;
                //Console.WriteLine(startRun.ToString() + "Start Time");

                // set up access to Graph and Database
                var accessObjects = new SetAccessObjects();
                var graphClient = accessObjects.GraphClient();
                var connection = accessObjects.SqlConnection();
                // check for possible shutdown
                runStatus = accessObjects.appConfig["runStatus"];
                // open database connection
                connection.Open();

                // start loop through offices
                SqlDataReader officesReader = MessageSQLCommands.GetOffices(connection);
                while (officesReader.Read())
                {

                    try
                    {
                        // synchronize folder
                        var synchProcess = GraphClientActions.SynchMailFolder(
                            graphClient,
                            connection,
                            officesReader.GetInt16(0), // office ID
                            officesReader.GetString(1), // office_email
                            officesReader.GetString(2) // office_last_delta_token
                            );
                        await synchProcess;


                    }
                    catch (Exception e)
                    {
                        Console.WriteLine(e.ToString());
                    }
                }

                // cleanup
                officesReader.Close();
                connection.Close();

                // wait to make the loop two minutes minimum
                DateTime endRun = DateTime.Now;
                TimeSpan timeSpan = (endRun - startRun);
                var elapsed = (int)timeSpan.TotalSeconds;
                if (elapsed < 120)
                {
                    int cycleDelay = 120 - elapsed;
                    Thread.Sleep(1000 * cycleDelay);
                }
            }
        }
    }
}
