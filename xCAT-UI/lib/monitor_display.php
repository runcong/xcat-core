<?php
/* 
 * All the <html> code related with monitor interface is put here.
 */

#displayMonitorLists() will generate all the monitoring plug-ins,
#the user can select the plug-ins he wants to operate on,
#and press the "Next" button;
 function displayMonitorLists() {
     #The command "monls -a" is used to get the monitoring plug-ins list
     $xml = docmd("monls"," ", array('-a'));
     if(getXmlErrors($xml,$errors)) {
         echo "<p class=Error>",implode(' ', $errors), "</p>";
         exit;
     }
     #then, parse the xml data
     $ooe = 0;
     $line = 0;
     foreach($xml->children() as $response) foreach($response->children() as $data) {
         list($name, $stat, $nodemonstatus) = preg_split("/\s+/", $data);
         $ooe = $ooe%2;
         echo "<tr class='ListLine$ooe' id='row$line'>";
         echo "<td><input type='radio' name='plugins' value='$name' /></td>";
         echo "<td ><a class='description' href='#'>$name</a></td>";
         echo "<td >$stat</td>";
         if(isset($nodemonstatus)) { echo "<td >Enabled</td>";}else {echo "<td >Disabled</td>";}
         echo "<td>";
         $name_str = "\"$name\"";
         if($stat == "monitored") {
             $act_str = "\"stop\"";
             insertButtons(array('label'=>'Stop', 'id'=>'stop', 'onclick'=>"monsetupAction($name_str, $act_str)"));
             $act_str = "\"restart\"";
             insertButtons(array('label'=>'Restart', 'id'=>'restart', 'onclick'=>"monsetupAction($name_str, $act_str)"));
         }else {
             $act_str = "\"start\"";
             insertButtons(array('label' => 'Start', 'id'=>'start', 'onclick' => "monsetupAction($name_str, $act_str)"));
         }
         echo "</td>";
         echo "   </tr>";
         $ooe++;
         $line++;
         //echo "<tr><td><input type='checkbox' />$name</td><td>$stat</td><td><a onclick='LoadMainPage("main.php")'>$name</a></td></tr>";
     }
     return 0;
 }

function displayTips($tips)
{
    //to tell the user how to operate on the current web page
    //the argument $tips is an array like this:
    //{
    //  "Click the name of each plugin, you can get the plugin's description.",
    //  "You can also select one plugin, then you can set node/application status monitoring for the selected plugin.",
    //}
    echo '<div class="tips">'; 
    echo '<p class="tips_head"><b>Tips:</b>(Click me to display tips)</p>';
    echo '<div class="tips_content">';
    foreach ($tips as $tip) {
        echo "<p>$tip</p>";
        echo "\n";
    }
    echo "</div>";

    echo '<script type="text/javascript">';
    echo '$(handle_tips());';
    echo '</script>';
    echo '</div>';
    return 0;
}

function insertDiv($id)
{
    echo "<div id=$id></div>";
}

function displayMonTable()
{
    //create one table to display the monitoring plugins' list
    echo '<div id="monlist_table">';
    echo <<<TOS1
<table id="tabTable" class="tabTable" cellspacing="1">
    <thead>
        <tr class="colHeaders">
            <td></td>
            <td>Plug-in Name</td>
            <td>Status</td>
            <td>Node Status Monitoring</td>
            <td>Action</td>
        </tr>
    </thead>
TOS1;
echo <<<TOS9
<script type="text/javascript">
    showPluginOptions();
    showPluginDescription();
</script>
TOS9;
    echo '<tbody id="monlist">';
    displayMonitorLists();
    echo "</tbody></table></div>";
    return 0;
}

