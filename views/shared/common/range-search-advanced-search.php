<div class="field">
		<div class="two columns alpha">
				<?php echo $this->formLabel('range_search_term', __('Range Search')); ?>
		</div>
		<div class="inputs five columns omega">
				<p class="explanation">
				<?php
					echo __('You may enter a number in the forms XXXX, XXXX-YY, or XXXX-YY-ZZ, or a number range consisting of '.
									'two numbers, separated by a hypen ("-"). You may also select one of the units that you defined to '.
									'limit the search to. Range Search will find items that contain numbers and number ranges matching '.
									'your search. For example: "500" will find an item mentioning the number range "450-550".');
				?>
				</p>
				<p>
				<?php
					echo $this->formSelect('range_search_unit', @$_GET['range_search_unit'], array('multiple' => true, 'size' => 6), $selectUnits);
				?>
				</p>
				<p>
				<?php echo $this->formText('range_search_term', @$_GET['range_search_term'], null, array('size' => 10)); ?>
				</p>
		</div>
</div>
