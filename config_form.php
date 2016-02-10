<div class="field">
    <div class="two columns alpha">
        <?php echo get_view()->formLabel('range_search_units', __('Triple Units')); ?>
    </div>
    <div class="inputs five columns omega">
        <p class="explanation">
            <?php echo __('Please enter all triple units that you would like to support, one per line.'); ?>
        </p>
        <?php
				# ./application/libraries/Zend/View/Helper/FormTextarea.php
				# public function formTextarea($name, $value = null, $attribs = null)
				echo get_view()->formTextarea('range_search_units', $rangeSearchUnits, array( "rows" => 8 ) );
				?>
        <p class="explanation">
          <p>
            <a href="#" id="rangeSearchShowHideInfo">
              [<?php echo __('Please click here to show/hide additional information.'); ?>]
            </a>
          </p>
            <?php
            echo '<div id="rangeSearchInfo" style="display:none; font-size:80%;">'.__('
<p>
To specify a triple unit, please use the form “a-b-c”, e.g. like this:
<pre>
yd-ft-in
m-cm-mm
</pre>
</p>
<p>
You may also specify hierarchical conversion rates between the three single units;
you can do so by adding them in round bracktets after the triple unit, e.g. like this:
<pre>
yd-ft-in (1-3-12)
m-cm-mm (1-100-10)
</pre>
By this you would have specified that (a) 1 yard equals 3 feet,
while 1 foot equals 12 inch and (b) that 1 meter equals 100 centimeters,
while 1 centimeter equals 10 millimeters. — Obviously, the first number inside
the round brackets will always be “1”.
</p>
<p>
Additionally, you may group multiple triple units into categories; you can do so
by adding the category name in box brackets before the triple unit, e.g. like this:
<pre>
[Imperial] mi-yd-ft (1-1760-3)
[Imperial] yd-ft-in (1-3-12)
[Metric] km-m-cm (1-1000-100)
[Metric] m-cm-mm (1-100-10)
</pre>
<em>Please note:</em> Assigning a group name does not require adding conversion rates.</p>
<p>
“Yard” / “feet” and “meter” / “centimeter” are specified twice in two different
triple units. Within the same group of triple units, Range Search will
automatically create a semantic coherence between identical single units, so you
will be able to convert between them, based on their respective conversion rates.
</p>
<hr>
<p>
Entering numbers or ranges (hence the name, Range Search) into metadata fields,
you may use the pop-up tool that you can reach from within the item editor. You
may also type them manually in the form given below, i.e. using the concrete
numbers together with the unit names, e.g. like this:
<pre>
1yd-2ft-3in
1m-50cm - 2m
</pre>
As you can see, you may omit the last one or two numbers and units.<br>
<em>Please note:</em> The first number (i.e. the highest significant unit) may
be up to ten digits long, while the second and third number (i.e. the two lower
significant units) may each be up to four digits long.
</p>
            ')."</div>";
            ?>
        </p>
    </div>
    <div class="two columns alpha">
        <?php echo get_view()->formLabel('range_search_search_all_fields', __('Scan All Text Fields')); ?>
    </div>
    <div class="inputs five columns omega">
        <p class="explanation">
            <?php
            echo __('Check this if you want numbers / ranges processing to be carried out within all of an item\'s text fields.');
            ?>
        </p>
        <?php echo get_view()->formCheckbox('range_search_search_all_fields', null, array('checked' => $searchAllFields)); ?>
    </div>
	<div id="shownHiddenSeachAll">
    <div class="two columns alpha">
        <?php echo get_view()->formLabel('range_search_limit_fields', __('Limit Scan to Fields')); ?>
    </div>
    <div class="inputs five columns omega">
        <p class="explanation">
            <?php
            echo __('Please select the elements i.e. fields that the scan for names / ranges should be limited to.<br>'.
										'<em>Please note:</em> To select multiple entries, try holding '.
										'the Ctrl key (Windows) or the Cmd key (Mac) while clicking.');
            ?>
        </p>
				<?php echo get_view()->formSelect('range_search_limit_fields', $LimitFields, array('multiple' => true, 'size' => 10), $searchElements); ?>
		</div>
	<?php if ($withRelComments): ?>
    <div class="two columns alpha">
        <?php echo get_view()->formLabel('range_search_search_rel_comments', __('Scan Inside Relationship Comments')); ?>
    </div>
    <div class="inputs five columns omega">
        <p class="explanation">
            <?php
            echo __('The Item Relationships add-on is installed, and it has been patched to feature relationship comments. '.
										'Check this if you want Range Search to scan inside relationship comments.');
            ?>
        </p>
        <?php echo get_view()->formCheckbox('range_search_search_rel_comments', null, array('checked' => $searchRelComments)); ?>
		</div>
	<?php else: ?>
		<input type="hidden" name="range_search_search_rel_comments" id="range_search_search_rel_comments" value="<?php echo $searchRelComments; ?>">
	<?php endif;?>
	</div>

<script type="text/javascript">
// <!--
	jQuery(document).ready(function() {

		var $ = jQuery; // use noConflict version of jQuery as the short $ within this block

		showHideShownHiddenSearchAll();

		$("#range_search_units").change( function() { activateReindexCheckbox(); } );
		$("#range_search_search_all_fields").change( function() { showHideShownHiddenSearchAll(); activateReindexCheckbox(); } );
		$("#range_search_limit_fields").change( function() { activateReindexCheckbox(); } );
		$("#range_search_search_rel_comments").change( function() { activateReindexCheckbox(); } );

		function showHideShownHiddenSearchAll() {
			var searchAllPreset = $("#range_search_search_all_fields").is(":checked");
			// alert("foo: "+searchAllPreset);
			if (searchAllPreset) { $("#shownHiddenSeachAll").slideUp(); } else { $("#shownHiddenSeachAll").slideDown(); }
		}

		function activateReindexCheckbox() { $("#range_search_trigger_reindex").prop('checked', true); }

    $("#rangeSearchShowHideInfo").click(function(e) {
      e.preventDefault();
      var curDisplay = $("#rangeSearchInfo").css("Display");
      if (curDisplay=="none") { $("#rangeSearchInfo").slideDown(); } else { $("#rangeSearchInfo").slideUp(); }
    });

	} );
// -->
</script>

    <div class="two columns alpha">
        <?php echo get_view()->formLabel('range_search_trigger_reindex', __('Trigger Re-indexing of Existing Content')); ?>
    </div>
    <div class="inputs five columns omega">
        <p class="explanation">
            <?php
							echo __('<strong>Please note:</strong> Checking this box will re-generate the index <em>now</em> and '.
											'exactly <em>once</em>. This action will be carried out as soon as you click on "Save Changes".');
            ?>
        </p>
        <?php echo get_view()->formCheckbox('range_search_trigger_reindex', null, array('checked' => false)); ?>
        <p class="explanation">
            <?php
							echo __('<em>Explanation:</em> Range Search relies on a search index that is being created during content'.
											' maintenance in the background. However, existing content will not be re-indexed automatically. '.
											'So if you have existing content or modify your settings, you should re-generate the search index.');
            ?>
        </p>
    </div>

  <?php if (isset($debugOutput)) { ?>
    <div class="two columns alpha">
        <?php echo get_view()->formLabel('range_search_debug_output', __('Debug Output')); ?>
    </div>
    <div class="inputs five columns omega">
        <?php echo get_view()->formCheckbox('range_search_debug_output', null, array('checked' => $debugOutput)); ?>
    </div>
  <?php } ?>

</div>
