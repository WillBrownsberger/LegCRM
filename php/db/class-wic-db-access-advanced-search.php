<?php
/*
*
* class-wic-db-access-advanced-search.php
*
* COMPLEX QUERY USING DYNAMICALLY CREATED SQL 
* 	For security, all variable query elements are either 
*	+	object identifiers, operators or modifiers that are screened against valid lists
*	+	free form user supplied, but parametrized in final $summary_sql to protect against injection
* 
* The advanced search form creates four row types -- constituent, activity and constituent_having.
*	There is an entity for each row type as well as for the whole form (similar to the structure of the constituent form)
*	For each row type, can have arbitrarily many rows
*	Each row is a WHERE or HAVING term and Rows/terms are combined according to selected combination values
*	
*		Row Type	type	entity-field	comparison	value aggregator 
*		Constituent	YES		YES				YES			YES		NO		
*		Activity	YES		YES				YES			YES		NO		
*		Issue		NO		YES				YES			YES		NO
*		Having		NO		YES (limited)	YES			YES		YES		
*	
*	ON form submit, entity-parent reviews the $_POST and picks out array elements that match its valid field list and creates the data object array.
*	The data object array is an array of control objects each of which has a sanitization function appropriate for its field.
*	Then entity parent calls sanitize on each control in that array before formatting them into advanced_search child entity's assemble_meta_query_array.
*	Then it goes to database object factory and creates this object and submits the array which is processed here to create the actual query.
*
*	SANITIZATION FUNCTIONS FOR EACH ADVANCED SEARCH FIELD ASSURE NON-INJECTION FROM THE INCOMING ROW ARRAY AS FOLLOWS
*	  +	type -- coerced to letters_and_numbers, no spaces, not more than 20 characters -- and not SQL reserved words; 
*			(is both user defined in options interface and user selected here but sanitized on creation and resanitized here on submit)
*	  +	entity-field -- hard validated against the dictionary; coerced to valid field id for row
*	  +	comparison -- hard validated against hard_coded comparison options coerced to = if doubt
*	  +	value -- sanitized to utf8 no tags, NOT validated -- IS parametrized in the function
*	  +	aggregator -- hard validated against static list
*	
*	FIELDS AND/OR FOR ROW COMBINATION SELECTIONS HARD VALIDATED AGAINST STATIC LISTS
*	  + "special search parameters" coerced to common values if not on list by sanitization functions for fields in top level entity
*	
*	TOP LEVEL SEARCH PARAMETERS EVALUATED/SANITIZED HERE
*
*	GENERAL MODEL OF SQL GENERATED: 
*		Construct to produce unique list of ID's for retrieved entity type: Issue, Activity or Constituent
*		Must sort at this stage to allow pagination
*		In some cases place results in temp table, in others wrap the out put with a lister template
*
*	WHERE clause always includes limitation to user office
*
*	$select_mode CONTROLS WHAT RETRIEVED
*		FOR DOWNLOAD, the result is temp table of ID's 
*		FOR ID, the result is a page of ID's with summary properties
*		FOR LIST, the result is a page of complete records for display with summary properties
*/

class WIC_DB_Access_Advanced_Search Extends WIC_DB_Access {

	// public properties
	public $amount_total;
	public $financial_activities_in_results;
	public $entity_retrieved;	
	public $advanced_search = 'yes'; // flag used to preserve original identity as advanced search while spoofing constituent or activity search
	public $blank_rows_ignored = 0;
	public $share_name = ''; 
	
