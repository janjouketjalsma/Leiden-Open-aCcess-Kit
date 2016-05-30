<?php
    class Romeo {
        private $ROMEO_URL = "http://www.sherpa.ac.uk/romeo/api29.php?"; //issn= of jtitle=
        private $API_KEY = "HRwKLwhW8LE"; //key specific to LOC (Leiden Open Checker)
        
        
        public function search($search) {
            $status = "Error";
            //check what to search
            $xml = "";
            $search = trim($search);
            if (strlen($search) <= 9 && strlen($search) >= 8) {
                //could be issn
                if (preg_match("/[0-9xX]{8}/", $search) || preg_match("/[0-9xX]{4}-[0-9xX]{4}/", $search)) {
                    return ($this->getISSN($search));
                }
            } else {
                //check by journal title and choose the correct one
                $url = $this->ROMEO_URL."jtitle=".urlencode($search)."&ak=".$this->API_KEY;
                $xml = file_get_contents($url);
                $parsed = new SimpleXMLElement($xml);
                
                if ($parsed->header->outcome == "notFound") return("<p><strong>The journal you are looking for could not be found in Sherpa/RoMEO</strong></p>");
                
                
                $answer .= "<p><strong>Please choose the correct journal from the following list:</strong></p><ul>";
                foreach ($parsed->journals->journal As $journal) {
                    $answer .= "<li><a href=\"javascript:void()\" onclick=\"checkJournal('".$journal->issn."');\">".$journal->jtitle."</a> (issn: ".$journal->issn.", publisher: ".$journal->romeopub.")</li>";
                }
                $answer .= "</ul>";
                
                
                
                
                return($answer);
            }
            
            return $status;
        }
        
        public function getISSN($issn) {
            if (strlen($issn) == 8) {
                $issn = substr($issn,0,4)."-".substr($issn,4);
            }
            $url = $this->ROMEO_URL."issn=".$issn."&ak=".$this->API_KEY;
            $xml = file_get_contents($this->ROMEO_URL."issn=".$issn."&ak=".$this->API_KEY);
            $parsed = new SimpleXMLElement($xml);
            
            if ($parsed->header->outcome == "notFound") return("<p><strong>The journal you are looking for could not be found in Sherpa/RoMEO</strong></p>");
            $numOfresults = (int) $parsed->header->numhits;
            $answer = "";
            if ($numOfresults == 1) {
                $answer = "<table><tr><td>";
                $answer .= "<tr><td><strong>Journal</strong></td><td>".$parsed->journals->journal->jtitle." (ISSN ";
                if (is_array($parsed->journals->journal->issn)) {
                    $first = true;
                    foreach($parsed->journals->journal->issn as $issn) {
                        if (!$first) $answer .= ", ";
                        $answer .= $issn;
                        $first = false;
                    }
                } else {
                    $answer .= $parsed->journals->journal->issn;
                }
                $answer .= ")</td></tr>";
                $answer .= "<tr><td><strong>RoMEO</strong></td><td>";
                if (is_array($parsed->publishers)) {
                    $publisher = $parsed->publishers->publisher[0];
                } else {
                    $publisher = $parsed->publishers->publisher;
                }
                
                $romeocolour = $publisher->romeocolour;
                switch ($romeocolour) {
                        case "green":
                            $answer .= "Green: Can archive pre-print and post-print or publisher's version/PDF";
                        break;
                        case "yellow":
                            $answer .= "Yellow: Can archive pre-print (ie pre-refereeing)";
                        break;
                        case "blue":
                            $answer .= "Blue: Can archive post-print (ie final draft post-refereeing) or publisher's version/PDF";
                        break;
                        case "white":
                            $answer .= "White: Archiving not formally supported";
                        break;
                }
                $answer .= "</td></tr>";
                $answer .= "<tr><td><strong>Auhtor's pre-print</strong></td><td>author <strong>".$publisher->preprints->prearchiving."</strong> archive pre-print (ie pre-refereeing)</td></tr>";
                    $prerestrictions = $publisher->preprints->prerestrictions;
                    $answer .= "<tr><td><strong>Restrictions</strong></td><td><ul>";
                    foreach ($prerestrictions->prerestriction As $prerestriction) {
                        $answer .= "<li>".$prerestriction."</li>";
                    }
                    $answer .= "</ul></td></tr>";
                    
                $answer .= "<tr><td><strong>Author's post-print</strong></td><td>author <strong>".$publisher->postprints->postarchiving."</strong> archive post-print (ie final draft post-refereeing)</td></tr>";
                    $postrestrictions = $publisher->postprints->postrestrictions;
                    $answer .= "<tr><td><strong>Restrictions</strong></td><td><ul>";
                    foreach ($postrestrictions->postrestriction As $postrestriction) {
                        $answer .= "<li>".$postrestriction."</li>";
                    }
                    $answer .= "</ul></td></tr>";
                    
                $answer .= "<tr><td><strong>Publisher's Version/PDF</strong></td><td>author <strong>".$publisher->pdfversion->pdfarchiving."</strong> archive publisher's version/PDF</td></tr>";
                    $answer .= "<tr><td><strong>Restrictions</strong></td><td><ul>";
					$pdfrestrictions = $publisher->pdfversion->pdfrestrictions;
                    foreach ($pdfrestrictions->pdfrestriction As $pdfrestriction) {
                        $answer .= "<li>".$pdfrestriction."</li>";
                    }
                    $answer .= "</ul></td></tr>";
                    
                $answer .= "<tr><td><strong>General conditions</strong></td><td><ul>";
                $conditions = $publisher->conditions;
                    foreach ($conditions->condition as $condition) {
                        $answer .= "<li>".$condition."</li>";
                    }
                $answer .= "</ul></td></tr>";
                $copyrightlinks = $publisher->copyrightlinks;
                $answer .= "<tr><td><strong>Copyright</strong></td><td>";
                    foreach ($copyrightlinks->copyrightlink As $copyrightlink) {
                        $answer .= "<a href=\"".$copyrightlink->copyrightlinkurl."\">".$copyrightlink->copyrightlinktext."</a> ";
                    }
                $answer .= "</td></tr>";
                $answer .= "<tr><td><strong>More information</strong></td><td><a href=\"http://www.sherpa.ac.uk/romeo/\">Data kindly provided by Sherpa/RoMEO</a></td></tr>";
                $answer .= "<tr><td><strong>Disclaimer</strong></td><td>".$parsed->header->disclaimer."</td></tr></table>";
            }
            return ($answer);
            
            
        }
        
    }
    
?>