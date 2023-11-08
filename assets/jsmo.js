// This file extends the default JSMO object with methods for this EM
;{
    // Define the jsmo in IIFE so we can reference object in our new function methods
    const module = ExternalModules.Stanford.TwinFamilyMapper;

    // Extend the official JSMO with new methods
    Object.assign(module, {

        ExampleFunction: function() {
            console.log("Example Function showing module's data:", module.data);
        },

        // Ajax function calling 'TestAction'
        InitFunction: function () {
            console.log("Example Init Function");

            // Set up bindings
            $('#refreshTable').bind('click', module.getRecordTable);

            $('#processMissingFamilyId').bind('click', module.processAllMissingFamilyIds);



            // Initialize data table
            module.summaryTable = $('#summaryTable').DataTable({
                response: true
            });



            // Note use of jsmo to call methods
            module.ajax('TestAction', module.data).then(function (response) {
                // Process response
                console.log("Ajax Result: ", response);
            }).catch(function (err) {
                // Handle error
                console.log(err);
            });
        },

        getRecordTable: function() {
            console.log("getRecordTable");
            result = module.ajax("getRecordTable").then(function(response) {
                // Process response
                module.summaryTable.clear();
                module.summaryTable.rows.add(response);
                module.summaryTable.draw();
            }).catch(function(err) {
                // Handle error
                console.log("ERROR", err);
            });
        },

        processAllMissingFamilyIds: function() {
            result = module.ajax("processAllMissingFamilyIds").then(function(response) {
                // Process response
                console.log(response);
                // Process response
                module.summaryTable.clear();
                module.summaryTable.rows.add(response);
                module.summaryTable.draw();
            }).catch(function(err) {
                // Handle error
                console.log("ERROR", err);
            });
        }
    });
}
