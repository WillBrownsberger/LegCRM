<?php
/*
* class-wic-db-sqlsrv.php
*
* access object for sql server --  instantiated once as global $sqlsrv variable;
* connection persists through the whole of each application transaction; 
* supports repeated database transactions through the same connection, but only one result set active at a time (flushed each query);
*
* uses SQLSRV driver of the Microsoft Drivers for PHP for SQL Server.
* https://docs.microsoft.com/en-us/sql/connect/php/sqlsrv-driver-api-reference?view=sql-server-ver15
*
*
* usage for $sqlsrv->query method: expects valid parameters for sqlsrv->query 
*
* NOTE: ALL SELECT COLUMNS MUST INCLUDE A slug TYPE NAME TO BE USABLE IN THE FETCHED OBJECTS
*   sqlsrv_fetch_object fails silently without column names that are suitable for php object properties
*
*/
class WIC_DB_SQLSRV {

    // is the connection up and running (tested only on instantiation)
    public $ready = false;
   
    // the persistent connection handle
    private $conn = false;
   
    // only populated on selects
    public $last_result = false;
   
    // only populated on inserts
    public $insert_id = false;

    // do not rely on has_rows -- have not completely understood behavior
    
    // only populated on selects -- always populated with 0 in the rows branch
    // or a positive integer if actually have rows
    public $num_rows;
    
    // for updates, means rows examined, not necessarily rows changed
    // for deletes, rows deleted
    // for inserts, rows inserted
    public $rows_affected = false;
    
    // array ($query, $args)
    public $last_query = false;
    
    // expose last error message as multi-line string
    public $last_error; 
    
    // accessible boolean query success indicator
    public $success = false;
    
    // resource created by query or prepare
    private $active_statement = false;

    // populated from Azure to populate security proc parms
    private $user_email = false;

    // populated by stack trace
    private $calling_class = false;

    // populated by stack trace
    private $calling_method = false;

    /*
    *
    * configure sqlsrv and open connection
    *
    */
    public function __construct() {

        // connect
        if ( ! $this->connect() ) {
            $this->log_db_error();
        } else {
            $this->ready = true;
        };
        
    }

    /*
    *
    * connects using either windows or azure active directory authentication
    *
    * for development use windows authentication; for beta/production use azure authentication
    * https://docs.microsoft.com/en-us/sql/connect/php/azure-active-directory?view=sql-server-ver15
    *
    */
    private function connect() {

        // accepting defaults on other settings https://docs.microsoft.com/en-us/sql/connect/php/connection-options?view=sql-server-ver15
        $connection_options = array(
            'CharacterSet'           => 'UTF-8', // using utf-8 collation in database available in server 19
            'ConnectRetryCount'      => 2, // applies to broken connection, not new connection
            'Database'               => APP_SQLSRV_NAME,
            "Encrypt"                => SQL_ENCRYPT, 
            'LoginTimeout'           => 20, // default is indefinite wait; time to fail the connection attempt
            'MultipleActiveResultSets' => false, // don't need this: https://docs.microsoft.com/en-us/sql/relational-databases/native-client/features/using-multiple-active-result-sets-mars?view=sql-server-ver15
            'ReturnDatesAsStrings'   => true, // not the default; to minimize code compatibility issues vs mysql
            "UID"                    => APP_SQLSRV_UID, 
            "pwd"                    => APP_SQLSRV_PSWD, 
        );
       

        // set up retry loop for transient errors on initial connection, 
        // following https://docs.microsoft.com/en-us/sql/connect/php/step-4-connect-resiliently-to-sql-with-php?view=sql-server-ver15 
        $maxCountTriesConnectAndQuery = 3;  
        $this->conn = NULL;
        $arrayOfTransientErrors = array('08001', '08002', '08003', '08004', '08007', '08S01');  // https://docs.microsoft.com/en-us/sql/odbc/reference/appendixes/appendix-a-odbc-error-codes?view=sql-server-ver15

        for ($cc = 1; $cc <= $maxCountTriesConnectAndQuery; $cc++) {  

            $this->conn = sqlsrv_connect( APP_SQLSRV_HOST, $connection_options ); 

            // done if success
            if ( $this->conn ) { 
                return true; 
            // else check error code
            } else {    
                $isTransientError = false;  
                $errorCode = '';
                if ( ($errors = sqlsrv_errors() ) != NULL ) {
                    foreach ( $errors as $error ) {  
                        $errorCode = $error['code']; // the sqlserv error code
                        $isTransientError = in_array($errorCode, $arrayOfTransientErrors);
                        if ( $isTransientError ) {
                            break;
                        }
                    }
                }  
                if ( !$isTransientError ) { 
                    return false;
                }  
                // just q quick pause before trying again
                sleep( 1 );  
            }  
        }  
    }

