/**
** SEARCHBOX
**/

//Find/use journal
$('input.js-searchJournal.js-selectOnReturn')
.keypress(function(e) {
  if (e.which == 13) {
      var searchTerm = $(e.target).val();
      checkJournal(searchTerm);
      return false;
  }
})
.autocomplete({
    serviceUrl: 'api/handler.php',
    onSelect: function(suggestion) {
        $('#JournalIDSrch').val(suggestion.data);
        checkJournal(suggestion.value);
    }
});

//Use the entered journal on click of this button
$('button.js-selectJournal').click(function() {
    var searchTerm = $('input.js-searchJournal').val();
    checkJournal(searchTerm);
});

/**
** Statistics
**/
$.get("api/handler.php?action=stats", function(data) {
    $("#overView").html(data.html);
});




$('#journalTable').DataTable({

    ajax: 'api/datatables.php',
    processing: true,
    serverSide: true,
    dataSrc: 'data',
    columns: [{
        data: 'Journal'
    }, {
        data: 'Reduction'
    }, {
        data: 'Publisher'
    }, {
        data: 'Impact'
    }, {
        data: 'Quartile'
    }, {
        data: 'subject category',
        orderable: false
    }]

});

$(document).ready(function() {

    // DataTable
    var table = $('#journalTable').DataTable();

    table.on('xhr', function() {
        var json = table.ajax.json();


        var count = 0;
        $('#journalTable tfoot th').each(function() {
            var title = $(this).text();
            switch (count) {
                case (1):
                    //discount
                    var html = "<select>";

                    html = html + "<option></option>";
                    $.each(json.discountColumn, function(key, val) {
                        // do something with key and val
                        if (json.discountColumn.length == 1) {
                            html = html + "<option selected>" + val + "</option>";
                        } else {
                            html = html + "<option>" + val + "</option>";
                        }
                    });

                    html = html + "</select>";
                    $(this).html(html);
                    break;
                case (2):
                    //publisher
                    var html = "<select>";

                    html = html + "<option></option>";
                    $.each(json.publisherColumn, function(key, val) {
                        // do something with key and val
                        if (json.publisherColumn.length == 1) {
                            html = html + "<option selected>" + val + "</option>";
                        } else {
                            html = html + "<option>" + val + "</option>";
                        }
                    });

                    html = html + "</select>";
                    $(this).html(html);
                    break;
                case (4):
                    //quartile
                    var html = "<select>";

                    html = html + "<option></option>";
                    $.each(json.quartileColumn, function(key, val) {
                        // do something with key and val
                        if (json.quartileColumn.length == 1) {
                            html = html + "<option selected>" + val + "</option>";
                        } else {
                            html = html + "<option>" + val + "</option>";
                        }
                    });

                    html = html + "</select>";
                    $(this).html(html);
                    break;
                case (5):
                    //categories
                    var html = "<select>";

                    html = html + "<option></option>";
                    $.each(json.subjectColumn, function(key, val) {
                        // do something with key and val
                        if (json.subjectColumn.length == 1) {
                            html = html + "<option selected>" + val + "</option>";
                        } else {
                            html = html + "<option>" + val + "</option>";
                        }
                    });

                    html = html + "</select>";
                    $(this).html(html);
                    break;
            }

            count++;
        });



        // Apply the search
        table.columns().every(function() {
            var that = this;

            $('select', this.footer()).on('change', function() {
                if (that.search() !== this.value) {
                    that
                        .search(this.value)
                        .draw();
                }
            });
        });

    });


});

/**
** GET RESULTS
**/
function checkJournal(searchTerm) {
    var url = "api/handler.php?action=search&q=" + searchTerm;

    $.get(url, function(data) {
        $("#searchResult").html(data.html);
    });
}
