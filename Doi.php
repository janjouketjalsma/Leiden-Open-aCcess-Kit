<?php
    class Doi {
        private $doi;
        private $issn = array();
        private $publisher;
        private $volume;
        private $issue;
        private $page;
        private $title;
        private $author;
        private $journal;
        private $url;
        private $status;
        private $year;
        private $type;
		private $message;
        private $CROSSREF_URL = "http://api.crossref.org/works/"; //only works for crossref issued doi
        
        
        
        public function __construct($doi) {
            //clean up the doi
            $this->doi = $this->cleanDoi($doi);
            $url = $this->CROSSREF_URL.$this->doi;
            $jsonfile = file_get_contents($url);
            $json = json_decode($jsonfile, true);
            $this->status = $json['status'];
            if ($json['status']=='ok') {
                $message = $json['message'];
				$this->message = $message;
                $this->publisher = $message['publisher'];
                $this->issue = $message['issue'];
                $this->type = $message['type'];
                $this->issn = $message['ISSN'];
				if (is_array($this->issn)) $this->issn = $this->issn[0];
                $this->journal = $message['container-title'];
                $this->page = $message['page'];
                $this->title = implode("; ",$message['title']);
                $this->volume = $message['volume'];
                $this->url = $message['URL'];
                $issued = $message['issued'];
                $dateparts = $issued['date-parts'];
                $this->year = $dateparts[0][0];
                foreach($message['author'] as $author) {
                    if (!empty($this->author)) $this->author .= ", ";
                    $this->author .= $author['family'].", ";
                    $givens = split(" ",$author['given']);
                    foreach($givens as $given) {
                        $this->author .= substr($given,0,1).".";
                    }
                    
                    
                }
                
            }
            
            
        }
        
        public static function cleanDoi($doi) {
            //check if there is an additional url text
            if (strpos(strtolower($doi), "http://dx.doi.org/") !== FALSE) {
                $doi = substr($doi, 18);
            } else if (strpos(strtolower($doi), "https://dx.doi.org/") !== FALSE) {
                $doi = substr($doi, 19);
            } else if (strpos(strtolower($doi), "doi:") !== FALSE) {
                $doi = substr($doi, 4);
            }
            return trim($doi);
        }
        
        public function getISSN() {
            return $this->issn;
        }
        
        public function display() {
            $answer = "<p>".$this->author." ".$this->title.". <em>";
            if (is_array($this->journal)) {
             $answer .= $this->journal[0];
            } else {
                $answer .= $this->title;
            }
            $answer .= "</em> <strong>".$this->volume."</strong>, ".$this->page." (".$this->year."). <a href=\"".$this->url."\">".$this->doi."</a></p>";
            return($answer);
        }
    }
?>