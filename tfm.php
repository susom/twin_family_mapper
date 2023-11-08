<?php
namespace Stanford\TwinFamilyMapper;

/** @var TwinFamilyMapper $module */

// An example project header
include_once APP_PATH_DOCROOT . "ProjectGeneral/header.php";

// Replace this with your module code
echo "Hello from $module->PREFIX";

$module->injectJSMO(["foo"=>"bar"], 'InitFunction');

?>
<div class="flex-column">
    <table id="summaryTable">
        <thead>
            <tr>
                <th>Record ID</th>
                <th>Event ID</th>
                <th>Family ID</th>
                <th>Email</th>
                <th>Secondary Email</th>
            </tr>
        </thead>
        <tbody>
        </tbody>
    </table>
    <br/>
    <div>
        <div id="refreshTable" class="btn btn-sm btn-primaryrc">Refresh Table</div>
        <div id="processMissingFamilyId" class="btn btn-sm btn-primaryrc">Try Process All Missing Family ID Records</div>

    </div>

</div>

<script>
    //console.log(module);
</script>
<?php

$module->emDebug("Test debug!", $module->getSetting('event-id'));


