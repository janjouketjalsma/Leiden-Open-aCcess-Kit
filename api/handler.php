<?php
    header('Content-Type: application/json');
    require_once('../db.php');
    require_once('../Romeo.php');
    require_once('../Doi.php');
    require_once('../JournalRecord.php');


    //get request, output json
    $action = $_GET['action'];
    $id = $_GET['id'];
    $search = $_GET['search'];
    if(!empty($_REQUEST['query'])) $action = 'query';
    switch ($action) {
        case 'publishers':
            getPublishers();
            break;
        case 'query':
            getQuery($_REQUEST['query']);
            break;
        case 'stats':
            getStat();
            break;
        case 'list':
            getList();
            break;
        case 'search':
            getSearch($_REQUEST['q']);
            break;
        default:
            sendError('undefined action');
    }
    
    
    /**
     * Publishers 
     * Shows unique publishers names and gives number of hits
     * */
     function getPublishers() {
         $db = new MyDB();
         $data = array();
         $publisherList = array();
         $publisherAnswer = array();
         $sql = "SELECT DISTINCT Publisher FROM Journals";
         $results = $db->query($sql);
         while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
             array_push($publisherList, $row['Publisher']);
         }
         foreach ($publisherList as $publisher) {
             $sql = "SELECT COUNT(*) as count FROM Journals WHERE Publisher='".$publisher."'";
             $results = $db->query($sql);
             
            $row = $results->fetchArray();
            $num = $row['count'];
             array_push($data, array('publisher'=>$publisher, 'journals'=>$num));
         }
         
         $answer = array('status'=>'OK', 'msg'=>'Gives the different publishers and journals they represent', 'data'=>$data);
         $db->close();
         echo(json_encode($answer));
     }

    /**
     * Query
     * This function is used for autocompletion
     **/
    function getQuery($query) {
        //returns 10 suggestions for the typed word
        $db = new MyDB();
        $data = array();
        $query = SQLite3::escapeString($query);
        $sql = "SELECT Journal, ID FROM Journals WHERE Journal LIKE '%".$query."%' LIMIT 0,10";
        $results = $db->query($sql);
        while($row = $results->fetchArray(SQLITE3_ASSOC)) {
            array_push($data, array("value"=>$row['Journal'], "data"=>$row['ID']));
        }
        
        
        $db->close();
        $answer = array("query"=>$query, "suggestions"=>$data);
        echo(json_encode($answer));
        
    }
    
    /**
     * Stat
     * Gives some html text to put inside a status box
     **/
    function getStat() {
        $db = new MyDB();
        $html = "<div class=\"row\"><div class=\"col-md-6\"><h2>Open Access in big deals</h2>";
        $html .= "<p>The VSNU has made license deals in which the costs for Open Access publishing in certain journals are covered with the following publishers:</p>";
        $html .= "<ul>";
        $sql = "SELECT DISTINCT Publisher FROM Journals";
        $publishers = array();
        $results = $db->query($sql);
        while($row = $results->fetchArray(SQLITE3_ASSOC)) {
            if (!empty($row['Publisher'])) array_push($publishers, $row['Publisher']);
        }
        foreach($publishers as $publisher) {
            $sql = "SELECT COUNT(*) as count FROM Journals WHERE Publisher='".$publisher."'";
            $results = $db->query($sql);
            
            $row = $results->fetchArray();
            $num = $row['count'];
            $html .= "<li>".$publisher.": ".$num." journals</li>";
        }
        $html .= "</ul>";
        $html .= "<p>To use these deals, make sure to check the Open Access box when sending in your article! For the Royal Society we have several vouchers available. Ask the subject librarian science for more information and a voucher code: <a href=\"mailto:r.m.de.jong@library.leidenuniv.nl\">Rutger de Jong</a></p>";
        $html .="<p>&nbsp;</p></div>";
        $html .= "<div class=\"col-md-6\"><h2>Open Access by numbers</h2>";
        $html .= "<ul>";
        $html .="<li>Journals with Open Access arrangement: <strong>";
        $sql = "SELECT COUNT(*) as count FROM Journals";
        $results = $db->query($sql);
        $row = $results->fetchArray();
        $num = $row['count'];
        $html .= $num."</strong></li>";
        $html .= "<li>100 per cent paid for in: <strong>";
        $sql = "SELECT COUNT(*) as count FROM Journals WHERE reduction='100'";
        $results = $db->query($sql);
        $row = $results->fetchArray();
        $num = $row['count'];
        $html .= $num."</strong> journals</li>";
        $html .= "<li>Spread over <strong>";
        $sql = "SELECT COUNT(*) as count FROM Subjects";
        $results = $db->query($sql);
        $row = $results->fetchArray();
        $num = $row['count'];
        $html .= $num."</strong> different research subject categories</li>";
        $html .= "<li>With <strong>";
        $sql = "SELECT COUNT(*) as count FROM Journals WHERE quartile='Q1'";
        $results = $db->query($sql);
        $row = $results->fetchArray();
        $num = $row['count'];
        $html .= $num."</strong> journals belonging to the top 25 per cent of their field (quartile 1)</li>";
        $html .= "</ul></div></div>";
        $db->close();
        $answer = array('status' => 'ok', 'msg' => 'Some basic facts of the licensing deals', 'html'=>$html);
        echo(json_encode($answer));
        
    }
    
    function getSearch($search) {
        
        //first check if issn, title or doi
        $search = trim($search);
       if (strlen($search) >=8 && strlen($search) <= 9) {
            //could be issn
            if (preg_match("/[0-9xX]{8}/", $search) || preg_match("/[0-9xX]{4}-[0-9xX]{4}/", $search)) {
                if (strlen($search) == 8) {
                    $search = substr($search,0,4)."-".substr($search,4);
                }
                return searchISSN($search);
            }
        }
        
        //check if doi
        //should contain a 10 and a slash
        if (strpos($search, "10") !== false && strpos($search, "/") !== false) {
            return searchDOI($search);
        }
        
        //no doi no issn, check if title in database
        $db = new MyDB();
        $sql = "SELECT * FROM Journals WHERE Journal='".SQlite3::escapeString($search)."'";
        $results = $db->query($sql);
        while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
            $issn = $row['IssnPrint'];
            $issn1 = $row['IssnOnline'];
            if (empty($issn)) $issn = $issn1;
            $db->close();
            return searchISSN($issn);
        }
        
        $db->close();
        //not in our database, try sherpa
        $rm = new Romeo();
        $html = $rm->search($search);
        
        $answer = array('status'=>'ok', 'msg' => 'Answer from Sherpa/ROMEO on title search', 'html'=>$html);
        echo json_encode($answer);
    }
    
    function searchDOI($doi) {
        $crossref = new Doi($doi);
        $html = $crossref->display();
        $issn = $crossref->getISSN();
        if (is_array($issn)) $issn = $issn[0];
        searchISSN($issn, $html);
    }
    
    function searchISSN($issn, $extra = "") {
        $html = $extra;
        
        //check if issn in our database
        $jr = new JournalRecord();
        $jr->searchISSN($issn);
        $html .= $jr->html();
        
        //check romeo
        $html .= "<h2>Green Open Access Options</h2>";
        $romeo = new Romeo();
        $html .= $romeo->search($issn);
        
        $answer = array('status'=>'ok', 'msg'=>'Journal results by issn', 'html'=>$html);
        echo json_encode($answer);
    }
    
    
   

    /**
     * Send Error
     * Gives a status error
     * */
     function sendError($error) {
         $answer = array('status' => 'error', 'msg' => $error);
         echo json_encode($answer);
     }

?>