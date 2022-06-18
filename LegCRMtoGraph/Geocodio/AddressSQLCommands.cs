using System;
using System.Data;
using System.Data.SqlClient;

namespace GeocodIo
{
    class AddressSQLCommands
    {
        public static bool CacheToAddress(SqlConnection connection)
        {
            try { 
                var command = new SqlCommand
                {
                    Connection = connection,
                    CommandText = "cacheToAddress",
                    CommandType = CommandType.StoredProcedure,
                    CommandTimeout=0 // no limit; this process is efficient, but may need to store a large number of records on startup
                };

                command.ExecuteNonQuery();
                return true;
            }
            catch (Exception e)
            {
                Console.WriteLine("CacheToAddress said:" + e.Message);
                return false;
            }
        }

        public static SqlDataReader CacheToGeocodIo(SqlConnection connection)
        {
            try { 
                var command = new SqlCommand
                {
                    Connection = connection,
                    CommandText = "cacheToGeocodIo",
                    CommandType = CommandType.StoredProcedure
                };
                using (command)
                {
                    SqlDataReader reader = command.ExecuteReader();
                    return reader;
                }
            }
            catch (Exception e)
            {
                Console.WriteLine("CacheToGeocodio said: " + e.Message);
                return null;
            }
        }

        public static bool CacheUpdateFromGeocodIo (SqlConnection connection, string jsonData )
        {
            try { 
                var command = new SqlCommand
                {
                    Connection = connection,
                    CommandText = "cacheUpdateFromGeocodIo",
                    CommandType = CommandType.StoredProcedure
                };

                var jsonDataParm = new SqlParameter
                {
                    ParameterName = "@jsonData",
                    SqlDbType = SqlDbType.NVarChar,
                    Direction = ParameterDirection.Input,
                    Value = jsonData
                };
                command.Parameters.Add(jsonDataParm);

                // do the updates
                command.ExecuteNonQuery();

                return true;
            }
            catch (Exception e)
            {
                Console.WriteLine("CacheUpdateFromGeocodIo said" + e.Message);
                return false;
            }
        }
    }
}
