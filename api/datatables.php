<?PHP
    header('Content-Type: application/json');
    require_once('../db.php');
    require_once('../JournalRecord.php');
    
    /**
     * Parameters send by datatables
     **/
    $draw = SQLite3::escapeString($_REQUEST['draw']); //number to recognize asynchronous request
    $start = SQLite3::escapeString($_REQUEST['start']);
    $length = SQLite3::escapeString($_REQUEST['length']);
    $search = SQLite3::escapeString($_REQUEST['search']['value']);
    $order = $_REQUEST['order']; //has a column id (int) en dir (string)
    //$columns = SQLite3::escapeString($_REQUEST['columns']); //name and data and search
    $columns = $_REQUEST['columns'];
    
    
    //max number of results
    if ($length > 200 || $length <= 0) $length = 200;
    if (empty($start)) $start = 0;
    /**
     * Send back
     **/
    /**
    draw	integer	The draw counter that this object is a response to - from the draw parameter sent as part of the data request. Note that it is strongly recommended for security reasons that you cast this parameter to an integer, rather than simply echoing back to the client what it sent in the draw parameter, in order to prevent Cross Site Scripting (XSS) attacks.
    recordsTotal	integer	Total records, before filtering (i.e. the total number of records in the database)
    recordsFiltered	integer	Total records, after filtering (i.e. the total number of records after filtering has been applied - not just the number of records being returned for this page of data).
    data	array	The data to be displayed in the table. This is an array of data source objects, one for each row, which will be used by DataTables. Note that this parameter's name can be changed using the ajax option's dataSrc property.
    error	string	Optional: If an error occurs during the running of the server-side processing script, you can inform the user of this error by passing back the error message to be displayed using this parameter. Do not include if there is no error.**/
    
    $db = new MyDB();
    $recordsTotal = 0;
    
    $sql = "SELECT COUNT(*) as count FROM Journals";
    $results = $db->query($sql);
    while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
        $recordsTotal = $row['count'];
    }
    
    //now create the search request
    $columndata = array();
    $count = 0;
    $params = "";
    $first = true;
    foreach($columns as $column) {
        
        array_push($columndata, array("number"=>$count,"column"=>SQLite3::escapeString($column['data']), "search"=>SQLite3::escapeString($column['search']['value'])));
        $count++;
    }
    
    $subjectCategory = "";
    $subjectID = 0;
    $withSubjectParam = "";
    
    foreach($columndata as $column) {
        
        if (!empty($column['search']) && $column['column'] != 'subject category') {
            if ($first) $params = " WHERE ";
            if (!$first)  {
                $params .= "AND ";
                $withSubjectParam .= "AND ";
            }
            $params .= $column['column']." LIKE '%".$column['search']."%' ";
            $withSubjectParam .= "Journals.".$column['column']." LIKE '%".$column['search']."%' ";
            $first = false;

        }
        if (!empty($column['search']) && $column['column'] == 'subject category') {
            $subjectCategory = $column['search'];
        }
    }
    
    if(!empty($subjectCategory)) {
        $sql = "SELECT ID FROM Subjects WHERE subject='".SQLite3::escapeString($subjectCategory)."'";
        $results = $db->query($sql);
        while($row = $results->fetchArray(SQLITE3_ASSOC)){
            $subjectID = $row['ID'];
        }
    }
    
    if (!empty($search)) {
        if ($first) $params = " WHERE ";
        if (!$first) $params .= "AND ";
        $params .= "Journal LIKE '%".$search."%' ";
    }
    
    //create the ordering
    $ordering = "";
    $first = true;
    foreach($order as $orderby) {
        if ($first) $ordering .= " ORDER BY ";
        if (!$first) $ordering .= ", ";
        $ordering .= $columndata[SQLite3::escapeString($orderby['column'])]['column']." ".SQLite3::escapeString($orderby['dir']);
        $first = false;
    }

    
    
    $recordsFiltered = 0;
    if ($subjectID>0) {
        $sql = "SELECT COUNT(*) as count FROM Journals JOIN JournalXSubject ON Journals.ID=JournalXSubject.JournalID WHERE JournalXSubject.SubjectID=".$subjectID." ";
        if (!empty($withSubjectParam)) $sql .= "AND ".$withSubjectParam;
    } else {
        $sql = "SELECT COUNT(*) as count FROM Journals".$params;
    }
    
    
    
    $results = $db->query($sql);
    while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
        $recordsFiltered = $row['count'];
    }
    if ($subjectID>0) {
        $sql = "SELECT * FROM Journals JOIN JournalXSubject ON Journals.ID=JournalXSubject.JournalID WHERE JournalXSubject.SubjectID=".$subjectID." ";
        if (!empty($withSubjectParam)) $sql .= "AND ".$withSubjectParam;
        $sql .= "LIMIT ".$start.",".$length;
    } else {
    $sql = "SELECT * FROM Journals".$params.$ordering." LIMIT ".$start.",".$length;
    }
    $results = $db->query($sql);
    $journals = array();
    while($row = $results->fetchArray(SQLITE3_ASSOC)) {
        if ($subjectID > 0) {
            array_push($journals, $row['JournalID']);
        } else {
          array_push($journals, $row['ID']);
        }
    }
    $data = array();
    //get the records
    foreach ($journals As $journalID) {
        $journal = new JournalRecord($journalID);
        array_push($data, $journal->asArray());
    }
    
    //additional selection data
    $subjects = array();
    $sql = "SELECT Subject FROM Subjects ORDER BY Subject ASC";
    $results = $db->query($sql);
    while($row = $results->fetchArray(SQLITE3_ASSOC)) {
        array_push($subjects, $row['Subject']);
    }
    
    $discounts = array();
    $sql = "SELECT DISTINCT Reduction FROM Journals".$params;
    $results = $db->query($sql);
    while($row = $results->fetchArray(SQLITE3_ASSOC)) {
        array_push($discounts, $row['Reduction']);
    }
    $publishers = array();
    $sql = "SELECT DISTINCT Publisher FROM Journals".$params;
    $results = $db->query($sql);
    while($row = $results->fetchArray(SQLITE3_ASSOC)) {
        array_push($publishers, $row['Publisher']);
    }
    $quartiles = array();
    $sql = "SELECT DISTINCT Quartile FROM Journals".$params;
    $results = $db->query($sql);
    while($row = $results->fetchArray(SQLITE3_ASSOC)) {
        array_push($quartiles, $row['Quartile']);
    }
    
    $answer = array("draw"=>$draw, "recordsTotal"=>$recordsTotal, "recordsFiltered"=>$recordsFiltered, "data"=>$data, "subjectColumn"=>$subjects, "discountColumn"=>$discounts, "publisherColumn"=>$publishers, "quartileColumn"=>$quartiles);
    echo(json_encode($answer));
    /**/
    
?>