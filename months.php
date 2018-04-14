<table cellpadding="5" cellspacing="0" border="1" bordercolor="000000">
	<thead>
		<tr>
			<th>Year</th>
			<?php for($month = 1; $month <= 12; $month++): ?><th><?=$month;?></th><?php endfor; ?>
		</tr>
	</thead>
	<tbody>
		<?php for($year = 1999; $year <= date("Y"); $year++): ?>
		<tr>
			<td><?=$year;?></td>
			<?php
				for($month = 1; $month <= 12; $month++):
				$max_day = date("t", mktime(0, 0, 0, $month, "1", $year));
			?>
			<td><?=$max_day;?></td>
			<?php endfor; ?>
		</tr>
		<?php endfor; ?>
	</tbody>
</table>