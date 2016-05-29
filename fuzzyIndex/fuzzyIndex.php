<?php
mb_internal_encoding('UTF-8'); 
/**
* @author Philipp Strazny <philipp at strazny dot com>
* @copyleft (l) 2012  Philipp Strazny
* @package FuzzyIndex
* @file
* @since 2012-01
* @license http://opensource.org/licenses/lgpl-license.php GNU Lesser General Public License
* 
* 2012-06-16:
* added foreign keys in $sqlCreateSnippetLocationsTable
* added $sqlEnableForeignKeys
* added $sqlDeleteLocation
* added call to $sqlEnableForeignKeys in setDatabasehandle()
* added delete_location()
*/

/**
* @class FuzzyIndex
*/
class FuzzyIndex{
	
	protected $dbh;
	protected $heuristic;
	protected $dbfile;

	// sql
	protected $sqlCreateLocationsTable = 'CREATE TABLE IF NOT EXISTS locations (locationid INTEGER PRIMARY KEY AUTOINCREMENT, location TEXT UNIQUE, snippetcount INTEGER, strlen INTEGER)'; //mysql needs STRING instead of TEXT  
	protected $sqlCreateSnippetsTable = 'CREATE TABLE IF NOT EXISTS snippets (snippetid INTEGER PRIMARY KEY AUTOINCREMENT, snippet TEXT UNIQUE)'; //mysql needs STRING instead of TEXT 
	protected $sqlCreateSnippetLocationsTable = 'CREATE TABLE IF NOT EXISTS snippet_locations (snippetid INTEGER REFERENCES snippets ON DELETE CASCADE, locationid INTEGER REFERENCES locations ON DELETE CASCADE, UNIQUE(snippetid, locationid))'; 
	protected $sqlEnableForeignKeys = 'PRAGMA foreign_keys = ON';
	protected $sqlDeleteLocation = 'DELETE FROM locations WHERE locationid = ?';
	protected $sqlInsertLocations = 'INSERT OR IGNORE INTO locations VALUES( NULL,?,?,? )';
	protected $sqlInsertSnippetLocations = 'INSERT OR IGNORE INTO snippet_locations VALUES( ?,? )';
	protected $sqlInsertSnippet = 'INSERT OR IGNORE INTO snippets VALUES( NULL, ? )'; //:snippet )';
	protected $sqlSelectLocationid = 'SELECT locationid FROM locations WHERE location = ?';
	protected $sqlSelectSnippetid = 'SELECT snippetid FROM snippets WHERE snippet = ?';
	//{numsnippets}
	//{strlen}
	//{minlen}
	//{maxlen}
	//{sqlsnippets}
	//{threshold}
	protected $sqlSelectBestMatches = 'SELECT locid, score, location FROM (  SELECT locid,   (hits*100/{numsnippets} + hits*100/snippetcount + (({strlen}- abs({strlen}-strlen))*100)/{strlen})/3 AS score,    location    FROM (  SELECT COUNT(snippet_locations.locationid) AS hits, snippet_locations.locationid AS locid, locations.snippetcount, locations.strlen, locations.location    FROM locations, snippets, snippet_locations   WHERE locations.strlen >= {minlen}   AND locations.strlen <= {maxlen}   AND snippet_locations.locationid = locations.locationid   AND snippet_locations.snippetid = snippets.snippetid AND snippets.snippet IN ({sqlsnippets})   GROUP BY locid  ) as tmp1 ) as tmp2 WHERE score > {threshold}   ORDER BY score DESC  LIMIT 5';

	protected $preparedStatements;

	/**
	 * opens database and initializes if needed
	 */ 
	public function __construct($dbfile){
		if ( empty($dbfile) || !preg_match('/[a-z]/i', basename($dbfile))){
				throw new InvalidArgumentException("invalid dbfile");		
		}
		//print __LINE__.' '.$dbfile."\n";
		$this->dbfile = $dbfile;
		$init= !$this->dbInitialized();
		//print "init: $init\n";
		$this->setDatabaseHandle();
		if ( $init ){
			$this->init();
		}
		$this->heuristic = new CharsHeuristic();
		$this->setupPreparedStatements();
	}
	/**
	 * destroys database handle
	 */
	public function __destruct(){
		$this->dbh=null;
	}
	/**
	 * get size of sqlite database
	 * @return size of database file 
	 */
	public function getDBSize(){
		return filesize($this->dbfile);
	}
	
