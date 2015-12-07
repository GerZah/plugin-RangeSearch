jQuery(document).bind("omeka:elementformload", function() {
  var $ = jQuery; // use noConflict version of jQuery as the short $ within this block

  var lightbox = lity(); // https://www.npmjs.com/package/lity

  var textFields = ["#rangeSearch1", "#rangeSearch2", "#rangeSearch3",
                    "#rangeSearch4", "#rangeSearch5", "#rangeSearch6"];

  // --------------------------------------------------------

  $("#rangeSearchWrapper").remove();
  $("#save")
    .append("<span id='rangeSearchWrapper'>"+
              $("#range-search-controls").html()+
              "</span>");

  // --------------------------------------------------------

  var currentTextArea = false;
  $("textarea").focus(function(e) { currentTextArea = $(this); })

  // --------------------------------------------------------

  function showHideSecondTriple(range) {
    if (range) { $("#rangeSearchSecondTriple").slideDown("fast"); }
    else { $("#rangeSearchSecondTriple").slideUp("fast"); }
  }

  // -------------------

  function presetFormValues(selText) {
    var usableSelection = rangeSearchFullMatch(selText); // in RangeSearchUI.php

    if (usableSelection) {
      var decimals = selText.match(/(\d+)/g); // decimals
      var cnt = decimals.length;
      var range = (cnt <= 3 ? false : true);
      $("#rangeSearchRange").prop("checked", range);
      showHideSecondTriple(range);

      for (i = 0; i < cnt; i++) { $(textFields[i]).val(decimals[i]); }
      for (i = cnt; i < 6; i++) { $(textFields[i]).val("0"); }

      var units = selText.match(/((?![-| ])\D)+/g); // no dashes, no blanks
      units = units.slice(0,3).join("-").toLowerCase();
      var unitsLen = units.length;
      for(var idx in rangeSearchUnits) {
        if (units == rangeSearchUnits[idx].toLowerCase().substr(0,unitsLen)) {
          $("#rangeSearchUnits").val(idx);
          return;
        }
      }
    }
    else {
      for (i = 0; i < 6; i++) { $(textFields[i]).val(""); }
      $("#rangeSearchRange").prop("checked", false);
      $("#rangeSearchUnits").val(-1);
      showHideSecondTriple(false);
    }

  }

  // -------------------

  $(".rangeSearchButtons button").click(function(e) {
    e.preventDefault();

    if (!currentTextArea) { alert(rangeSearchSelectFirst); return; }

    var sel = currentTextArea.getSelection();
    var selText = "";
    if (sel.start != sel.end) { selText = sel.text; }

    lightbox("#range-search-popup");
    presetFormValues(selText);
  });

  // --------------------------------------------------------

  $("#range-search-popup button").click(function(e) { e.preventDefault(); });

  // --------------------------------------------------------

  function isRange() {
    return $("#rangeSearchRange").is(":checked");
  }

  // -------------------

  $("#rangeSearchRange").change(function() { showHideSecondTriple(isRange()); });

  // --------------------------------------------------------

  function checkTextfields(curTextFields) {
    for(var textField of curTextFields) {
      if (!$(textField).val().match(/^\d+$/)) {
        alert(rangeSearchEnterNumber);
        $(textField).focus();
        return false;
      }
    }
    return true;
  }

  // -------------------

  $("#rangeSearchApply").click(function () {
    if (!currentTextArea) { alert(rangeSearchSelectFirst); return; }

    if ($("#rangeSearchUnits").val() == -1) {
      alert(rangeSearchSelectUnit);
      $("#rangeSearchUnits").focus();
      return;
    }

    if (!checkTextfields(textFields.slice(0, 3))) { return; }
    var range = isRange();
    if (range) {
      if (!checkTextfields(textFields.slice(3))) { return; }
    }

    var units = $('#rangeSearchUnits option:selected').text();
    units = units.split("-");

    var result="";
    result += $("#rangeSearch1").val() + units[0] + "-" +
              $("#rangeSearch2").val() + units[1] + "-" +
              $("#rangeSearch3").val() + units[2];

    if (range) {
      result += " - " +
                $("#rangeSearch4").val() + units[0] + "-" +
                $("#rangeSearch5").val() + units[1] + "-" +
                $("#rangeSearch6").val() + units[2];
    }

    currentTextArea.replaceSelectedText(result);
    lightbox.close();
  });

  // --------------------------------------------------------

  $(document).on('lity:close', function(event, lightbox) {
  });

  // --------------------------------------------------------

} );