function display_stat_mon_table($args)
{
    //create one table to disable or enable node/application monitoring
    //the argument $args are one array like this:
    //{ 'xcatmon' => {
    //      'nodestat' => 'Enabled',
    //      'appstat' => 'Disabled',
    //  },
    //};
    //

    echo '<div style="margin-right: 50px; width:auto; margin-left: 50px">';
    foreach($args as $key => $value) {
        $name = $key;

        if($value{'nodestat'} == 'Enabled') {
            $ns_tobe = 'Disable';
        } else {
            $ns_tobe = 'Enable';
        }
        if($value{'appstat'} == 'Enabled') {
            $as_tobe = 'Disable';
        } else {
            $as_tobe = 'Enable';
        }

    }
    echo "<h3>Node/Application Status Monitoring for $name</h3>";
echo <<<TOS2
<table cellspacing="1" class="tabTable" id="tabTable"><tbody>
<tr class="ListLine0">
<td>Node Status Monitoring</td>
<td>
TOS2;
    insertButtons(array('label'=>$ns_tobe, 'id'=>'node_stat', 'onclick'=>"node_stat_control(\"$name\")"));
    //TODO: change the function name of node_stat_control
    echo '</td>';
    echo '</tr>';
    echo '<tr class="ListLine1">';
    echo '<td>Application Status Monitoring</td>';
    echo '<td>';
    insertButtons(array('label'=>$as_tobe, 'id'=>'app_stat', 'onclick'=>'show_monshow_data()'));
    echo '</td>';
    echo '</tr>';
    echo '</tbody> </table> </div>';
}

function displayStatus()
{
    //tell the user that the current interface is not done yet...
    echo "<div id='devstatus'><p>This interface is still under development -use accordingly.</p></div>";
}

function displayOSITree()
{
    //display the node range tree, but only with the nodes with OSI type
    //this follows the function displayNrTree();
    //it doesn't work on firefox!!!
echo <<<EOS3
<script type="text/javascript">
$(init_ositree());
</script>
<div id=ositree></div>
EOS3;
}

function displayAssociation()
{
    echo '<div id="association">';
echo <<<TOS5
<b>Available Condition/Response Associations</b>
<table id="tabTable" class="tabTable" cellspacing="1">
    <thead>
        <tr class="colHeaders">
            <td>Condition</td>
            <td>Response</td>
            <td>Node</td>
            <td>State</td>
            <td>Action</td>
        </tr>
    </thead>
    <tbody>
TOS5;
    $xml = docmd("webrun", "", array("lscondresp"));
    if(getXmlErrors($xml,$errors)) {
        echo "<p class=Error>",implode(' ', $errors), "</p>";
        exit;
    }
    //get all the condition&response associations for RMC
    foreach ($xml->children() as $response) foreach($response->children() as $data) {
        //get the data from xcatd
        $association = explode("=", $data);

        $ooe = 0;
        $line = 0;
        foreach($association as $elem) {
            $ooe = $ooe%2;
            //the format should be
            //"NodeReachability"\t"EmailRootOffShift"\t"hv8plus01.ppd.pok.ibm.com"\t"Active"
            $record = explode("\"", $elem);
            $cond = $record[1];
            $resp = $record[3];
            $node = $record[5];
            $state = $record[7];
            echo "<tr class='ListLine$ooe' id='row$line'>";
            echo "<td>$cond</td>";
            echo "<td>$resp</td>";
            echo "<td>$node</td>";
            echo "<td>$state</td>";
            echo "<td>";
            if($state == "Active") {
                insertButtons(array('label'=>'DeActivate', 'id'=>'deactivate', 'onclick'=>"control_RMCAssoc(\"$cond\", \"$node\", \"$resp\", \"stop\")"));
            }else if($state == "Not active"){
                insertButtons(array('label'=>'Activate', 'id'=>'activate', 'onclick'=>"control_RMCAssoc(\"$cond\", \"$node\", \"$resp\", \"start\")"));
            }
            echo "</td>"; 
            echo "</tr>";
            $ooe++;
            $line++;
        }
    }
    echo "</tbody></table></div>";
    return 0;
}

function displayCond()
{
    //the user selects one node/noderange from the #ositree div
echo <<<COND
<div id="avail_cond">
<b>Available Conditions</b>
<table id="tabTable" class="tabTable" cellspacing="1">
    <thead>
        <tr class="colHeaders">
            <td></td>
            <td>Conditions</td>
        </tr>
    </thead>
    <tbody>
COND;
    $xml = docmd("webrun", '', array("lscondition"));
    foreach($xml->children() as $response) foreach($response->children() as $data) {
        //get the data from xcatd
        $conditions = explode("=", $data);
    }
    $ooe = 0;
    $line = 0;
    foreach($conditions as $elem) {
        $ooe = $ooe%2;
        echo "<tr class='ListLine$ooe' id='row$line'>";
        echo "<td><input type=\"radio\" name=\"conditions\" value=\"$elem\" /></td>";
        echo "<td>$elem</td>";
        echo "</tr>";
        $ooe++;
        $line++;
    }
    echo "</tbody></table></div>";
    return 0;

}