    //
    private function who_is_asking() {
        $this->user_email = get_azure_user_direct();
        $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)[2];
        $this->calling_class = $caller['class'] ?? 'Global_Function';
        $this->calling_method = $caller['function'];
        if ( ERROR_LOG_QUERIES ) {
            $now = date('Y-m-d H:i:s');
            error_log  ( "AT $now user {$this->user_email} called sqlsrv->query through  {$this->calling_class}::{$this->calling_method}");
        }
    }

    private function log_db_error() {

        if( ($errors = sqlsrv_errors( SQLSRV_ERR_ALL ) ) != null) { 
            $error_message = "----------------------------------------------------\nSQLSRV call failed with " . 
            count($errors) . " error" . (count($errors) > 1 ? 's' : '') . ".\n";
            $counter = 1;
            foreach( $errors as $error) {  
                $error_message .= "Error $counter:\n";  
                $error_message .= "\t SQLSTATE: " . $error[ 'SQLSTATE'] . "\n";  
                $error_message .= "\t code: " . $error[ 'code'] ."\n";  
                $error_message .= "\t message: ".$error[ 'message']."\n";  
            }  
        } else {
            $error_message = "Unexplained database error\n";
        }
        if ( $this->last_query ) {
            array_walk( $this->last_query[1], 'show_values');
            $error_message .= "Last query was: {$this->last_query[0]}\n" . print_r( $this->last_query[1], true ) . "\n" . wic_generate_call_trace(2) ;
        }
        error_log ( $error_message );
        $this->last_error = $error_message;

    }

    private function log_class_error( $error ) {
        array_walk( $this->last_query[1], 'show_values');
        error_log ( "$error -- query was : {$this->last_query[0]}.\n" . print_r( $this->last_query[1], true ) . "\n" . wic_generate_call_trace(2) );
        $this->last_error = $error;
    }

    // clear all properties and the current active statement (but not the connection)
    private function flush() {
        
        $this->last_result = false;
        $this->insert_id = false;
        $this->num_rows = false;
        $this->rows_affected = false;
        $this->last_query = false;
        $this->last_error = false;
        $this->success = false;
        if ( $this->active_statement ) {
            sqlsrv_free_stmt( $this->active_statement );
            $this->active_statement = false;
        }
    }

    // flush resources and close the connection
    public function close() {
        $this->flush();
        if ( $this->conn ) {
            sqlsrv_close ( $this->conn );
        }
        $this->conn = false;
    }

    // accepts a parametrized query -- wrapper for sqlserv->query
    public function query( $query, $args, $rows_on = false ) {
        /*
        *
        */
        $this->who_is_asking();
        /* returns
        *       false on error
        *       result array of row objects on select
        *       insert_id on insert
        *       rows_affected on update or delete
        *       true on any other usage that is successful
        *
        *       $rows_on passed to get client side buffer results
        *       statements beginning WITH or EXEC which may not be recognized as selects
        */
        // flush previous statement and outcome indicators
        $this->flush();
        // expose last query for error reporting
        $this->last_query = array( $query, $args );
        /*
        * cannot entirely rely on sqlsrv error checking
        *   -- no $args passed => php Too few arguments
        *   -- non array passed => php fail on expected arg type
        *   -- $args with too many values will not fail -- allowing bugs
        */
        $query_variables_count = substr_count( $query, '?');
        $offered_substitutions_count = count( $args );
        if ( $query_variables_count !=  $offered_substitutions_count ) {
            $this->log_class_error ( "Bad call to WIC_DB_SQLSRV::Query -- mismatched argument count. \n" .
            "Query arg count was $query_variables_count and offered substitutions count was $offered_substitutions_count.") ;
            return false;
        }

        // what kind of query is this?
        $query = trim($query, "; \t\n\r\0\x0B"); // including trim of a final terminator
        $first_six = strtoupper( substr( $query,0,6) );
        $is_insert = 'INSERT' == $first_six;
        $is_delete = 'DELETE' == $first_six;
        $is_update = 'UPDATE' == $first_six;
        $is_select = 'SELECT' == $first_six;

        // does the query have any embedded delimiters?  belt and suspenders -- prohibit this even though parametrized
        // https://docs.microsoft.com/en-us/sql/relational-databases/security/sql-injection?view=sql-server-ver15
       $bad_chars = array();
        if ( 0 !== preg_match ("#(;|--|/\*|\*/|xp_)#", $query, $bad_chars ) ) {
            $this->log_class_error ( "Bad call to WIC_DB_SQLSRV::Query embedded prohibited delimiter: {$bad_chars[0]}.") ;
          return false;
        }

        // in case of an insert, add a supplementary query to return last insert id
        if ( $is_insert ) {
            $query .= "; SELECT SCOPE_IDENTITY();";
        }

        // set query options
        $options = array(
            'QueryTimeout' => 300 // seconds to timeout
        );
        /*
        * would like to add client side buffering on selects (only for selects, because interferes with rows affected reporting for updates)
        *
        * SQLSRV_CURSOR_CLIENT_BUFFERED worked fine in local testing, but in azure ANY *valid* specification of Scrollable caused "Invalid cursor state" error.  
        *   (an invalid random string passed for Scrollable does also generate an error, but the appropriate invalid option error).
        *
        *Nothing on web about this,  maybe something unique, but let it go for now b/c default forward cursor fetch time seems pretty good.
        *
        * if( $is_select || $rows_on ) {
        *    $options['Scrollable'] = SQLSRV_CURSOR_CLIENT_BUFFERED;
        *    $options['ClientBufferMaxKBSize'] = 65536; // default is 10240 (KB) 
        * }
        * See https://docs.microsoft.com/en-us/sql/connect/php/cursor-types-sqlsrv-driver?view=sql-server-ver15 for cursor types
        *
        */

        // do query and handle return reporting
        $start_time = microtime(true); 
        if ( ! $this->active_statement = sqlsrv_query ( $this->conn, $query, $args, $options ) ) {
            $this->log_db_error();
            return false;            
        } else { 
            if ( ERROR_LOG_QUERIES ) {
                error_log ( "QUERY elapsed time was:" . ( microtime(true) - $start_time ));
            }
            // global indicator
            $this->success = true;
            // set reporting correctly
            if ( $is_insert ) {  //  num_rows  remain false as flushed
                // first result set (available without next_result) is the result of insert; get rows affected from this
                // this result set does not include last insert id as a field available after fetch
                $this->rows_affected = sqlsrv_rows_affected( $this->active_statement );
                // move to next result to get at the results of the select scope_identity
                sqlsrv_next_result( $this->active_statement );
                // make the scope_identity result available for reading
                sqlsrv_fetch( $this->active_statement );
                $this->insert_id = sqlsrv_get_field( $this->active_statement, 0 );
                return $this->insert_id;
            } elseif ( $is_update || $is_delete ) { // insert_id,  num_rows remain false as flushed
                $this->rows_affected = sqlsrv_rows_affected( $this->active_statement);
                return $this->rows_affected;
            } 
            // enter this branch for any statement that has rows 
            if ( 
                sqlsrv_has_rows ($this->active_statement)
               // or any statement we thought would have rows and might be looking for an array result, poss empty
               || $is_select || $rows_on
               ) 
               {
               // always return at least an empty array in the has or should have rows scenario
               $this->last_result = array(); 
               $this->num_rows = 0;
               /*
               *
               * if failed to ask for or get buffered results, sqlsrv_num_rows( $this->active_statement) may be zero falsely
               * so always compute it manually from retrieved rows --  
               *
               */
               $start_time2 = microtime(true); 
               while ( $row = sqlsrv_fetch_object( $this->active_statement ) ) {
                   $this->last_result[] = $row;
                   $this->num_rows++;
               }
               if ( ERROR_LOG_QUERIES ) {
                    error_log ( "FETCH elapsed time was:" . ( microtime(true) - $start_time2 ) .
                        " and total access time was: " . ( microtime(true) - $start_time ) );
               }
               return $this->last_result; // false if none, otherwise row array
            } else {
                // no reporting for other statements
                return true;
            }
        }
    }
}