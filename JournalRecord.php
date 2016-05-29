<?php
    require_once('db.php');

    class JournalRecord {
        private $id;
        private $issn_print;
        private $issn_online;
        private $title;
        private $publisher;
        private $reduction;
        private $impact;
        private $quartile;
        private $url;
        private $subjects = array();
        
        public function __construct($id = -1) {
            $this->load($id);
        }
        private function load($id) {
            if ($id != -1) {
                //load from database
                $db = new MyDB();
                if(!$db){
                    echo $db->lastErrorMsg();
                } else {
                }
                $results = $db->query("SELECT * FROM Journals WHERE ID='".$id."';");
                
                while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
                    $this->id = $row['ID'];
                    $this->title = $row['Journal'];
                    $this->reduction = $row['Reduction'];
                    $this->impact = $row['Impact'];
                    $this->quartile = $row['Quartile'];
                    $this->issn_print = $row['IssnPrint'];
                    $this->issn_online = $row['IssnOnline'];
                    $this->publisher = $row['Publisher'];
                    $this->url = $row['URL'];
                    
                }
                
                $results = $db->query("SELECT * FROM Subjects JOIN JournalXSubject ON JournalXSubject.SubjectID=Subjects.ID WHERE JournalXSubject.JournalID=".$id);
                while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
                    $this->addSubject($row['Subject']);
                }
                if (!$this->id > 0|| empty($this->id) || $this->id=="") {
                    $this->id = -1;
                }
            } else {
                $this->id = -1;
            }
        }
        
        public function getId() {
            return $this->id;
        }
        public function setId($new_id) {
            $this->id = $new_id;
        }
        
        public function getIssnPrint() {
            return $this->issn_print;
        }
        public function setIssnPrint($new_issn) {
            $this->issn_print = $new_issn;
        }
        
        public function setIssnOnline($issn) {
            $this->issn_online = $issn;
        }
        public function getIssnOnline() {
            return $this->issn_online;
        }
        
        public function getTitle() {
            return $this->title;
        }
        public function setTitle($new_title) {
            $this->title = $new_title;
        }
        
        public function save() {
            $db = new MyDB();
            if ($this->id > -1) {
                //it already exists
                //delete all couplings to subject areas
                $sql = "DELETE FROM JournalXSubject WHERE JournalID='".$id."'";
                $answer = $db->exec($sql);
                
                //now update
                $sql = "UPDATE Journals WHERE ID='".$id."' SET ";
                $sql .= "Journal='".SQLite3::escapeString($this->title)."', ";
                $sql .= "Reduction='".SQLite3::escapeString($this->reduction)."', ";
                $sql .= "Impact='".SQLite3::escapeString($this->impact)."', ";
                $sql .= "Quartile='".SQLite3::escapeString($this->quartile)."', ";
                $sql .= "IssnPrint='".SQLite3::escapeString($this->issn_print)."', ";
                $sql .= "IssnOnline='".SQLite3::escapeString($this->issn_online)."', ";
                $sql .= "Publisher='".SQLite3::escapeString($this->publisher)."', ";
                $sql .= "URL='".SQLite3::escapeString($this->url)."'";
                
            } else {
                $sql = "INSERT INTO Journals (ID,Journal,Reduction,Impact,Quartile,IssnPrint,IssnOnline,Publisher,URL) VALUES(NULL, ";
            
                $sql .= "'".SQLite3::escapeString($this->title)."', ";
                $sql .= "'".SQLite3::escapeString($this->reduction)."', ";
                $sql .= "'".SQLite3::escapeString($this->impact)."', ";
                $sql .= "'".SQLite3::escapeString($this->quartile)."', ";
                $sql .= "'".SQLite3::escapeString($this->issn_print)."', ";
                $sql .= "'".SQLite3::escapeString($this->issn_online)."', ";
                $sql .= "'".SQLite3::escapeString($this->publisher)."', ";
                $sql .= "'".SQLite3::escapeString($this->url)."')";
            }
            $answer = $db->exec($sql);
            if ($this->id == -1) $this->id =  $db->lastInsertRowid();
            //add subjects
            
            foreach($this->subjects as $subject) {
                if (!empty($subject)) {
                    $subject = strtolower($subject);
                    $subject = SQLite3::escapeString($subject);
                    $sql = "SELECT ID FROM Subjects WHERE subject='".$subject."'";
                    $answer = $db->query($sql);
                    $subject_id = -1;
                    
                    while ($row = $answer->fetchArray(SQLITE3_ASSOC)) {
                        $subject_id = $row['ID'];
                    }
                    
                    if ($subject_id == -1) {
                        //add it first
                        $sql_insert = "INSERT INTO Subjects (ID, subject) VALUES (NULL, '".$subject."')";
                        $ret2 = $db->exec($sql_insert);
                        $subject_id = $db->lastInsertRowID();
                        
                    } 
                    
                    $sql2 = "INSERT INTO JournalXSubject (JournalID, SubjectID) VALUES ('".$this->id."', '".$subject_id."')";
                    $answer2 = $db->exec($sql2);
                }
            }
            $db->close();
        }
        
        
        public function setReduction($red) {
            $this->reduction = $red;
        }
        public function getReduction() {
            return $this->reduction;
        }
        
        public function setImpact($impact) {
            $this->impact = $impact;
        }
        public function getImpact() {
            return $this->impact;
        }
        
        public function setQuartile($quartile) {
            $this->quartile = $quartile;
        }
        public function getQuartile() {
            return $this->quartile;
        }
        
        public function setPublisher($publisher) {
            $this->publisher = $publisher;
        }
        public function getPublisher() {
            return $this->publisher;
        }
        
        public function setURL($url) {
            $this->url = $url;
        }
        public function getUrl() {
            return $this->url;
        }
        
        public function setSubjects($subjects) {
            if (!is_array($subjects)) {
                $subjects = preg_split("/;/" ,$subjects);
            }
            $this->subjects = array();
            foreach($subjects as $subject) {
                array_push($this->subjects, trim(strtolower($subject)));
            }
        }
        public function addSubject($subject) {
            array_push($this->subjects, trim(strtolower($subject)));
        }
        public function deleteSubjects() {
            $this->subjects = array();
        }
        public function getSubjects() {
            return $this->subjects;
        }
        public function getSubjectsString() {
            $subjects = $this->subjects;
            $answer = "";
            $first = true;
            foreach($subjects as $subject) {
                if (!$first) $answer .= "; ";
                $answer .= $subject;
                $first = false;
            }
            return $answer;
        }
        
        public function toArray() {
            $subjects = "";
            foreach($this->subjects as $subject) {
                $subjects .= $subject.";";
            }
            return array("journal"=> $this->title, "discount"=>$this->reduction, "publisher"=>$this->publisher, "impact"=>$this->impact, "quartile"=>$this->quartile, "subjects"=>$subjects);
            
        }
        
        public function asArray() {
            $data = array("ID"=>$this->id, "Journal"=>$this->title, "Reduction"=>$this->reduction, "Publisher"=>$this->publisher, "Impact"=>$this->impact, "Quartile"=>$this->quartile, "subject category"=>$this->getSubjectsString());
            return $data;
        }
        
        public function searchISSN($issn) {
            if (strlen($issn) == 8) $issn = substr($issn, 0,4)."-".substr($issn,4);
            $db = new MyDB();
            $sql = "SELECT ID FROM Journals WHERE (IssnPrint='".$issn."' OR IssnOnline='".$issn."')";
            $results = $db->query($sql);
            $id = -1;
            while($row = $results->fetchArray(SQLITE3_ASSOC)) {
                $id = $row['ID'];
            }
            $this->load($id);
        }
        
        public function html() {
            if ($this->id == -1) {
                return ("<p><strong>The journal could not be found in the database with licenses for Gold Open Access.</strong></p>");
            } else {
                $html = "<h2>Gold Open Access options</h2>";
                $html .= "<table><tr><td><strong>Journal</strong></td><td>".$this->title." (issn: ".$this->issn_online.", ".$this->issn_print.")</td></tr>";
                $html .= "<tr><td><strong>APC-discount</strong></td><td>".$this->reduction." %</td></tr>";
                $html .= "<tr><td><strong>Publisher</strong></td><td>".$this->publisher."</td></tr>";
                $html .= "<tr><td><strong>Journal Impact</strong></td><td>".$this->impact." (".$this->quartile.")</td></tr>";
                $subjects = "";
                foreach($this->subjects as $subject) {
                    $subjects .= $subject.";";
                }
                $html .= "<tr><td><strong>Journal subjects</strong></td><td>".$subjects."</td></tr>";
                $html .= "<tr><td><strong>URL</strong></td><td><a href=\"".$this->url."\">".$this->url."</a></td></tr>";
                $html .= "</table>";
                return $html;
                
            }
        }
        
        public function display() {
            echo('JOURNAL ENTRY\n');
            echo($this->id."\n");
            echo('Title:\t\t'.$this->title.'\n');
            echo('Reduction:\t'.$this->reduction.'\n\n');
        }
    }

?>