function displayResp()
{
echo <<<RESP
<div id="avail_resp">
<b>Available Response</b>
<table id="tabTable" class="tabTable" cellspacing="1">
    <thead>
        <tr class="colHeaders">
            <td></td>
            <td>Response</td>
        </tr>
    </thead>
    <tbody>
RESP;
  $xml=docmd("webrun", '', array("lsresponse"));
  $ooe=0;
  $line=0;
  foreach($xml->children() as $response) foreach($response->children() as $data) {
      $responses = explode("=", $data);
  }
  foreach($responses as $elem) {
      $ooe = $ooe%2;
      echo "<tr class='ListLine$ooe' id='row$line'>";
      echo "<td><input type='checkbox' name='responses' value='$elem' /></td>";
      echo "<td>$elem</td>";
      echo "</tr>";
      $ooe++;
      $line++;
  }
    echo '</tbody></table></div>';
    return 0;
}

function displayCondResp()
{
    echo '<div id="condresp">';
    displayAssociation();
    displayCond();
    displayResp();
    insertButtons(array('label'=>'Add', id=>'addAssociation', 'onclick'=>'mkCondResp()'));
    insertButtons(array('label'=>'Cancel', id=>'cancel_op', 'onclick'=>'clearEventDisplay()'));
    echo '</div>';
    displayStatus();
}

function displayMonsetting()
//TODO: copied from the function displayTable() from display.php, need update
{
    echo "<div class='mContent'>";
    echo "<h1>$tab</h1>\n";
    insertButtons(array('label' => 'Save','id' => 'saveit'),
                    array('label' => 'Cancel', 'id' => 'reset')
            );
    $xml = docmd('tabdump', '', array("monsetting"));
    $headers = getTabHeaders($xml);
    if(!is_array($headers)){ die("<p>Can't find header line in $tab</p>"); }
    echo "<table id='tabTable' class='tabTable' cellspacing='1'>\n";
    #echo "<table class='tablesorter' cellspacing='1'>\n";
    echo "<thead>";
    echo "<tr class='colHeaders'><td></td>\n"; # extra cell for the red x
    #echo "<tr><td></td>\n"; # extra cell for the red x
    foreach($headers as $colHead) {echo "<td>$colHead</td>"; }
    echo "</tr>\n"; # close header row

    echo "</thead><tbody>";
    $tableWidth = count($headers);
    $ooe = 0;
    $item = 0;
    $line = 0;
    $editable = array();
    foreach($xml->children() as $response) foreach($response->children() as $arr){
            $arr = (string) $arr;
            if(ereg("^#", $arr)){
                    $editable[$line++][$item] = $arr;
                    continue;
            }
            $cl = "ListLine$ooe";
            $values = splitTableFields($arr);
            # X row
            echo "<tr class=$cl id=row$line><td class=Xcell><a class=Xlink title='Delete row'><img class=Ximg src=img/red-x2-light.gif></a></td>";
            foreach($values as $v){
                    echo "<td class=editme id='$line-$item'>$v</td>";
                    $editable[$line][$item++] = $v;
            }
            echo "</tr>\n";
            $line++;
            $item = 0;
            $ooe = 1 - $ooe;
    }
    echo "</tbody></table>\n";
    $_SESSION["editable-$tab"] = & $editable; # save the array so we can access it in the next call of this file or change.php
    echo "<p>";
    insertButtons(array('label' => 'Add Row', 'id' => 'newrow'));
    echo "</p>\n";
}

