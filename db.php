<?php
    require_once('fuzzyIndex/fuzzyIndex.php');
    require_once('JournalRecord.php');

    class MyDB extends SQLite3
   {
      public $BASE = __DIR__;
      public $FUZZYBASE = '/fuzzyIndex/fuzzydb.db';
      public $FUZZYHEURISTIC = "LowercaseCharsHeuristic";
      public $DB_FILE = 'test.db';
       
      function __construct()
      {
         $this->open($this->BASE.'/'.$this->DB_FILE);
         $this->createTable();
      } 
      
      function createTable() {
          $ret = $this->exec("create table if not exists Journals (ID INTEGER PRIMARY KEY, 
         Journal TEXT NOT NULL, 
         Reduction INTEGER NOT NULL,
         Impact REAL,
         Quartile VARCHAR(2),
         IssnPrint VARCHAR(9),
         IssnOnline VARCHAR(9),
         Publisher TEXT,
         URL TEXT)");
         
         //add one for the research areas
         $ret = $this->exec("create table if not exists Subjects (ID INTEGER PRIMARY KEY, 
         Subject TEXT NOT NULL)");
         
         //and one to couple subjects and journals
         $ret = $this->exec("create table if not exists JournalXSubject (ID INTEGER PRIMARY KEY, 
         JournalID INTEGER NOT NULL, 
         SubjectID INTEGER NOT NULL,
         UNIQUE(JournalID, SubjectID) ON CONFLICT REPLACE)");
      }
      
      function createNewFuzzy() {
          //add journal names to fuzzy database.
          //it is best to do this after all journals have been updated
          if (file_exists($FUZZYBASE)) unlink($FUZZYBASE);
          //get all Journal names
          $sql = "GET Journal, ID FROM Journals";
          $fi = new FuzzyIndex($FUZZYBASE);
	        $fi->setHeuristic($FUZZYHEURISTIC);
          $results = $this->query($sql);
            while($row = $results->fetchArray(SQLITE3_ASSOC) ){
                //add sentences to heuristics
                $fi->insert_string($row['Journal'], $row['ID']);
            }
      }
      
      function search($params) {
          $params = SQLite3::escapeString ( $params );
          //check if it is an issn or a Journal title
          $regex1 = '/\d{4}-\d{3}[\dxX]/'; //issn with -
          $regex2 = '/\d{7}[\dxX]/'; //issn without -
          $id = -1;
          if (!preg_match($regex1, $params) && !preg_match($regex2, $params)) {
              //no issn but Title. Try fuzzy
              $possibilities = $fi->get_best_locations($params);
              if (sizeof($possibilities) > 0) {
                  $id = $possibilities[0];
              }
          } else {
              $params = str_to_lowercase($params);
              $search1 = "";
              $search2 = "";
              if (preg_match($regex1, $params)) {
                  //is met streepje
                  $search1 = $params;
                  $search2 = substr($params,0,4).substr($params, 5);
              } else {
                  $search1= substr($params,0,4)."-".substr($params,4);
                  $search2 = $params;
              }
              $sql = "SELECT ID FROM Journals WHERE (issn_print='".$search1."' OR issn_print='".$search2."' OR issn_online='".$search1."' OR issn_online='".$search2."'";
              $results = $this->query($sql);
              while($row = $results->fetchArray(SQLITE3_ASSOC)) {
                  $id = $row['ID'];
              }
              
          }
          
          if ($id > -1) {
              return new JournalRecord($id);
          } else {
              //get Sherpa info
          }
          
      }
    
       function createJSONDump() {
           $ids = array();
           $answer = array();
           $sql = "SELECT ID FROM Journals";
           $results = $this->query($sql);
           while($row = $results->fetchArray(SQLITE3_ASSOC)) {
               array_push($ids, $row['ID']);
           }
           foreach($ids as $id) {
               $myJournal = new JournalRecord($id);
               array_push($answer, $myJournal->toArray());
           }
           
           $file = __DIR__.'list.txt';
           // Write the contents back to the file
           file_put_contents($file, json_encode($answer));
           
           
       }
      
      function getPublishers() {
          $sql = "SELECT DISTINCT Publisher FROM Journals";
          
      }
   }
?>