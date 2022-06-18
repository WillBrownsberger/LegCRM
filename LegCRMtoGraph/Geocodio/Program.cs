using System;
using System.Data.SqlClient;
using System.Threading;
using System.Threading.Tasks;
//https://docs.microsoft.com/en-us/dotnet/framework/network-programming/making-asynchronous-requests
// https://github.com/snake-plissken/cSharpGeocodio/blob/master/README.md


namespace GeocodIo
{
    class Program
    {
        /*
         * 
         * This program loops through two tasks and waits to complete a one minute cycle if needed
         *      Most of the time, it will be waiting, but when addresses are bulk updated,  it will chug through them
         * 
         *  1. Check for constituent addresses that need to be geocoded or regeocoded
         *      If they exist in the cache, update from the cache
         *      If not, add them to the cache
         *  2. Look for addresses in the cache that need to be geocoded
         *      If present, start a new loop to 
         *          request updates 1000 at a time from geocodio
         *          await the response
         *          update the cache
         *          get more addresses until done geocoding the cache
         *          
         *  Run the outer loop every 120 seconds -- inexpensive (2 db calls) and provides quick update of addresses added from online
         *  
         *  Can shutdown gracefully by changing runStatus in json config to something other than go, but it is not important to shutdown gracefully
         *      -- all tasks are rerunnable/restartable
         *  
         *  Note that Geocodio can support batch requests of up to 10000, but this appears to push the limits
         *  of this code -- intermittent errors occur with batches of this size.  Reduced to 1000 for reliability.
         *  This is plenty efficient.  The code is already async, but could rewrite code to use stream read/write and that
         *  might allow 10000 -- not a priority, especially since this code will mostly be doing only a few at a time.
         */
        static async Task Main()
        {

            // variable checked from environment to force orderly shutdown -- not important to use this
            String runStatus = "GO";
            while ("GO" == runStatus)
            {
                // start loop time
                DateTime startInner = DateTime.Now;

                // check environment variables and refresh objects in each major loop
                var accessObjects = new LegCRMtoGraph.SetAccessObjects();
                runStatus = accessObjects.appConfig["runStatus"];
                using SqlConnection connection = accessObjects.SqlConnection();
                {
                    using GeocodIoHttp geoObject = new GeocodIoHttp(accessObjects); // create httpClient with API key
                    {
                        // open database connection
                        connection.Open();

                        // run first step proc -- straight sql
                        AddressSQLCommands.CacheToAddress(connection);

                        // load the addresses to be geocoded in the cache into a JSON string
                        while ( geoObject.GetAddresses(connection) > 0) { 

                            // if found a string of addresses needing updating, post them to geocodIo
                            string jsonForSave = await geoObject.MakeRequestAsync();

                            // send a single batch json response back to update the cache
                            if (string.Empty != jsonForSave)
                            {
                                AddressSQLCommands.CacheUpdateFromGeocodIo(connection, jsonForSave);
                            }

                        }

                        // rerun first step to assure that newly added addresses are updated in one cycle
                        AddressSQLCommands.CacheToAddress(connection);

                        connection.Close();

                        // at end of loop wait to complete a two minute cycle if necessary
                        int elapsed = (int)(DateTime.Now - startInner).TotalSeconds;
                        if (elapsed < 120)
                        {
                            Thread.Sleep(1000 * (120 - elapsed));
                        }
                    }
                }
            }
        }
    }
}