function displayRMCRsrc()
{
echo <<<TOS0
<b>Available RMC Resources</b>
<table id="tabTable" class="tabTable" cellspacing="1">
<thead>
    <tr class="colHeaders">
    <td></td>
    <td>Class Name</td>
    </tr>
</thead>
<tbody>
TOS0;
    $xml = docmd("webrun", "", array("lsrsrc"));
    if(getXmlErrors($xml,$errors)) {
        echo "<p class=Error>",implode(' ', $errors), "</p>";
        exit;
    }
    foreach($xml->children() as $response) foreach($response->children() as $data) {
        //get all the class name
        $classes = explode("=", $data);
    }
    $ooe = 0;
    $line = 0;
    foreach($classes as $class) {
        $ooe = $ooe%2;
        echo "<tr class='ListLine$ooe' id='row$line'>";
        echo "<td><input type='radio' name='classGrp' value='$class' onclick='showRMCAttrib()' /> </td>";
        echo "<td>$class</td>";
        echo "</tr>";
        $ooe++;
        $line++;
    }

    echo "</tbody></table>";
    return 0;
}

function displayRMCAttr()
{
    echo "<p>Select the RMC Resource, you will see all its available attributes here.</p>";
}


function RMCEventLogToTable()
{
    $xml = docmd("webrun", "", array("lsevent"));


    //var_dump($xml);
    foreach($xml->children() as $response) foreach($response->children() as $records)
    {
        //$data should be one array to store all the RMC event log.
        echo "<tr>";
        foreach($records->children() as $data) {
            echo "<td>$data</td>";
        }
        echo "</tr>";
    }
}
//displayRMCEventLog() to display the RMC event logs in one table with "tablesorter" class
function displayRMCEvnetLog()
{
echo <<<TOS9
<table class="tablesorter" cellspacing="1">
<thead>
    <tr>
        <th>Time</th>
        <th>Category</th>
        <th>Message</th>
</thead>
<tbody>
TOS9;
    RMCEventLogToTable();
    echo "</tbody></table>";
    //TODO: the following javascript doesn't work well.
echo <<<TOS8
<script type="text/javascript" type"utf-8">
 $("table").tablesorter({ sortList:  [[0,1],[1,1]] });
</script>
TOS8;
    echo "</div>";
}

function displayRMCMonshowAttr($attr) {
    //TODO: should add one argument to support the noderange argument
    echo "<div>";
    echo "<table class='tablesorter' cellspacing='1'>";
    echo "<thead>";
    echo "<tr>";
    echo "<td>Time</td>";
    echo "<td>$attr</td>";
    echo "</tr>";
    echo "</thead>";
    echo "<tbody>";

    //get all the data by the command "monshow"
    $xml = docmd("monshow", "", array("rmcmon", "-s", "-t", "60", "-a", "$attr"));
    //the error handling is skipped
    $index = 0;
    foreach($xml->children() as $response) foreach($response->children() as $data) {
        //handle the data here
        //skip the first 2 lines
        if($index++ < 2) {
            continue;
        }
        //then, parse "date" & "value"
        $arr = preg_split("/\s+/", $data);
        array_pop($arr);
        $val = array_pop($arr);
        $time = implode(" ", $arr);
        echo "<tr>";
        echo "<td>$time</td>";
        echo "<td>$val</td>";
        echo "</tr>";        
    }


    echo "</tbody>";
    echo "</table>";
    echo "</div>";
}

function displayRMCMonshowGraph($value) {
//display the RMC Performance Data
echo <<<TOS11
<div>
<script type="text/javascript">
rmc_monshow_draw_by_flot("$value");
</script>
<p><b>The Graph View for RMC Resource</b></p>
<div id='placeholder' style='width:600px;height:300px'>
</div>
</div>
TOS11;
}

/*
 * function displayOptionsForPlugin($plugin)
 * *****************************************
 * when the user selects one plugin, the avaiable configuration options will display in <div id='monconfig'> 
 * TODO: for the non-rmcmon plugins, only the option "node/application status monitoring setting" is shown;
 * 
 * the avaiable view options will also display in <div id='monview'>, 
 * TODO: right now, it's only implemented for rmcmon plugin.
 */