	/**
	 * sets heuristic for internal use
	 */
	public function setHeuristic($heuristic){
		if ( !class_exists($heuristic)){
			throw new InvalidArgumentException("bad heuristic: $heuristic");
		}
		$this->heuristic = new $heuristic;
	}
	/**
	 * @return the internally used heuristic
	 */
	public function getHeuristic(){
		return $this->heuristic;
	}
	
	/**
	 * @return class name of internal heuristic
	 */
	public function getHeuristicName(){
		return get_class($this->heuristic);
	}

	private function dbInitialized(){
		return file_exists($this->dbfile);	
	}
	
	/**
	 * e.g.: sqlite:FILE
	 */ 
	public function setDatabaseHandle(){
		$this->dbh = new PDO('sqlite:'.$this->dbfile);
		$this->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		// foreign keys are turned off by default in sqlite, so they
		// must be turned on for each connection:
		$this->dbh->query($this->sqlEnableForeignKeys); 
	}
	/**
	 * sets up needed tables
	 */ 
	public function init(){
		//print __FILE__." in init\n";
		$this->dbh->query($this->sqlCreateLocationsTable); 
		$this->dbh->query($this->sqlCreateSnippetsTable); 
		$this->dbh->query($this->sqlCreateSnippetLocationsTable); 
	}
	
	/**
	 *  sets up cache of prepared statements for relevant sql statements
	 */ 
	public function setupPreparedStatements(){
		$ps = array();
		$ps['sqlInsertLocations'] = $this->dbh->prepare($this->sqlInsertLocations);
		$ps['sqlInsertSnippetLocations'] = $this->dbh->prepare($this->sqlInsertSnippetLocations);
		$ps['sqlInsertSnippet'] = $this->dbh->prepare($this->sqlInsertSnippet);
		$ps['sqlSelectLocationid'] = $this->dbh->prepare($this->sqlSelectLocationid);
		$ps['sqlSelectSnippetid'] = $this->dbh->prepare($this->sqlSelectSnippetid);	
		$ps['sqlDeleteLocation'] = $this->dbh->prepare($this->sqlDeleteLocation);	
		$this->preparedStatements = $ps;
	}
	
	/**
	 * inserts valpairs into specified table
	 * returns rowid or false if insert failed
	 */ 
	private function insert_snippet_location_vals($vals){
		$rowid=false;
		$stmt = $this->preparedStatements['sqlInsertSnippetLocations'];
		$num_values = count($vals);
		for( $i = 0; $i < $num_values; $i++ ){
			$stmt->execute( array($vals[$i][0], $vals[$i][1]) );
		}
		$rowid=$this->dbh->lastInsertId();
		return $rowid;
	}


	/**
	 * retrieves id of given location
	 * if id does not exist and insert is true, then new location is inserted
	 * returns false if location does not exist in db
	 */ 
	public function get_locationid($val, $snippetcount=0, $strlen=0, $insert=false){
		$stmt = $this->preparedStatements['sqlSelectLocationid'];
		$stmt->execute(array($val));
		if ( $locationid = $stmt->fetchColumn()){
			return $locationid;	
		}	
		if ( !$insert ){
			return false;
		}
		return $this->insert_location($val, $snippetcount, $strlen);
	}
	
	/**
	 * inserts given location into db
	 * returns locationid or false if insert failed
	 */ 
	public function insert_location($location, $snippetcount, $strlen){
		$this->preparedStatements['sqlInsertLocations']->execute( array($location, $snippetcount, $strlen) );
		return $this->dbh->lastInsertId();
	}
	
	/**
	 * deletes location associated with given locationid from database
	 * due to foreign key constraint: all relevant snippet_locations are also deleted
	 */
	public function delete_location($locationid){
		$this->preparedStatements['sqlDeleteLocation']->execute( array($locationid) );
		return $this->dbh->lastInsertId();
	}
	

	/**
	 * retrieves id of given snippet
	 * if id does not exist and insert is true, then new snippet is inserted
	 * returns false if snippet does not exist in db
	 */ 
	public function get_snippetid($val, $insert=false){
		$stmt = $this->preparedStatements['sqlSelectSnippetid'];
		$stmt->execute(array($val));
		if ( $snippetid = $stmt->fetchColumn()){
			return $snippetid;	
		}	
		return $insert?$this->insert_snippet($val):false;
	}
	
