using GeocodIo.JsonResultObjects;
using LegCRMtoGraph;
using System;
using System.Collections.Generic;
using System.Data.SqlClient;
using System.Net.Http;
using System.Text;
using System.Text.Json;
using System.Text.RegularExpressions;

namespace GeocodIo
{
    class GeocodIoHttp : IDisposable
    {

        // To detect redundant calls to dispose
        private bool _disposed = false;

        // disposable client for geocodio
        private readonly HttpClient client;

        // see constructor: version 1.6 hardcoded -- should review code before upgrading
        private Uri baseUri;

        // accumulators for addresses
        private short addressCount;
        private List<int> addressIds = new List<int>();
        private List<string> addressStrings = new List<string>();
        private List<GeocodIoResultExtractForSave> listForSave = new List<GeocodIoResultExtractForSave>();

        // JSON backer object for receiving JSON results from geocodIo
        private OuterResult jsonResultObject;

        // constructor -- takes config including api key
        public GeocodIoHttp(SetAccessObjects config)
        {
            baseUri = new Uri("https://api.geocod.io/v1.6/geocode?api_key=" + config.GetGeocodIoApiKey() + "&limit=1");
            client = new HttpClient
            {
                Timeout = TimeSpan.FromMinutes(3) // wants 600 seconds to geocode 10000 addresses; batching 1000, 3 s/b plenty
            };
        }

        // step 1 -- load addresses for geocoding
        public int GetAddresses(SqlConnection connection)
        {
            // reset list objects, since may loop
            addressCount = 0;
            addressIds.Clear();
            addressStrings.Clear();
            listForSave.Clear();

            // get the reader and read
            SqlDataReader addressReader = AddressSQLCommands.CacheToGeocodIo(connection);
            while (addressReader.Read())
            {
                // save ids in order in list to be reassociated with addresses as they return
                addressIds.Add(addressReader.GetInt32(0));

                // save address after stripping possibly difficult characters
                var address_query = StripNonJson(addressReader.GetString(1));
                addressStrings.Add(address_query);

                // count the addresses
                addressCount++;
            }

            addressReader.Close();
            return addressCount;
        }

        // step 2 -- geocode the addresses
        public async System.Threading.Tasks.Task<string> MakeRequestAsync()
        {
            // encode the address list as a json array of strings
            string serializedList = JsonSerializer.Serialize(addressStrings);
            var data = new StringContent(serializedList, Encoding.UTF8, "application/json");

            // post the request to GeocodIo
            HttpResponseMessage response;
            try
            {
                response = await client.PostAsync(baseUri, data);
                response.EnsureSuccessStatusCode();
            }
            catch (Exception e)
            {
                Console.WriteLine(e.Message);
                return string.Empty;
            }

            // deserialize the response as an object backed by definitions JsonResultObjects namespace
            var options = new JsonSerializerOptions
            {
                PropertyNameCaseInsensitive = true,
            };
            string result = response.Content.ReadAsStringAsync().Result;
            jsonResultObject = JsonSerializer.Deserialize<OuterResult>(result,options);

            // loop through the enumerated id list and the result array within the outer result
            // these are "guaranteed" to be in same order
            for (int i = 0; i < addressCount; i++)
            {
                // break if somehow array is less than the right length, but GeocodIo says this is "guaranteed" not to be true
                if ( i >= jsonResultObject.Results.Length )
                {
                    break;
                }

                // create new saveObject
                GeocodIoResultExtractForSave saveObject = new GeocodIoResultExtractForSave();

                // address geocoding results worth using if accuracy > .8; in MA, should have rooftop data from MassGIS
                // https://www.geocod.io/guides/accuracy-types-scores/
                if (
                    ( jsonResultObject.Results[i].Response.Results.Length > 0 ) && // assuming this gets evaluated first, preventing errors on following
                    ( jsonResultObject.Results[i].Response.Results[0].Accuracy >= .8 ) &&
                    // following condition probably equivalent to != "place"
                    ( jsonResultObject.Results[i].Response.Results[0].Accuracy_Type == "rooftop" ||
                      jsonResultObject.Results[i].Response.Results[0].Accuracy_Type == "range_interpolation" ||
                      jsonResultObject.Results[i].Response.Results[0].Accuracy_Type == "nearest_rooftop_match")
                )
                {
                    saveObject.cache_ID                         = addressIds[i];
                    saveObject.lat                              = jsonResultObject.Results[i].Response.Results[0].Location.Lat;
                    saveObject.lon                              = jsonResultObject.Results[i].Response.Results[0].Location.Lng;
                    saveObject.zip_clean                        = jsonResultObject.Results[i].Response.Results[0].Address_Components.Zip;
                    saveObject.matched_clean_address            = jsonResultObject.Results[i].Response.Results[0].Formatted_Address;
                    saveObject.geocode_vendor_accuracy          = jsonResultObject.Results[i].Response.Results[0].Accuracy;
                    saveObject.geocode_vendor_accuracy_type     = jsonResultObject.Results[i].Response.Results[0].Accuracy_Type;
                    saveObject.geocode_vendor_source            = jsonResultObject.Results[i].Response.Results[0].Source;
                }
                else
                {
                    saveObject.cache_ID                         = addressIds[i];
                    saveObject.lat                              = 99;
                    saveObject.lon                              = 999;
                    saveObject.zip_clean                        = string.Empty;
                    if (jsonResultObject.Results[i].Response.Results.Length > 0)
                    {
                        saveObject.matched_clean_address        = jsonResultObject.Results[i].Response.Results[0].Formatted_Address;
                        saveObject.geocode_vendor_accuracy      = jsonResultObject.Results[i].Response.Results[0].Accuracy;
                        saveObject.geocode_vendor_accuracy_type = jsonResultObject.Results[i].Response.Results[0].Accuracy_Type;
                        saveObject.geocode_vendor_source        = jsonResultObject.Results[i].Response.Results[0].Source;
                    } else
                    {
                        saveObject.matched_clean_address        = string.Empty;
                        saveObject.geocode_vendor_accuracy      = 0;
                        saveObject.geocode_vendor_accuracy_type = "error";
                        saveObject.geocode_vendor_source        = "error";
                    }
                }

                listForSave.Add(saveObject);
            }
          
            return JsonSerializer.Serialize (listForSave);
        }

        public string StripNonJson(String input)
        {
            // assure that bad characters do not challenge the json encoding or the geocoder itself
            // https://www.json.org/json-en.html
            string stepOne = Regex.Replace(input, "\"", "'");
            string stepTwo = Regex.Replace(stepOne, "[^A-Za-z0-9,.#& '-]+", " ");
            return stepTwo;
        }

        public void Dispose()
        // https://docs.microsoft.com/en-us/dotnet/standard/garbage-collection/implementing-dispose
        {
            // Dispose of unmanaged resources.
            Dispose(true);
            // Suppress finalization.
            GC.SuppressFinalize(this);
        }

        protected virtual void Dispose(bool disposing)
        {
            if (_disposed)
            {
                return;
            }
            if (disposing)
            {
                client.Dispose();
                baseUri = null;
                addressIds = null;
                addressStrings = null;
                jsonResultObject = null;
                listForSave = null;
            }

            _disposed = true;
        }
    }
}