	// single public function
	public function search( $meta_query_array, $search_parameters ) { 
		
		
		global $sqlsrv;
		$office = get_office(); 

		/*
		* extract only needed search parameters from $search_parameters (which may include other parameters that will be passed through to search log)
		*/
		$select_mode 		= isset ( $search_parameters['select_mode'] ) ? strtoupper($search_parameters['select_mode']) : 'ID'; // alt is download or list; if invalid mode, not output
		$sort_order 		= isset ( $search_parameters['sort_order'] ) ? $search_parameters['sort_order'] : true; // could pass false; default
		$list_page_offset	= isset ( $search_parameters['list_page_offset'] ) ? $search_parameters['list_page_offset'] : 0;
		$retrieve_limit 	= isset ( $search_parameters['retrieve_limit'] ) ? $search_parameters['retrieve_limit'] : 100;
		$this->share_name 	= isset ( $search_parameters['share_name'] ) ? $search_parameters['share_name'] :''; // display only
		// validate search parameters that will be embedded
		if ( !is_int( $retrieve_limit ) || $retrieve_limit < 1 ) {
			$retrieve_limit = 50;
		}
		if ( !is_int( $list_page_offset ) || $list_page_offset < 0 ) {
			$list_page_offset = 0;
		}

		/*
		* now extract top level working values from the meta_query_array; 
		*
		* meta query array includes two types of elements
		*	special parameters that govern row combination -- find constituents or activities? and/or across clauses?
		*	row arrays each of which translates to a single where or having term
		*		
		* default special search parameters appear below 
		*/
		$primary_search_entity 			= 'constituent';  		// constituent, issue or activity
		$activity_and_or				= 'and'; 			// and, or, and not, or not
		$constituent_and_or				= 'and'; 			// and, or, and not, or not
		$constituent_having_and_or		= 'and'; 			// and, or, and not, or not
		// array or rows to be assembled into terms
		$query_clause_rows = array();
		// extract working values from the meta_query_array; only further use of the array itself is to log to the search log 
		foreach ( $meta_query_array as $populated_field ) {
			
			if ( isset ( $populated_field['table'] ) ) { // extract special search parameters
				${$populated_field['key']} = $populated_field['value'];	// override default special search parameters	
			} else { // extract row arrays
				$query_clause_rows[] = $populated_field[1]; // [1] is the row array of query clauses
			}		
		} 
		// populate entity property
		$this->entity_retrieved = $primary_search_entity; // hard sanitized in top WIC_ENTITY_ADVANCED_SEARCH

		/* 
		*
		* Now proceed to assemble the clauses of the query
		*
		*
		*
		* first, set up order by (doing early b/c need values in next if/else)
		*/
		$sort_clause 	= $sort_order === true ? $this->get_sort_order_for_entity( $primary_search_entity ) : '';
		$order_clause 	= ( '' == $sort_clause ) ? '' : " ORDER BY $sort_clause ";

		/*
		* set up base select and the tables needed to support it 
		*
		* select list depends on primary entity and select_mode, not on metaquery
		*
		* primary prepare_list_clauses and the metaquery can both contribute to the $table_array used to constitute the join
		*
		*/
		if ( 'LIST' == $select_mode ) {
			$clauses = WIC_DB_Access_WIC::prepare_list_clauses( $primary_search_entity );
			extract ( $clauses ); 
			/*
			* extract yields:
			*	$select_list -- final subject to addition of aggregate terms
			*	$join -- not used here and overriden immediately
			*	$table_array -- starting point for accumulation of tables
			*/
			$join = " $primary_search_entity ";
			$group_by = "GROUP BY {$this->get_group_by_string_for_entity( $primary_search_entity)}";
			if ( 'activity' == $primary_search_entity ) {
				$list_support_query = true;  // further below, in case of activity list, need to add constituent and issue tables
				// since activity is a subordinate entity, primary entity search rules do not yield all needed fields
				$activity_extra_fields = ', last_name, first_name, post_title ';
				$group_by 		.= $activity_extra_fields;
				$select_list 	.= $activity_extra_fields;
			}

		} else {
			$select_list 	= " $primary_search_entity.id AS ID";
			$join 			= " $primary_search_entity ";
			$table_array = array(); // array of tables that will be identified below and added to the join 
			// add any sort terms to the group by, but strip directionals
			$group_by 		= " GROUP BY $primary_search_entity.ID " . 
				(
				 ( '' == $sort_clause ) ? 
					 '' : 
					 ", " .  preg_replace ( '/(ASC|DESC)/i', '', $sort_clause )
				);
			$list_support_query = false;
		}

		/*
		*
		* set up limit clause -- only depends on select mode
		*
		* also set up temporary table clause
		*/
		if ( in_array( $select_mode, array ('LIST', 'ID' ) ) ) {
			$limit_phrase = " OFFSET $list_page_offset ROWS FETCH NEXT $retrieve_limit ROWS ONLY";
			$temp_table_string = '';
		} else {
			$temp_table = WIC_List_Constituent_Export::temporary_id_list_table_name();	
			$temp_table_string = " INTO $temp_table ";
			$limit_phrase = '';
		}

		/*
		*
		* prepare stubs to be built out in loop
		*
		* this is effectively the map of the variable elements of the sql
		*
		*/

		// stubs for conditional clauses
		$constituent_where 		= '';
		$activity_where			= '';
		$issue_where			= '';
		$having 				= ''; 
		// count of conditions in each
		$constituent_where_count = 0;
		$activity_where_count 	 = 0; 
		$issue_where_count 		 = 0; 
		$having_count 			 = 0; 
		// parametrized values for each condition
		$constituent_values = array();
		$issue_values		= array();
		$activity_values 	= array(); 
		$having_values 		= array();
		// phrases for conjunction of conditions
		$constituent_not 	= strpos ( $constituent_and_or, 'NOT' ) > 0 ? ' NOT ' : '' ;
		$issue_not 			= strpos ( $issue_and_or, 'NOT' ) > 0 ? ' NOT ' : '' ;
		$activity_not 		= strpos ( $activity_and_or, 'NOT' ) > 0 ? ' NOT ' : '' ; 
		$having_not 		= strpos ( $constituent_having_and_or, 'NOT' ) > 0 ? 'NOT' : ''; 
		// additional terms carried in having clause searches to capture aggregates
		$additional_select_terms = '';
		$additional_select_terms_values = array();

		/*
		*
		*  parse the query_clause_rows into strings and value arrays 
		*
		*  outer loop is down the rows; inner loop is across
		*
		* 
		*/
		foreach ( $query_clause_rows as $query_clause_row ) { 

			// reset all terms for the start of each row
			$field_name 		= ''; // inner loop should always set
			$table 				= ''; // inner loop should always set
			$compare 			= ''; // inner loop should always set
			$type				= '';
			$value 				= ''; // need not be set (e.g "DOES NOT EXIST" is the compare term)
			$aggregator			= '';
			$blank_search_valid = false; // will not be set in inner loop -- may be set in compare switch
			$value_variable 	= '?'; // will not be set in inner loop -- may be set in compare switch
			$type_filter 		= ''; // will not be set in inner loop -- may be set in row logic

			// process the row, setting elements sanitized in assembly
			$row_type = substr( $query_clause_row[0]['table'], 16 );
			foreach ( $query_clause_row as $query_clause_item ) { 
				if ( $row_type . '_field' == $query_clause_item['key'] ) {
					$field = WIC_Entity_Advanced_Search::get_field_rules_by_id( $query_clause_item['value'] );
					$field_name		= $field['field_slug'];
					$table 			= $field['entity_slug'];
				} elseif ( $row_type . '_comparison' == $query_clause_item['key']  ) {
					$compare = $query_clause_item['value']; 				
				} elseif ( $row_type . '_value' == $query_clause_item['key']  ) { 
					$value = $query_clause_item['value'] ; 
				} elseif ( $row_type . '_entity_type' == $query_clause_item['key'] ||  $row_type . '_type' == $query_clause_item['key'] ) { // different naming for activity and constituent 
					$type =  $query_clause_item['value']; 
				} elseif ( $row_type . '_aggregator' == $query_clause_item['key']  ) { 
					$aggregator =  $query_clause_item['value']; 
				}														
			}
			
			// accumulate tables across rows, not repeating	
			if( ! in_array( $table, $table_array ) ) {
				$table_array[] = $table;			
			}
			
			// interpret certain compare modes
			switch ( $compare ) {
				case 'CONTAINS':
					$value = self::compose_fulltext_search_from_phrase( $value );
					break;
				case 'SCAN':
					$value   = '%' . $value . '%' ; 
					$compare = 'LIKE';
					break;
				case 'LIKE':
					$value =  $value . '%'	;		
					break;
				case 'BLANK':
				case 'NOT_BLANK';
					$value = '';
					$blank_search_valid = true;
					$compare = 'BLANK' == $compare ? '=' : '>' ;
					break;
				case 'IS_NULL';
					$value_variable = ''; // nothing to insert in search clause
					$blank_search_valid = true;
					$compare = ' IS NULL ';
					break;
				default:					
				// no interpretation imposed: $value = $value and $value_variable = ?;			
			} 

			

			/*
			* BUILD A WHERE CLAUSE FROM THE ELEMENTS OF THE ROW
			*	
			*/
			// begin by preparing for special handling for when field is a type field (only possible for activity and constituent rows)
			//	normally, value is separate from  type, but if user selects type as search field, they are the same
			//	if type is set for a non-type field, the single row can create a combination where clause 
			//  . . . for example, "( activity_type = 'email' AND activity_date > 2020-01-01 )"
			$is_a_type_field = in_array ( $field_name, array ( 'activity_type', 'phone_type', 'email_type', 'address_type' ) ); 			
			if ( $is_a_type_field ) {
				$value = $type;
				$compare = '=';
			}
			// now proceed to assemble the where clause 
			if ( 
					// rescreening row_type
					( 
						'activity'    == $row_type || 
						'constituent' == $row_type ||
						'issue'		  == $row_type 
					) && 
					// skipping rows where no value set except for certain compare terms
					( '' < $value || $blank_search_valid ) 
				) {
				// where connector written so full clause will read, for example, " NOT X AND NOT Y AND NOT Z . . ."
				$where_connecter = ${ $row_type . '_where_count' } > 0 ? ${ $row_type . '_and_or' } : ${ $row_type . '_not' };
				// fully qualify field name
				$field_name = $table . '.' . $field_name;
				// do the special handling for $type fields
				if ( ! $is_a_type_field && '' < $type ) { // not a type field and type not blank, so set additional type clause
					$type_name = $table . '.' . $table . '_type';
					$type_filter = " $type_name = ? AND ";
					${ $row_type  . '_values'}[] = $type;
				}
				if ( '?' == $value_variable ) { 
					${ $row_type  . '_values'}[] = $value; 
				} 
				// here the build of where term with a slot for variable and a value in the array; poss extra slot/value ofor type
				if ( 'CONTAINS' == $compare) {
					${ $row_type . '_where' } .= " $where_connecter ( $type_filter CONTAINS($field_name, ? ) )";
				} else {
					${ $row_type . '_where' } .= " $where_connecter ( $type_filter $field_name $compare $value_variable ) ";
				}				
				${ $row_type . '_where_count' }++;
			/*
			* OR BUILD A HAVING CLAUSE . . . 
			*/
			} elseif ( 
				'constituent_having' == $row_type && 
				// no having clause for activity search; having applies to the rollup of activity
				('constituent' == $primary_search_entity || 'issue' == $primary_search_entity ) && 
				// in all cases disregarding blank value -- but 0 is OK; 0 > ''; // php 8 makes this true; 
				'' < $value 
				) { 
				// determine having connector
				$having_connecter = $having_count > 0 ? $constituent_having_and_or : $having_not;
				// combine strings to make having condition
				$having .= " $having_connecter $aggregator" . "( activity.$field_name ) $compare ? ";
				$having_values[] = $value;
				$having_count++;
				// add a select term so that aggregation result can be included in download
				$additional_select_terms .= ", $aggregator" . "( activity.$field_name ) as " . $aggregator . '_' . $having_count;
			} else {
			// set flag for top entity messaging
				$this->blank_rows_ignored++;
			}	 
		} // close loop over rows
		
		// wrap the where clauses
		$constituent_where 	=  $constituent_where 	? 'AND ( ' . $constituent_where . ') ' : '';		
		$activity_where 	=  $activity_where 		? 'AND ( ' . $activity_where 	. ') ' : '';
		$issue_where 		=  $issue_where			? 'AND ( ' . $issue_where 		. ') ' : '';		

		/*
		* WHAT TABLES ARE NEEDED IN THE JOIN?
		*
		* DO ONLY THE NECESSARY JOINS
		*
		*/
		$need_constituent 	= false;
		$need_activity 		= false;
		$need_issue 		= false;
		$details_join = '';
		// pass $table_array 
		$valid_table_list = array( 'constituent', 'activity', 'issue', 'address', 'email' ,'phone');
		$where_base_details = '';
		foreach ( $table_array as $table ) {
			if ( !in_array ( $table, $valid_table_list ) ) {
				Throw new Exception( sprintf("Invalid \$table value: |%s|", $table));
			}
			if ( $table != 'constituent' && $table != 'activity' && $table != 'issue' ) {
				$need_constituent = true;
				$details_join .= " LEFT JOIN $table on $table.constituent_id = constituent.ID ";
				$where_base_details .= " AND ( $table.OFFICE = $office OR $table.OFFICE is null )";
			} else {
				${'need_' . $table} = true;
			}
		}

		// only logically necessary to build in limitation by office from the primary entity, which will limit all other,
		// but including office limitation for all accessed tables supports better performance
		$where_base 	= " WHERE ( $primary_search_entity.office = $office ";
		$where_constituent_office = " AND ( constituent.office = $office OR constituent.office IS NULL ) ";
		$where_activity_office = " AND ( activity.office = $office OR activity.office IS NULL ) ";
		$where_issue_office = " AND ( issue.office = $office OR issue.office IS NULL ) ";
		// may need to use the strings
		$join_constituent 	= " LEFT JOIN constituent on activity.constituent_id = constituent.ID ";
		$join_issue			= " LEFT JOIN issue on issue.ID = activity.issue ";
		// started with primary entity as $join base above the loop -- now add the other major entitites as needed
		if ( 'constituent' == $primary_search_entity ) {
			if ( $need_issue || $need_activity ) {
				$join .= " LEFT JOIN activity on activity.constituent_id = constituent.ID ";
				$where_base .= $where_activity_office;
			} 
			if ( $need_issue ) {
				$join .= $join_issue;
				$where_base .= $where_issue_office;
			}
		} elseif ( 'activity' == $primary_search_entity ) {
			if ( $need_issue || $list_support_query ) {
				$join .= $join_issue;
				$where_base .= $where_issue_office;
			}
			if ( $need_constituent || $list_support_query ) {
				$join .= $join_constituent;
				$where_base .= $where_constituent_office;
			} 
		} elseif ( 'issue' == $primary_search_entity ) {
			if ( $need_constituent || $need_activity ) {
				$join .= "LEFT JOIN activity on activity.issue = issue.ID ";
				$where_base .= $where_activity_office;
			} 
			if ( $need_constituent ) {
				$join .= $join_constituent;
				$where_base .= $where_constituent_office;
			}
		}
		// complete where base phrase
		$where_base .= " $where_base_details ) ";

		// in all cases add the details if any
		$join .= $details_join;

		// complete the having clause
		$having = ( $having_count > 0 ) ? 'HAVING ' . $having : '';
		
		// merge prepare values 
		$values = array_merge ( $additional_select_terms_values, $constituent_values, $activity_values, $issue_values, $having_values );

		/*
		* at this point, have assembled each element of the following query
		*
		* deriving each object name or operator from known terms
		*
		* loading array of user supplied values for parametrized search
		*/
		$sql = "
				SELECT $select_list $additional_select_terms
				$temp_table_string
				FROM $join
				$where_base 
					$constituent_where 
					$activity_where 
					$issue_where 
				$group_by
				$having
				$order_clause
				$limit_phrase
				";
		
		// go ahead and execute
		$this->result = $sqlsrv->query ( $sql, $values );
		$this->sql = $sql; // final retrieval sql
		$this->showing_count =  $sqlsrv->num_rows;
		$this->outcome = $sqlsrv->success; 

		// not downloading, so returning to form -- get summary values
		if ( $this->outcome && ( 'LIST' == $select_mode || 'ID' == $select_mode ) ){
			// prepare amount total if activity search
			if ( 'activity' == $primary_search_entity ) {
				$financial_type_flag = $this->set_financial_type_flag();
			
				$summary_select = 
				"
					WITH base_query AS (
						SELECT activity.ID, activity_amount $financial_type_flag
						FROM $join
						$where_base 
						$constituent_where 
						$activity_where 
						$issue_where 
						$group_by
						$having
						)
					SELECT count(*) as found_count, sum(activity_amount) as total_amount, sum(includes_financial_types) as includes_financial_types from base_query 
					";
				
				$result = $sqlsrv->query($summary_select, $values, true);
				// save results
				$this->found_count 						= $result[0]->found_count;
				$this->amount_total 					= $result[0]->total_amount;
				$this->financial_activities_in_results 	= $result[0]->includes_financial_types > 0 ? true : false;
			// just count for issues or constituents
			} else {
				$summary_select =  
				"
				WITH base_query AS (
					SELECT $primary_search_entity.ID
					FROM $join
					$where_base 
					$constituent_where 
					$activity_where 
					$issue_where 
					$group_by
					$having
					)
				SELECT count(*) as found_count from base_query 
				";
				$result = $sqlsrv->query($summary_select, $values, true);
				$this->found_count = $result[0]->found_count; // need to support only in constituent queries; activities check downstream;
				// set value to say whether found_count is known
				$this->found_count_real = true; // always looking for full found count

			}
			
			$this->outcome = $sqlsrv->success; // only in this branch if first attempt was true;
			$this->retrieve_limit 	= $retrieve_limit;
			$this->list_page_offset = $list_page_offset;
			$this->explanation = ''; 
		}
	
		
		// log the search 
		$this->search_id = WIC_Entity_Search_Log::search_log ( 
			array( 
				'meta_query_array' 	=> $meta_query_array, 
				'search_parameters' => $search_parameters, 
				'found_count'		=> $this->found_count, 
			)
		);	
		
	}	