	/**
	 * inserts given snippet or array of snippets into db
	 * returns snippetid or false if insert failed
	 */ 
	public function insert_snippet($snippet){
		$rowid=false;
		$stmt = $this->preparedStatements['sqlInsertSnippet'];
		$handletransaction = !$this->dbh->inTransaction();
		if ( is_array($snippet) ){
			if ( $handletransaction ){
				$this->dbh->beginTransaction();
			}
			foreach($snippet as $s){
				$stmt->execute( array($s) );
			}
			if ( $handletransaction ){
				$this->dbh->commit();
			}
		}
		else{ //inserting as singleton
			$stmt->execute( array($snippet) );
		}
		$rowid=$this->dbh->lastInsertId();
		return $rowid;
	}
	
	/**
	 * inserts snippet/location association
	 * for each supplied snippetid
	 */ 
	public function insert_snippet_locations(array $snippetids, $locationid){
		$vals = array();
		foreach($snippetids as $snippetid){
			$vals[] = array($snippetid, $locationid);
		} 
		return $this->insert_snippet_location_vals($vals);
	}
	
	/**
	 * inserts relevant data points for the supplied string
	 */ 
	public function insert_string($string, $location){
		if (empty($string) ){
		 	throw new InvalidArgumentException("empty string");
		}
		if (empty($location) ){
		 	throw new InvalidArgumentException("empty location");
		}
		$snippets = $this->heuristic->makeSnippets($string);
		if (!empty($snippets)){
			$numsnippets = count($snippets);
			$snippetids = $this->get_snippetids($snippets, true);
			$numsnippetids = count($snippetids);
			if ($numsnippets > $numsnippetids){
				throw new Exception("unexpected error: not enough snippets found for \n\t$string\nat $locationid");
			}  
			$locationid = $this->get_locationid($location, count($snippets), mb_strlen($string), true);
			if (!$locationid){
				throw new Exception("unexpected error: no locationid found for $location");
			}  
			$this->insert_snippet_locations($snippetids, $locationid);
			// exceptions thrown here would be indications of a bad heuristic
			// they are truly "unexpected" and should not occur
		}
		// else: ignore string without useful snippets
	}
	
