using System;
using Microsoft.Identity.Client;
using Microsoft.Graph;
using Microsoft.Graph.Auth;
using Azure.Security.KeyVault.Secrets;
using Azure.Identity;
using System.Data.SqlClient;
using Microsoft.Extensions.Configuration;
using System.ComponentModel;

namespace LegCRMtoGraph
{
	public class SetAccessObjects : IDisposable
    {
        private bool _disposed = false;
        public IConfigurationRoot appConfig;
        private SecretClient vaultClient;

        public SetAccessObjects()
        {
            appConfig = LoadAppSettings();
            vaultClient = GetVaultClient();
        }

        /*
         * 
         *  Methods for settings
         * 
         */
        private IConfigurationRoot LoadAppSettings()
        {
            try
            {
                var config = new ConfigurationBuilder()
                                  .SetBasePath(System.IO.Directory.GetCurrentDirectory().Split('\\' + "bin" + '\\', 2)[0]) // back up from debug directory if in debugging mode
                                  .AddJsonFile("appSettings.json", false, true)
                                  .Build();
                if (
                    string.IsNullOrEmpty(config["clientIdName"]) ||
                    string.IsNullOrEmpty(config["clientSecretName"]) ||
                    string.IsNullOrEmpty(config["databaseName"]) ||
                    string.IsNullOrEmpty(config["passwordName"]) ||
                    string.IsNullOrEmpty(config["serverName"]) ||
                    string.IsNullOrEmpty(config["tenantIdName"]) ||
                    string.IsNullOrEmpty(config["userIDName"]) ||
                    string.IsNullOrEmpty(config["vaultURL"])
                  )

                {
                    Console.WriteLine("Check config json for missing values.");
                    return null;
                }
                return config;
            }
            catch (System.IO.FileNotFoundException)
            {
                Console.WriteLine("Unable to access configuration file.");
                return null;
            }
        }

        private SecretClient GetVaultClient()
        {
            try
            {
                // will Default Azure Credential work outside studio?
                var vclient = new SecretClient(vaultUri: new Uri(appConfig["vaultURL"]), credential: new DefaultAzureCredential());
                return vclient;
            } catch ( Exception e )
            {
                Console.WriteLine(e.Message);
                return null;
            }
        }

        /*
         * 
         * Methods for Graph Access
         * 
         */
        public GraphServiceClient GraphClient()
        {
            try
            {
                IConfidentialClientApplication confidentialClientApplication = BuildClientApplication();
                ClientCredentialProvider authProvider = new ClientCredentialProvider(confidentialClientApplication);
                GraphServiceClient graphClient = new GraphServiceClient(authProvider);
                return graphClient;
            }
            catch (Exception e)
            {
                Console.WriteLine(e.Message);
                return null;
            }
        }

        private IConfidentialClientApplication BuildClientApplication() 
        {
            try
            {
                // identifiers are those for client registration graph-access-test
                Azure.Security.KeyVault.Secrets.KeyVaultSecret clientId = vaultClient.GetSecret(appConfig["clientIDName"]);
                Azure.Security.KeyVault.Secrets.KeyVaultSecret tenantId = vaultClient.GetSecret(appConfig["tenantIdName"]);
                Azure.Security.KeyVault.Secrets.KeyVaultSecret clientSecret = vaultClient.GetSecret(appConfig["clientSecretName"]);

                // Create an authentication provider by passing in a client application and graph scopes.
                IConfidentialClientApplication confidentialClientApplication = ConfidentialClientApplicationBuilder
                    .Create(clientId.Value) //application client id from app registration
                    .WithTenantId(tenantId.Value)
                    .WithClientSecret(clientSecret.Value)
                    .Build();
                return confidentialClientApplication;
            }
            catch (Exception e)
            {
                Console.WriteLine(e.Message);
                return null;
            }
        }

        /*
         * 
         * Methods for SQL Access
         * 
         */

        public SqlConnection SqlConnection()
        {
            try
            {
                SqlConnection conn = new SqlConnection(BuildConnectionString());
                return conn;
            }
            catch (Exception e)
            {
                Console.WriteLine(e.Message);
                return null;
            }
        }


        private string BuildConnectionString() {
            try
            {
                string connectionString = GetConnectionString();
                SqlConnectionStringBuilder builder = new SqlConnectionStringBuilder(connectionString);
                KeyVaultSecret DataSource      = vaultClient.GetSecret(appConfig["ServerName"]);
                KeyVaultSecret UserID          = vaultClient.GetSecret(appConfig["UserIDName"]);
                KeyVaultSecret Password        = vaultClient.GetSecret(appConfig["PasswordName"]);
                KeyVaultSecret InitialCatalog  = vaultClient.GetSecret(appConfig["DatabaseName"]);
                if ("LOCAL" != appConfig["environment"])
                {
                    builder.DataSource = DataSource.Value;
                    builder.UserID = UserID.Value;
                    builder.Password = Password.Value;
                    builder.InitialCatalog = InitialCatalog.Value;
                } else
                {
                    builder.DataSource = "localhost";
                    builder.UserID = "test";
                    builder.Password = "test";
                    builder.InitialCatalog = "legcrm1";
                }
                return builder.ConnectionString;
            }
            catch (Exception e)
            {
                Console.WriteLine(e.Message);
                return null;
            }
        }

        private string GetConnectionString()
        {
            if ("LOCAL" != appConfig["environment"])
            {
                return "PersistSecurityInfo=False;MultipleActiveResultSets=True;ConnectRetryCount=2;" +
                "Encrypt=True;TrustServerCertificate=False;Connection Timeout=20;";
            } else
            {
                return "PersistSecurityInfo=False;MultipleActiveResultSets=True;ConnectRetryCount=2;" +
                "Encrypt=False;TrustServerCertificate=True;Connection Timeout=20;";
            }
        }

        public string GetGeocodIoApiKey()   
        {
            KeyVaultSecret key = this.vaultClient.GetSecret(appConfig["geocodIoApiKey"]);
            return key.Value;
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
                vaultClient = null;
                appConfig = null;
            }

            _disposed = true;
        }
    
    }
}
