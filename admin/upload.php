<?php
	set_time_limit(0);
	require_once('../JournalRecord.php');
	require_once('../db.php');
	
	//first back up the database
	if (file_exists('../test.db')) rename('../test.db', '../'.time().'test.db');
	if (file_exists('../fuzzyIndex/fuzzy.db')) unlink('../fuzzyIndex/fuzzy.db');
	
	
	
	$db = new MyDB();


$target_dir = "uploads/";
$target_file = $target_dir . basename($_FILES["fileToUpload"]["name"]);
$uploadOk = 1;

// Check if file already exists
if (file_exists($target_file)) {
    echo "Sorry, file already exists.";
    $uploadOk = 0;
}
// Check file size
if ($_FILES["fileToUpload"]["size"] > 50000000) {
    echo "Sorry, your file is too large.";
    $uploadOk = 0;
}
// Allow certain file formats
if($imageFileType != "xls" && $imageFileType != "xlsx") {
    echo "Sorry, only xls and xlsx extensions are allowed.";
    $uploadOk = 0;
}
// Check if $uploadOk is set to 0 by an error
if ($uploadOk == 0) {
    echo "Sorry, your file was not uploaded.";
// if everything is ok, try to upload file
} else {
    if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $target_file)) {
        echo "The file ". basename( $_FILES["fileToUpload"]["name"]). " has been uploaded.";
    } else {
        echo "Sorry, there was an error uploading your file.";
    }
}


//try processing xlsx
require_once('../Excel-reader/SpreadsheetReader_XLSX.php');
require_once('../Excel-reader/SpreadsheetReader.php');
require_once('../Excel-reader/php-excel-reader/excel_reader2.php');

date_default_timezone_set('UTC');

	$StartMem = memory_get_usage();
	echo '---------------------------------'.PHP_EOL;
	echo 'Starting memory: '.$StartMem.PHP_EOL;
	echo '---------------------------------'.PHP_EOL;

	try
	{
		$Spreadsheet = new SpreadsheetReader($target_file);
		$BaseMem = memory_get_usage();

		$Sheets = $Spreadsheet -> Sheets();

		echo '---------------------------------'.PHP_EOL;
		echo 'Spreadsheets:'.PHP_EOL;
		print_r($Sheets);
		echo '---------------------------------'.PHP_EOL;
		echo '---------------------------------'.PHP_EOL;

		foreach ($Sheets as $Index => $Name)
		{
			echo '---------------------------------'.PHP_EOL;
			echo '*** Sheet '.$Name.' ***'.PHP_EOL;
			echo '---------------------------------'.PHP_EOL;

			$Time = microtime(true);

			$Spreadsheet -> ChangeSheet($Index);
			$firstLine = true;
			$publisher_pos = 0;
			$reduction_pos = 0;
			$issn_print_pos = 0;
			$issn_online_pos = 0;
			$impact_pos = 0;
			$quartile_pos = 0;
			$url_pos = 0;
			$subjects_pos = 0;
			$maxpos = 0;
			

			foreach ($Spreadsheet as $Key => $Row)
			{
				if ($Row && $firstLine)
				{
					//check the right columns
					$cnum = 0;
					foreach ($Row as $col) {
						$col = strtolower($col);
						if (strpos($col, 'publisher') !== false) {
							$publisher_pos = $cnum;
						} elseif (strpos($col, 'title') !== false) {
							$journal_pos = $cnum;
						}	elseif (strpos($col, 'discount') !== false) {
							$reduction_pos = $cnum;
						} elseif (strpos($col, 'impact') !== false) {
							$impact_pos = $cnum;
						} elseif (strpos($col, 'quartile') !== false) {
							$quartile_pos = $cnum;
						} elseif (strpos($col, 'issn print') !== false) {
							$issn_print_pos = $cnum;
						} elseif (strpos($col, 'issn online') !== false) {
							$issn_online_pos = $cnum;
						} elseif (strpos($col, 'url') !== false) {
							$url_pos = $cnum;
						} elseif (strpos($col, 'subject') !== false) {
							$subjects_pos = $cnum;
						}
						$cnum++;
					}
					
					//we use
					$posArray = array($publisher_pos, $journal_pos, $reduction_pos, $impact_pos, $quartile_pos, $issn_online_pos, $issn_print_pos, $url_pos, $subjects_pos);
					foreach ($posArray As $pos) {
						if ($pos > $maxpos) $maxpos = $pos;
					}
					
					
					$firstLine = false;
				}
				elseif ($Row && count($Row) > $maxpos) {
				   // print_r($Row);
				    //create new record
				    print($Row[$publisher_pos]);
				    $myRecord = new JournalRecord();
				    $myRecord->setTitle(trim($Row[$journal_pos]));
				    $myRecord->setPublisher(trim($Row[$publisher_pos]));
				    $myRecord->setImpact(trim($Row[$impact_pos]));
				    $myRecord->setIssnOnline(trim($Row[$issn_online_pos]));
				    $myRecord->setIssnPrint(trim($Row[$issn_print_pos]));
				    $myRecord->setQuartile(trim($Row[$quartile_pos]));
				    $myRecord->setReduction(trim($Row[$reduction_pos]));
				    $myRecord->setSubjects(trim($Row[$subjects_pos]));
				    $myRecord->setURL(trim($Row[$url_pos]));
				    $myRecord->save();
				    
				    
				    
				} else
				{
					var_dump($Row);
				}
				
			}
		
			echo PHP_EOL.'---------------------------------'.PHP_EOL;
			echo 'Time: '.(microtime(true) - $Time);
			echo PHP_EOL;

			echo '---------------------------------'.PHP_EOL;
			echo '*** End of sheet '.$Name.' ***'.PHP_EOL;
			echo '---------------------------------'.PHP_EOL;
		}
	}
	catch (Exception $E)
	{
		echo $E -> getMessage();
	}

?>