	/**
	 * gets ids for all supplied snippets
	 * if insert is true, then snippets are inserted if not in db yet
	 */ 
	public function get_snippetids(array $snippets, $insert=false){
		$snippetids = array();
		$numsnippets = count($snippets);
		$sqlsnippets = $this->prepareSqlSnippets($snippets, $numsnippets);
		$sql = 'SELECT snippet, snippetid FROM snippets WHERE snippet IN ('.$sqlsnippets.')'; 
		$stmt = $this->dbh->query($sql);
		$foundsnippets = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
		$notfoundsnippets = array_diff($snippets, $foundsnippets);
		if (!empty($notfoundsnippets)){
			$this->insert_snippet($notfoundsnippets);
		}
		$stmt = $this->dbh->query($sql);
		$snippetids = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);
		return $snippetids;
	}

	
	/**
	 * preps snippet array for use in sql statement
	 */ 
	public function prepareSqlSnippets($snippets, $numsnippets){
		$sqlsnippets = '';
		for($i=0; $i<$numsnippets; $i++){
			$sqlsnippets .= '\''.$snippets[$i].'\'';
			if ( $i+1<$numsnippets){
				$sqlsnippets .= ', ';
			}
		}
		return $sqlsnippets;
	}
	
	/**
	 * returns best hits for provided string
	 */ 
	public function get_best_locations($string, $snippetlength=3, $threshold=75){
		$snippets = $this->heuristic->makeSnippets($string, $snippetlength);
		if (empty($snippets)){
			return array();
		}
		$numsnippets = count($snippets);
		$sqlsnippets = $this->prepareSqlSnippets($snippets, $numsnippets);
		$deviation = 100-$threshold;
		$strlen = mb_strlen($string);
		$maxlen = $strlen + ($strlen*$deviation)/100;
		$minlen = $strlen - ($strlen*$deviation)/100;
		$sql = $this->prepareSqlSelectBestMatches($numsnippets, $strlen, $minlen, $maxlen, $sqlsnippets, $threshold);
		$ret = $this->run_timed_query($sql);
		return $ret;
	}
	
	/**
	 * inserts supplied values into sqlSelectBestMatches query
	 */ 
	function prepareSqlSelectBestMatches($numsnippets, $strlen, $minlen, $maxlen, $sqlsnippets, $threshold){
		$sql = $this->sqlSelectBestMatches;
		$sql = str_replace('{numsnippets}', $numsnippets, $sql);
		$sql = str_replace('{strlen}', $strlen, $sql);
		$sql = str_replace('{minlen}', $minlen, $sql);
		$sql = str_replace('{maxlen}', $maxlen, $sql);
		$sql = str_replace('{sqlsnippets}', $sqlsnippets, $sql);
		$sql = str_replace('{threshold}', $threshold, $sql);
		return $sql;		
	}
	
	/**
	 * runs a sql query and adds the processing time to the rendered output
	 */
	function run_timed_query($sql){
		$t1 = microtime(true);
		//print __FILE__."\n$sql\n";
		$res = $this->dbh->query($sql);
        $ret = $res->fetchAll(PDO::FETCH_FUNC, 'FuzzyIndex::renderColRow');
		$t2 = microtime(true);
		$diff = round($t2-$t1, 5);
		if ( !empty($ret)){
			$ret[0] = $diff.'s | '.$ret[0]; 
		}
        return $ret;		
	}


	
	/**
	* @author Jonathan Gotti <jgotti at jgotti dot org>
    * 	in class-sqlite3db.php
    * get the table list
    * @return array
    */
    public function list_tables(){
		$names = array();
        $res = $this->dbh->query('SELECT name FROM sqlite_master WHERE type=\'table\'');
        $rows = $res->fetchAll();
        foreach($rows as $row){
			$names[] = $row[0];
		}
		//print_r($names);
		return $names;
    }
    
    /**
     * converts array argument into string delimited by ' | '
     */
	public static function renderColRow() {
		$args = func_get_args();
		return implode (' | ', $args);
	}
	
	/**
	 * crude utility function to load plain text strings
	 * from a file
	 * @arg fullstring 	allows to specify whether the full strings should be stored in the db 
	 * 					or their location (filename + linenumber)
	 */
	public function load_lines_from_file($filename, $fullstring=false){
		if ( empty($filename) || !file_exists($filename)){
			throw new InvalidArgumentException("file not found: $filename");
		}
		print "loading ".basename($filename)."\n.";
		$t1 = microtime(true);
		$fh = fopen($filename, 'r');
		$linenumber=0;
		$loadedlines=0;
		$this->dbh->beginTransaction();
		while (!feof($fh) ){
			$linenumber++;
			$s = fgets($fh);
			$s = trim($s);			
			if ( $linenumber == 1 && substr($s, 0, 3)  == "\xef\xbb\xbf" ){
				// remove bom
				$s = substr($s, 3);
			}
			if ( empty($s) ){
				continue;
			}
			$loadedlines++;
			if ($fullstring){
				$this->insert_string($s, $s);
			}
			else{
				$this->insert_string($s, $filename.':'.$linenumber);
			}
			if ($loadedlines%1000==0){
				$this->dbh->commit();
				$this->dbh->beginTransaction();
				//print "linenumber: ".$linenumber."\n";
				print '.';
			}
		}
		print "\n";
		$this->dbh->commit();
		$t2 = microtime(true);
		$diff = $t2-$t1;
		print "$loadedlines of $linenumber lines loaded in $diff s\n";
	}
}

/**
 * defines public methods of a basic heuristic
 */
interface FuzzyIndexHeuristic{
	public function makeSnippets($string);
	public function quoteString($string);	
}

/**
 * a non-functional heuristic base class
 * supplying utility methods and modifier variable 
 */
class BaseHeuristic implements FuzzyIndexHeuristic{
	protected $modifier;	
	public function __construct($modifier=false){
		$this->modifier = $modifier;
	}
	public function quoteString($string){
		return htmlspecialchars($string, ENT_QUOTES);
	}
	public function quoteStrings($array){
		$newarray = array();
		foreach ($array as $s){
			$newarray[] = htmlspecialchars($s, ENT_QUOTES);
		}
		return $newarray;
	}
	public function makeSnippets($string){
		return array($string);
	}
}

/**
 * splits a string into overlapping substrings (snippets) of a given length 
 */
