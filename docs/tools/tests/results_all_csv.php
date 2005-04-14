<?php
/****************************************************************/
/* ATutor														*/
/****************************************************************/
/* Copyright (c) 2002-2004 by Greg Gay & Joel Kronenberg        */
/* Adaptive Technology Resource Centre / University of Toronto  */
/* http://atutor.ca												*/
/*                                                              */
/* This program is free software. You can redistribute it and/or*/
/* modify it under the terms of the GNU General Public License  */
/* as published by the Free Software Foundation.				*/
/****************************************************************/

define('AT_INCLUDE_PATH', '../../include/');
require(AT_INCLUDE_PATH.'vitals.inc.php');

authenticate(AT_PRIV_TEST_MARK);

$tid = intval($_GET['tid']);

function quote_csv($line) {
	$line = str_replace('"', '""', $line);
	$line = str_replace("\n", '\n', $line);
	$line = str_replace("\r", '\r', $line);
	$line = str_replace("\x00", '\0', $line);

	return '"'.$line.'"';
}

$sql	= "SELECT title, randomize_order FROM ".TABLE_PREFIX."tests WHERE test_id=$tid AND course_id=$_SESSION[course_id]";
$result	= mysql_query($sql, $db);

if (!($row = mysql_fetch_array($result))){
	require (AT_INCLUDE_PATH.'header.inc.php');
	$msg->printErrors('TEST_NOT_FOUND');
	require (AT_INCLUDE_PATH.'footer.inc.php');
	exit;
}
$test_title = str_replace(array('"', '<', '>'), '', $row['title']);
$random = $row['randomize_order'];

header('Content-Type: application/x-excel');
header('Content-Disposition: inline; filename="'.$test_title.'.csv"');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');


$sql	= "SELECT TQ.*, TQA.* FROM ".TABLE_PREFIX."tests_questions TQ INNER JOIN ".TABLE_PREFIX."tests_questions_assoc TQA USING (question_id) WHERE TQ.course_id=$_SESSION[course_id] AND TQA.test_id=$tid ORDER BY TQA.ordering, TQA.question_id";
$result	= mysql_query($sql, $db);

$questions = array();
$total_weight = 0;
$i=0;
while ($row = mysql_fetch_array($result)) {
	$row['score']	= 0;
	$questions[$i]	= $row;
	$questions[$i]['count']	= 0;
	$q_sql .= $row['question_id'].',';
	$total_weight += $row['weight'];
	$i++;
}
$q_sql = substr($q_sql, 0, -1);
$num_questions = count($questions);

$nl = "\n";

echo quote_csv(_AT('login_name')).', ';
echo _AT('date_taken').', ';
echo _AT('mark').'/'.$total_weight;
for($i = 0; $i< $num_questions; $i++) {
	echo ', Q'.($i+1).'/'.$questions[$i]['weight'];
}
echo $nl;

$sql	= "SELECT R.*, M.login FROM ".TABLE_PREFIX."tests_results R, ".TABLE_PREFIX."members M WHERE R.test_id=$tid AND R.final_score<>'' AND R.member_id=M.member_id ORDER BY M.login, R.date_taken";
$result = mysql_query($sql, $db);
if ($row = mysql_fetch_array($result)) {
	$count		 = 0;
	$total_score = 0;
	do {
		echo quote_csv($row['login']).', ';
		echo AT_date('%j/%n/%y %G:%i', $row['date_taken'], AT_DATE_MYSQL_DATETIME).', ';
		echo $row['final_score'];

		$total_score += $row['final_score'];

		$answers = array(); /* need this, because we dont know which order they were selected in */
		$sql = "SELECT question_id, score FROM ".TABLE_PREFIX."tests_answers WHERE result_id=$row[result_id] AND question_id IN ($q_sql)";
		$result2 = mysql_query($sql, $db);
		while ($row2 = mysql_fetch_array($result2)) {
			$answers[$row2['question_id']] = $row2['score'];
		}
		for($i = 0; $i < $num_questions; $i++) {
			$questions[$i]['score'] += $answers[$questions[$i]['question_id']];
			if ($answers[$questions[$i]['question_id']] == '') {
				echo ', -';
			} else {
				echo ', '.$answers[$questions[$i]['question_id']];
				if ($random) {
					$questions[$i]['count']++;
				}
			}
			
		}

		echo $nl;
		$count++;
	} while ($row = mysql_fetch_array($result));

	echo $nl;

	echo ' , '._AT('average').', ';

	echo number_format($total_score/$count, 1);

	for ($i = 0; $i < $num_questions; $i++) {
		if ($random) {
			$count = $questions[$i]['count'];
		}
		if ($questions[$i]['weight'] && $count) {
			echo ', '.number_format($questions[$i]['score']/$count, 1);
		} else {
			echo ', '.'-';
		}
	}

	echo $nl;

	echo ' , , '.number_format($total_score/$count/$total_weight*100, 1).'%';

	for($i = 0; $i < $num_questions; $i++) {
		if ($random) {
			$count = $questions[$i]['count'];
		}
		if ($questions[$i]['weight'] && $count) {
			echo ', '.number_format($questions[$i]['score']/$count/$questions[$i]['weight']*100, 1).'%';
		} else {
			echo ', '.'-';
		}
	}
	echo $nl;

}

?>
