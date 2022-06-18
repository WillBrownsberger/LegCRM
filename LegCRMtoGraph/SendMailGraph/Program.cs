using System;
using System.Threading.Tasks;
using LegCRMtoGraph;
using System.Threading;

namespace SendMailGraph
{
    class Program
    {
        /*
         * 
         * This send loop enforces three layers of throttling for the send process.
         *   (1) Outer loop checks for GO status every two minutes when inner loop ends.
         *     This allows orderly shut down by changing GO status in JSON.
         *       Not critical to achieve orderly shut down.  
         *          Worst and very unlikely consequence of a random kill is a single duped outgoing message.
         * 
         *   (2)  Outer loop also sleeps for a throttleWait period if there is a bounced messages
         *      Stops ALL offices from sending for at at least one minute if Graph bounces a message
         *          Increases hold linearly up to five minutes for repeat bounces
         * 
         *   (3) Inner loop sleeps for wait period if nothing to do -- no messages found.
         *   
         *   *****NOTE*****
         *   Additional limits are enforced by the SQL Proc that looks for ready messages.
         *     (1) Max recipients per day as set in JSON config (10,000);
         *     (2) Max messages per minute as set in JSON config (30);
         *     (3) Additional throttle wait period on office that bounced a message -- 5 min or whatever greater amount set in JSON config
         *     See: https://docs.microsoft.com/en-us/office365/servicedescriptions/exchange-online-service-description/exchange-online-limits#receiving-and-sending-limits
         */



        static async Task Main()
        {

            String runStatus = "GO";
            int throttleWait = 0;
            while ("GO" == runStatus)
            {

                // outer sleep time driven only by graph Client response
                Thread.Sleep(throttleWait);

                // set up access to Graph and Database
                var accessObjects = new SetAccessObjects();
                var graphClient = accessObjects.GraphClient();
                var connection = accessObjects.SqlConnection();

                // check for possible orderly shutdown by changing JSON run status from GO
                runStatus = accessObjects.appConfig["runStatus"];

                // open database connection
                connection.Open();

                // open inner loop and send for 120 seconds before looping out to check Go status
                DateTime startInner = DateTime.Now;
                int nothingDoingWait = 0;
                while ((DateTime.Now - startInner).TotalSeconds < 120)
                {
                    // avoid database unnecessary calls 
                    Thread.Sleep(nothingDoingWait);
                    // check for a message and send it
                    short status = await SendMailGraph.GraphClientActions.SendNextMessageAsync(graphClient, connection);

                    // determine wait based on status
                    if ( 0 == status ) 
                    {
                        // 0 means a message was sent successfully -- no reason to impose wait; reset existing waits
                        throttleWait = 0;
                        nothingDoingWait = 0;
                    } else if ( 1 == status ) 
                    {
                        // no send attempted, because none ready: stay in this loop looping but sleep awhile
                        throttleWait = 0;
                        nothingDoingWait = Math.Min(120000, 2 * ( nothingDoingWait + 1000 ));
                    }
                    else if (20 == status)
                    {
                        // message specific error -- will hold message on sql side, but continue processing
                        throttleWait = 0;
                        nothingDoingWait = 0;
                    }
                    else if (30 == status)
                    {
                        // user specific authorization error -- will hold user on sql side, but continue processing 
                        //      will require LIS intervention to release (through office ui)
                        throttleWait = 0;
                        nothingDoingWait = 0;
                    }
                    else if (31 == status )
                    {
                        // user specific activity level error -- will throttle user on sql side, but continue processing 
                        throttleWait = 0;
                        nothingDoingWait = 0;
                    }
                    else if (40 == status) 
                    {
                        // Graph bounced message due to environmental problem or general authentication error
                        // hold everyone for escalating throttleWait period of up to 5 minutes; 
                        throttleWait = Math.Min(300000, 2 * (throttleWait + 1000));
                        break;
                    }
                    else if (50 == status)
                    {
                        /* design options for handling unanticipated errors
                         * + just keep trying: could result in bad message cycling and holding other users . . .
                         * + if treat as message errors, could put a lot of messages on hold status that are fine . . .
                         * + if treat as user authentication error and is not a user or message error, 
                         *    . . . all users could end up on held email status, but requiring manual reversal by LIS
                         * + if treat as user pacing error, user can retry after throttle interval; 
                         *    . . . no manual intervention required unless error persists
                         *    . . . take that approach on sql side -- treat a 50 as a 31 on sql side
                         */   

                    }
                }
                connection.Close();
            }
        }
    }
}