class CharsHeuristic extends BaseHeuristic{
	protected $pattern; 
	public function __construct($modifier=3){
		if (empty($modifier) || !is_int($modifier) || $modifier>10){
			// 1-10 is a generous range 
			throw new InvalidArgumentException("modifier should be between 1 and 10");
		}
		parent::__construct($modifier);
	}
	public function getPattern(){
		if ( isset($this->pattern)){
			return $this->pattern;
		}
		$snippetlength = $this->modifier;
		$snippets = array();
		$pattern = '';
		$tmp = $snippetlength+1;
		while(--$tmp){ 
			$pattern .= '.'; 
		}
		$this->pattern = $pattern;
		return $pattern;
	}
	public function makeSnippets($string){
		$pattern = $this->getPattern();
		$snippetlength = $this->modifier;
		$snippets = array();
		//surround trimmed string with spaces to provide
		//normalized boundaries
		$string = ' '.trim($string).' '; 
		while($snippetlength--){			
			$matches = array();
			if ( preg_match_all('/'.$pattern.'/u', $string, $matches, PREG_PATTERN_ORDER)){
				$snippets = array_merge($snippets, $matches[0]); 
			}
			$string = mb_substr($string, 1); // chop off first char
		}
		$snippets = array_unique($snippets);
		$snippets = array_merge($snippets, array()); // reindex array
		return $this->quoteStrings($snippets);
	}
}

/**
 * like CharsHeuristic, but converts strings to lowercase first
 */
class LowercaseCharsHeuristic extends CharsHeuristic{
	public function makeSnippets($string){
		$string = mb_strtolower($string, 'UTF-8');
		return parent::makeSnippets($string);
	}
}

/**
 * like CharsHeuristic, but ignores all snippets containing nonword chars
 */
class WordCharsHeuristic extends CharsHeuristic{
	public function makeSnippets($string){
		$snippets = parent::makeSnippets($string);
		return $this->reduceSnippetsByNonwordchars($snippets);
	}
	public function reduceSnippetsByNonwordchars($snippets){
		$newsnippets = array();
		foreach($snippets as $snippet){
			if ( !preg_match('/\W/', $snippet)){
				$newsnippets[] = $snippet;
			}
		}
		return $newsnippets;
	}
	
}
/**
 * like WordCharsHeuristic, but converts strings to lowercase first
 */
class LowercaseWordCharsHeuristic extends WordCharsHeuristic{
	public function makeSnippets($string){
		$string = mb_strtolower($string, 'UTF-8');
		return parent::makeSnippets($string);
	}
}
/**
 * splits string into "words", using punctuation and whitespace as delimiters
 * 
 */
class WordHeuristic extends BaseHeuristic{
	public function makeSnippets($string){
		// remove punctuation from word peripheries
		$string = ' '.trim($string).' ';
		$string = preg_replace('/\s[[:punct:]]+/u', ' ', $string);
		$string = preg_replace('/[[:punct:]]+\s/u', ' ', $string);
		$string = preg_replace('/\s+/u', ' ', $string);
		$string = trim($string);
		$string = $this->quoteString($string);
		// split on whitespace sequences
		$snippets = explode(' ', $string);  
		$snippets = array_unique($snippets);
		$snippets = array_merge($snippets, array()); // reindex array
		return $snippets;
	}
}
/**
 * like WordHeuri-stic, but converts strings to lowercase first
 */
class LowercaseWordHeuristic extends WordHeuristic{
	public function makeSnippets($string){
		$string = mb_strtolower($string, 'UTF-8');
		return parent::makeSnippets($string);
	}
}
/**
 * like WordHeuristic, but with broader set of delimiters (non-word chars) to
 * create smaller chunks
 */
class WordChunkHeuristic extends BaseHeuristic{
	public function makeSnippets($string){
		// split on non-word char sequences
		$string = preg_replace('/\W+/iu', ' ', $string);
		$string = preg_replace('/\s+/iu', ' ', $string);
		$string = trim($string);
		$snippets = explode(' ', $string);  
		$snippets = $this->quoteStrings($snippets);
		$snippets = array_unique($snippets);
		$snippets = array_merge($snippets, array()); // reindex array
		return $snippets;		
	}
} 
/**
 * like WordChunkHeuristic, but converts strings to lowercase first
 */
class LowercaseWordChunkHeuristic extends WordChunkHeuristic{
	public function makeSnippets($string){
		$string = mb_strtolower($string, 'UTF-8');
		return parent::makeSnippets($string);
	}
}