	// create at term to tell receiving queries to show amount totals -- just checking for included financial activity types
	private function set_financial_type_flag() {

		// prepare 'IN' phrase
		$in_phrase = ', 0 as includes_financial_types '; // below, will give this a value > 0 if (a) they are set up and (b) there are some found;			
		$financial_activity_type_array = WIC_Entity_Activity::$financial_types ;
		$formatted_financial_activity_type_string = '';			
		if ( '' != $financial_activity_type_array ) {
			foreach ( $financial_activity_type_array as $type ) {
				$formatted_financial_activity_type_string .= ('\'' . $type . '\',');  								
			}
			$formatted_financial_activity_type_string = rtrim( $formatted_financial_activity_type_string, ',' );
			// setting flag in result set to tell activity list whether to show totals
			if ( $formatted_financial_activity_type_string ) {	 
				$in_phrase = ", iif(activity_type IN (" . $formatted_financial_activity_type_string . "),1,0) as includes_financial_types ";	
			}
		}
		
		return ( $in_phrase );		
	}

	public function compose_fulltext_search_from_phrase ( $value ) {
		$term_array = preg_split( '#[\s\p{P}]+#', $value ); 
		// for some reason, PREG_SPLIT_NO_EMPTY flag yields unexpected results, so do it separate step
		$non_empty = array();
		foreach ( $term_array as $term )  {
			if ( $term > '') $non_empty[] = $term;
		}
		$term_string = implode( ',', $non_empty );
		if(0 == count($non_empty) ) {
			return 'zxzxzx'; // something to avoid syntax error
		} elseif ( 1 == count ( $non_empty ) ) {
			return $term_string;
		} else {
			return 'NEAR((' . $term_string . '),' . max(10, 2 * count($term_array) ) . ')';
		}
	}
}

