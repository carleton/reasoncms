<?php

function check_open_id(&$the_form) {
    $the_form->sess = & get_reason_session();
    if ($the_form->sess->exists()) {
        if (!$the_form->sess->has_started())
            $the_form->sess->start();
    }

    if ($the_form->sess->get('openid_id')) {
        $the_form->openid_id = $the_form->sess->get('openid_id');
        return $the_form->openid_id;
    } else {
        return false;
    }
}

function check_login() {
    $url = get_current_url();
    $parts = parse_url($url);
//    $url = $parts['scheme'] . '://' . $parts['host'] . '/openid/?next=' . $parts['scheme'] . '://' . $parts['host'] . $parts['path'];

    $txt = '<h3>Hi There!</h3>';
    $txt .= '<p>To begin or resume your application, please sign in using an
            <a href="http://openid.net/get-an-openid/what-is-openid/" target="_blank">Open ID</a>.</p>';
    $txt .= '</div>';

//    $url = get_current_url();
    try {
        $next_url = $_GET['next'];
    } catch (Exception $e) {
        $next_url = '';
    }
    if ($url) {
        $url = $parts['scheme'] . '://' . $parts['host'] . '/reason/open_id/new_token.php?next=' . $parts['scheme'] . '://' . $parts['host'] . $parts['path'] . '?' .$parts['query'];
    } else {
        $url = $parts['scheme'] . '://' . $parts['host'] . '/reason/open_id/new_token.php';
    }
    return $txt . '<iframe src="https://luthertest2.rpxnow.com/openid/embed?token_url=' . $url . '"
    scrolling="no" frameBorder="no" allowtransparency="true" style="width:400px;height:240px"></iframe>';
}

/*
 *  Repopulate elements with info that has been saved to the database
 */
function get_applicant_data($openid, &$the_form) {
    echo '<br />openid: ' . $openid . '<br />';
    connectDB('admissions_applications_connection');
    $qstring = "SELECT * FROM `applicants` WHERE `open_id`='" . addslashes($openid) . "' ";
//            $qstring = "SELECT * FROM `applicants` WHERE `open_id`='". $openid . "' ";

    $results = db_query($qstring);

    if (mysql_num_rows($results) < 1) {
        //
        //$qstring = "INSERT INTO `applicants` (`open_id`)  VALUES ('" . addslashes($openid) . "'); ";
        $qstring = "INSERT INTO `applicants` (`open_id`, `creation_date`,  `submitter_ip`)
            VALUES ('" . addslashes($openid) . "', NOW(), '" . $_SERVER['REMOTE_ADDR'] . "'); ";
        $results = mysql_query($qstring) or die(mysql_error());
        $qstring = "SELECT * FROM `applicants` WHERE `open_id`='" . addslashes($openid) . "' ";
        $results = db_query($qstring);
    }
    
    /*
     * array of elements that are a checkbox_group_type
     * these are stored comma-separated in their database field
     * to set the value of these they must be exploded then set
     */
    $checkbox_elements = array('activity_1_participation', 'activity_2_participation', 'activity_3_participation',
        'activity_4_participation', 'activity_5_participation', 'activity_6_participation', 'activity_7_participation',
        'activity_8_participation', 'activity_9_participation', 'activity_10_participation', 'race');
    while ($row = mysql_fetch_array($results, MYSQL_ASSOC)) {
        foreach ($the_form->get_element_names() as $element) {
            if (array_key_exists($element, $row)) {
                if (in_array($element, $checkbox_elements)) {
                    $the_value = explode(',', $row[$element]);
                    $the_form->set_value($element, $the_value);
                } else {
                    if (($element != 'date_of_birth') && ($row[$element] != '0000-00-00')) {
                        $the_value = $row[$element];
                        $the_form->set_value($element, $the_value);
                    }
                }
            } else if ($element == 'ssn_1'){
                // handle ssn which is an element group of ssn_1, ssn_2, and ssn_3
                // but stored in the db as ssn
                $exploded_ssn = explode('-', $row['ssn']);
//                die(pray($exploded_ssn));
                $the_form->set_value('ssn_1', $exploded_ssn[0]);
                $the_form->set_value('ssn_2', $exploded_ssn[1]);
                $the_form->set_value('ssn_3', $exploded_ssn[2]);
            }
        }
    }
    connectDB(REASON_DB);
}