function displayOptionsForPlugin($name)
{
    echo "<div id='monconfig'>";
    echo "<p>Available Configurations for  <b>$name</b></p>";

    echo '<table id="tabTable" class="tabTable" cellspacing="1">';
    echo "<tbody>";

    //set up the options for the plugin with the name "$name"
    if($name == "rmcmon") {
        //node status monitor, RMC events, RMC resources
        echo "<tr class='ListLine0' id='row0'>";
        echo "<td>Node/Application Status Monitoring Setting</td>";
        echo "<td>";
        insertButtons(array('label'=>'Configure', 'id'=>'rmc_nodestatmon', 'onclick'=>'loadMainPage("monitor/stat_mon.php?name=rmcmon")'));
        echo "</td>";
        echo "</tr>";

        echo "<tr class='ListLine1' id='row1'>";
        echo "<td>RMC Events Monitoring Setting</td>";
        echo "<td>";
        insertButtons(array('label'=>'Configure', 'id'=>'rmc_event', 'onclick'=>'loadMainPage("monitor/rmc_event_define.php")'));
        echo "</td>";
        echo "</tr>";

        echo "<tr class='ListLine0' id='row2'>";
        echo "<td>RMC Resource Monitoring Setting</td>";
        echo "<td>";
        insertButtons(array('label'=>'Configure', 'id'=>'rmc_resource', 'onclick'=>'loadMainPage("monitor/rmc_resource_define.php")'));
        echo "</td>";
        echo "</tr>";

    } else {
        //there's only "node status monitoring" is enabled
        echo "<tr class='ListLine0' id='row0'>";
        echo "<td>Node/Application Status Monitoring Setting</td>";
        echo "<td>";
        insertButtons(array('label'=>'Configure', 'id'=>$name."_nodestatmon", 'onclick'=>"loadMainPage(\"monitor/stat_mon.php?name=$name\")"));
        echo "</td>";
        echo "</tr>";
    }

    echo "</tbody></table>";

    echo "</div>";

    echo "<div id='monview'>";
    echo "<p>View Options for <b>$name</b></p>";
    //there should be many choices for the user to view the clusters' status
echo <<<TOS1
<table id="view_tab" class="tabTable" cellspacing="1">
<thead>
    <tr class='colHeaders'>
        <td>Monitor Items</td>
        <td>Display Formats</td>
    </tr>
</thead>
<tbody>
TOS1;
    if($name == "rmcmon") {
        #display two rows, one for RMC event, another for RMC Resource Performance monitoring.
        echo "<tr class='ListLine0' id='row0'>";
        echo "<td>RMC Event Logs</td>";
        echo "<td>";
        insertButtons(array('label'=>'View in Text', 'id'=>'rmc_event_text', 'onclick'=>'loadMainPage("monitor/rmc_lsevent.php")'));
        echo "</td>";
        echo "</tr>";
        echo "<tr class='ListLine1' id='row1'>";
        echo "<td>RMC Resource Logs</td>";
        echo "<td>";
        insertButtons(array('label'=>'View in Text', 'id'=>'rmc_resrc_text', 'onclick'=>'loadMainPage("monitor/rmc_monshow.php")'));
        insertButtons(array('label'=>'View in Graphics', 'id'=>'rmc_resrc_graph', 'onclick'=>''));
        echo "</td>";
        echo "</tr>";
    }
    else {
        echo "<p>There's no view functions for $name.</p>";
    }

    echo "</tbody></table></div>";
}

/*
 * function displayNodeAppStatus($name)
 * ************************************
 * to display the web page "Node/Application Status Monitoring"
 * 
 */
function displayNodeAppStatus($name)
{
    displayMapper(array('home'=>'main.php', 'monitor'=>''));
    displayTips(array(
        "Enable/disable Node/App Status Monitoring by clicking the button",
        "In order to take affect, you have to START/RESTART the desired plugin"));

    //get the current status for "node-status-monitor"
    $xml = docmd("monls", ' ', array($name));
    if(getXmlErrors($xml,$errors)) {
        echo "<p class=Error>",implode(' ',$errors), "</p>";
        exit;
    }
    #then, parse the xml data
    foreach($xml->children() as $response) foreach($response->children() as $data) {
        list($n, $stat, $nodemonstatus) = preg_split("/\s+/",$data);
        if(isset($nodemonstatus)) {
            $ns = "Enabled";
        }else {
            $ns = "Disabled";
        }
    }

    display_stat_mon_table(array("$name"=>
        array(
            'nodestat'=>$ns,
            'appstat'=>'Disabled',  //currently application status monitoring is not supported by xCAT monitor Arch.
        )));
}

?>