/*
 * Write application data to database
 */
function set_applicant_data($openid, &$the_form) {
    connectDB('admissions_applications_connection');
    echo '<br>' . addslashes($openid) . '<br>';
    $qstring = "SELECT * FROM `applicants` WHERE `open_id`='" . addslashes($openid) . "' ";
    $results = db_query($qstring);
    if (mysql_num_rows($results) < 1) {
        $qstring = "INSERT INTO `applicants` (`open_id`, `creation_date`, `submitter_ip`)
            VALUES ('" . addslashes($openid) . "', NOW(), '" . $_SERVER['REMOTE_ADDR'] . "'); ";
        $results = mysql_query($qstring) or die(mysql_error());
        $qstring = "SELECT * FROM `applicants` WHERE `open_id`='" . addslashes($openid) . "' ";
        $results = db_query($qstring);
    }
    while ($row = mysql_fetch_array($results, MYSQL_ASSOC)) {
        $qstring = "UPDATE `applicants` SET ";
        foreach ($the_form->get_element_names() as $element) {
            if (array_key_exists($element, $row)) {
                $qstring .= $element . "=";
                if ((! is_null($the_form->get_value($element))) && ($the_form->get_value($element) <> '')) {
                    if (is_array($the_form->get_value($element))) {
                        $qstring .= "'" . addslashes(implode(',', $the_form->get_value($element))) . "'";
                    } else {
                        $qstring .= "'" . addslashes($the_form->get_value($element)) . "'";
                    }
                } else {
                    $qstring .= 'NULL';
                }
                $qstring .= ", ";
            }
            if ($element == 'ssn_1'){
                if($the_form->get_value('ssn_1') || $the_form->get_value('ssn_2') || $the_form->get_value('ssn_3')){
                    $qstring .= "`ssn` = '" . addslashes($the_form->get_value('ssn_1')) . "-" . addslashes($the_form->get_value('ssn_2')) . "-" . addslashes($the_form->get_value('ssn_3')) ."', ";
                }
            }
        }
        // ssn is 3 individual form elements, combine and write to db
        $qstring .= "`last_update`=NOW()";
//        $qstring = rtrim($qstring, ' ,');
        $qstring .= " WHERE `open_id`= '" . addslashes($openid) . "' ";
        //die($qstring);
    }
    $qresult = db_query($qstring);
    connectDB(REASON_DB);
}

function get_open_id() {
    $the_sess = get_reason_session();
    return $the_sess->get('openid_id');
}

function get_data($qstring){
    connectDB('admissions_applications_connection');
    $results = db_query($qstring);
    connectDB(REASON_DB);
    return $results;
}

function validate_page1(){
    /*
     * Required fields: student_type, enrollment_term, citizenship_status
     */
    return array('valid'=>True);
}
function validate_page2(&$the_form){
    /*
     * Required fields: first_name, middle_name, last_name, gender, date_of_birth,
     *                  email, home_phone, permanent_address, permanent_city,
     *                  permanent_state_province, permanent_state_province, permanent_zip_postal,
     *                  permanent_country, different_mailing_address (mailing_address, mailing_city,
     *                  mailing_state_province, mailing_zip_postal, mailing_country)
     */
    
    $qstring = "SELECT first_name, middle_name, last_name, gender, date_of_birth, " .
        "email, home_phone, permanent_address, permanent_city, " .
        "permanent_state_province, permanent_state_province, permanent_zip_postal, " .
        "permanent_country, different_mailing_address, mailing_address, mailing_city, " .
        "mailing_state_province, mailing_zip_postal, mailing_country " .
        "FROM applicants " .
        "WHERE open_id = '" . get_open_id() . "'";
    $results = get_data($qstring);
    $valid = True;
    $return = array();

    //should only be one row to loop through
    while ($row = mysql_fetch_array($results, MYSQL_ASSOC)) {

        //check always required fields
        if( is_null($row['first_name'])){               $valid=False;  $return['first_name'] = $the_form->get_display_name('first_name');  }
        if( is_null($row['middle_name'])){              $valid=False;  $return['middle_name'] = $the_form->get_display_name('middle_name'); }
        if( is_null($row['last_name'])){                $valid=False;  $return['last_name'] = $the_form->get_display_name('last_name'); }
        if( is_null($row['gender'])){                   $valid=False;  $return['gender'] = $the_form->get_display_name('gender'); }
        if( is_null($row['date_of_birth'])){            $valid=False;  $return['date_of_birth'] = $the_form->get_display_name('date_of_birth'); }
        if( is_null($row['email'])){                    $valid=False;  $return['email'] = $the_form->get_display_name('email'); }
        if( is_null($row['home_phone'])){               $valid=False;  $return['home_phone'] = $the_form->get_display_name('home_phone'); }
        if( is_null($row['permanent_address'])){        $valid=False;  $return['permanent_address'] = $the_form->get_display_name('permanent_address'); }
        if( is_null($row['permanent_city'])){           $valid=False;  $return['permanent_city'] = $the_form->get_display_name('permanent_city'); }
        if( is_null($row['permanent_state_province'])){ $valid=False;  $return['permanent_state_province'] = $the_form->get_display_name('permanent_state_province'); }
        if( is_null($row['permanent_zip_postal'])){     $valid=False;  $return['permanent_zip_postal'] = $the_form->get_display_name('permanent_zip_postal'); }
        if( is_null($row['permanent_country'])){        $valid=False;  $return['permanent_country'] = $the_form->get_display_name('permanent_country'); }

        //if different_mailing_address is set, check associated fields
        if(is_null($row['different_mailing_address']) == False){
            if( is_null($row['mailing_address'])){          $valid=False;  $return['mailing_address'] = $the_form->get_display_name('mailing_address'); }
            if( is_null($row['mailing_city'])){             $valid=False;  $return['mailing_city'] = $the_form->get_display_name('mailing_city'); }
            if( is_null($row['mailing_state_province'])){   $valid=False;  $return['mailing_state_province'] = $the_form->get_display_name('mailing_state_province'); }
            if( is_null($row['mailing_zip_postal'])){       $valid=False;  $return['mailing_zip_postal'] = $the_form->get_display_name('mailing_zip_postal'); }
            if( is_null($row['mailing_country'])){          $valid=False;  $return['mailing_country'] = $the_form->get_display_name('mailing_country'); }
        }
    }

    $return['valid'] = $valid;
    
    return $return;
}
function validate_page3(){
    /*
     * Required Fields: permanent_home_parent,
     *                  based on permanent_home_parent:  parent_1_first_name, parent_1_middle_name,
     *                  parent_1_last_name, parent_1_address, parent_1_city, parent_1_state_province,
     *                  parent_1_zip_postal, parent_1_country, parent_1_phone, parent_1_email,
     *                  parent_1_occupation (or replace "parent_1" with "parent_2" or "guardian"),
     *                  legacy,
     *                  based on legacy:  parent_1_college/parent_2_college/guardian_college
     */
    return array('valid'=>True);
}
function validate_page4(){
    /*
     * Required Fields: hs_name, hs_grad_year, based on student_type:  college_1_name
     */
    return array('valid'=>True);
}
function validate_page5(){
    /*
     * Required Fields: based on activity_1, if 'other' require activity_1_other (same for all activities)
     */
    return array('valid'=>True);
}
function validate_page6(){
    /*
     * Required Fields: college_plan_1, based on music_audition:  music_audition_instrument, financial_aid
     */
    return array('valid'=>True);
}


function validate_all_pages(){
    if (validate_page1 () && validate_page2() && validate_page3()
        && validate_page4() && validate_page5() && validate_page6()
    )
        return true;
}

?>